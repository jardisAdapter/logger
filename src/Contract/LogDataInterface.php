<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Contract;

interface LogDataInterface
{
    /**
     * @param string $context log level.
     * @param string $level log level.
     * @param string $message message string for data.
     * @param ?array<string, mixed> $data additional information to the message.
     * @return array<string, mixed>
     */
    public function __invoke(string $context, string $level, string $message, ?array $data = null): array;

    /**
     * Adds a field to the root level of the log record structure.
     *
     * Use this for structured fields that should be indexed separately:
     * - System metadata: timestamp, hostname, pid, environment
     * - Infrastructure context: region, datacenter, server_id
     * - Fields that become database columns when using LogDatabase
     * - Fields that need to be searchable at top-level in log aggregators
     *
     * @param string $fieldName The name of the field to add at root level
     * @param callable(): (string|int|float|bool|array<string, mixed>|null) $value
     *        A callback function that returns the value for this field
     * @return self The instance of the current object for method chaining.
     */
    public function addField(string $fieldName, callable $value): self;

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
     *
     * @param string $fieldName The name of the field to be added to 'data'
     * @param callable(): (string|int|float|bool|array<string, mixed>|null) $value
     *        A callable that returns the value for this field
     * @return self Returns the current instance for method chaining.
     */
    public function addExtra(string $fieldName, callable $value): self;
}
