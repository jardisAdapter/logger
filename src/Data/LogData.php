<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Data;

use JardisAdapter\Logger\Contract\LogDataInterface;

/**
 * Class Record is responsible for managing log data and generating log records
 * based on provided context, level, message, and additional data. It also allows
 * for dynamic additions of custom log data fields.
 */
class LogData implements LogDataInterface
{
    public const CONTEXT = 'context';
    public const LEVEL = 'level';
    public const MESSAGE = 'message';
    /** @var array<string, mixed>  */
    private array $additionalUserLogData = [];
    /** @var array<string, mixed>  */
    private array $recordLogData;

    /**
     * Constructor method to initialize the log record data.
     *
     * @param array<string, mixed> $additionalRecordLogData Additional data to be merged with the log record data.
     * @param array<string, mixed> $additionalUserLogData Additional data to be merged with the log user data.
     * @return void
     */
    public function __construct(?array $additionalRecordLogData = [], ?array $additionalUserLogData = [])
    {
        $this->recordLogData = array_merge($additionalRecordLogData ?? [], [
            static::CONTEXT => '',
            static::LEVEL => '',
            static::MESSAGE => '',
        ]);
        $this->additionalUserLogData = $additionalUserLogData ?? [];
    }

    public function __invoke(string $context, string $level, string $message, ?array $data = null): array
    {
        $logData = [];
        foreach ($this->recordLogData as $key => $value) {
            if (is_scalar($value) || is_array($value)) {
                if ($key === static::CONTEXT) {
                    $logData[$key] = $context;
                } elseif ($key === static::LEVEL) {
                    $logData[$key] = $level;
                } else {
                    $logData[$key] = $value;
                }
            } elseif (is_callable($value)) {
                $logData[$key] = $value();
            }
        }

        // Execute callables in additionalUserLogData (extra fields)
        $extraData = [];
        foreach ($this->additionalUserLogData as $key => $value) {
            if (is_callable($value)) {
                $extraData[$key] = $value();
            } else {
                $extraData[$key] = $value;
            }
        }

        $data = array_merge($data ?? [], $extraData);
        $logData[static::MESSAGE] = $this->interpolate($message, array_merge($data, $logData));
        $logData['data'] = $data;

        return $logData;
    }

    /**
     * Adds a field to the root level of the log record structure.
     *
     * Use this for structured fields that should be indexed separately:
     * - System metadata: timestamp, hostname, pid, environment
     * - Infrastructure context: region, datacenter, server_id
     * - Fields that become database columns when using LogDatabase
     * - Fields that need to be searchable at top-level in log aggregators (Loki, Elasticsearch)
     *
     * **Result structure:**
     * ```json
     * {
     *   "context": "OrderService",
     *   "level": "info",
     *   "message": "Order created",
     *   "timestamp": "2025-11-30T10:00:00Z",  // <- Added via addField()
     *   "hostname": "server-01",               // <- Added via addField()
     *   "data": {}
     * }
     * ```
     *
     * @param string $fieldName The field name to add at root level
     * @param callable $value Callable that returns the value for this field
     * @return self Returns the current instance for method chaining.
     *
     * @see addExtra() For business/domain context that goes into 'data' field
     *
     * @example
     * ```php
     * $logData->addField('timestamp', new LogDateTime())
     *         ->addField('hostname', fn() => gethostname())
     *         ->addField('env', fn() => 'production');
     * ```
     */
    public function addField(string $fieldName, callable $value): self
    {
        if (!array_key_exists($fieldName, $this->recordLogData)) {
            $this->recordLogData[$fieldName] = $value;
        }

        return $this;
    }

    /**
     * Adds a field to the 'extra' section of the log record (inside the 'data' field).
     *
     * Inspired by Monolog's processor pattern, this method automatically enriches
     * the context data with additional fields on every log call.
     *
     * Use this for business/domain context that is dynamic per request:
     * - Business identifiers: order_id, user_id, transaction_id
     * - Request tracking: request_id, correlation_id, trace_id
     * - Domain-specific data that goes into the JSON blob in LogDatabase
     * - Any data that changes per log call and belongs to business logic
     *
     * **Result structure:**
     * ```json
     * {
     *   "context": "OrderService",
     *   "level": "info",
     *   "message": "Order created",
     *   "data": {
     *     "order_id": "12345",        // <- From $logger->info() context param
     *     "request_id": "uuid...",    // <- Added via addExtra() - automatic enricher
     *     "correlation_id": "..."     // <- Added via addExtra() - automatic enricher
     *   }
     * }
     * ```
     *
     * @param string $fieldName The field name to add inside 'data'
     * @param callable $value Callable that returns the value for this field
     * @return self Returns the current instance for method chaining.
     *
     * @see addField() For system/infrastructure fields at root level
     *
     * @example
     * ```php
     * $logData->addExtra('request_id', new LogUuid())
     *         ->addExtra('user_id', fn() => $session->getUserId())
     *         ->addExtra('correlation_id', fn() => $request->getHeader('X-Correlation-ID'));
     * ```
     */
    public function addExtra(string $fieldName, callable $value): self
    {
        if (!array_key_exists($fieldName, $this->additionalUserLogData)) {
            $this->additionalUserLogData[$fieldName] = $value;
        }

        return $this;
    }

    /**
     * Replaces placeholders in a message string with corresponding values from the provided data array.
     *
     * @param string $message The message containing placeholders in the format {key}.
     * @param array<string, mixed> $data An associative array where keys correspond to placeholders in the message,
     * and values are their replacements.
     * @return string The message with placeholders replaced by their corresponding values from the data array.
     */
    protected function interpolate(string $message, array $data): string
    {
        $replacements = [];
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $replacements['{' . $key . '}'] = $value;
            } elseif (is_array($value)) {
                $replacements['{' . $key . '}'] = json_encode($value);
            } elseif (is_callable($value)) {
                $replacements['{' . $key . '}'] = $value();
            }
        }

        return strtr($message, $replacements);
    }
}
