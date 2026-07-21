<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Formatter;

use JardisAdapter\Logger\Contract\LogFormatInterface;

/**
 * Formats log data into Grafana Loki Push API format
 *
 * Loki expects:
 * - Labels (indexed metadata) like level, context, app, env
 * - Values array with [nanosecond_timestamp, log_line]
 * - JSON structure with streams array
 *
 * Reference: https://grafana.com/docs/loki/latest/api/#post-lokiapiv1push
 */
class LogLokiFormat implements LogFormatInterface
{
    /** @var array<string, string> */
    private array $staticLabels;

    /**
     * @param array<string, string> $staticLabels Static labels to attach to all log entries
     */
    public function __construct(array $staticLabels = [])
    {
        $this->staticLabels = $staticLabels;
    }

    /**
     * Format log data into Loki Push API JSON format
     *
     * @param array<string|int, mixed> $logData
     * @return string JSON payload
     */
    public function __invoke(array $logData): string
    {
        // Build labels (Loki's indexing mechanism)
        $labels = $this->buildLabels($logData);

        // Build log line
        $logLine = $this->buildLogLine($logData['message'] ?? '', $logData);

        // Loki expects nanosecond timestamps
        $timestamp = $this->getNanosecondTimestamp($logData);

        // Loki Push API format
        $payload = [
            'streams' => [
                [
                    'stream' => $labels,
                    'values' => [
                        [
                            (string) $timestamp,
                            $logLine,
                        ],
                    ],
                ],
            ],
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * Build labels from log data
     *
     * @param array<string|int, mixed> $data
     * @return array<string, string>
     */
    private function buildLabels(array $data): array
    {
        $labels = $this->staticLabels;

        // Add level as label
        if (isset($data['level'])) {
            $labels['level'] = strtolower((string) $data['level']);
        }

        // Add context as label if present
        if (isset($data['context']) && $data['context'] !== '') {
            $labels['context'] = (string) $data['context'];
        }

        // Sanitize label values (Loki requirements: alphanumeric + underscore)
        foreach ($labels as $key => $value) {
            $labels[$key] = $this->sanitizeLabelValue($value);
        }

        return $labels;
    }

    /**
     * Build the actual log line (message + structured data)
     *
     * @param string $message
     * @param array<string|int, mixed> $data
     * @return string
     */
    private function buildLogLine(string $message, array $data): string
    {
        $parts = [$message];

        // Add additional data as JSON if present
        if (isset($data['data']) && !empty($data['data'])) {
            $jsonData = json_encode($data['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($jsonData !== false) {
                $parts[] = $jsonData;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Get nanosecond timestamp from log data or current time
     *
     * @param array<string|int, mixed> $data
     * @return int
     */
    private function getNanosecondTimestamp(array $data): int
    {
        // Check if timestamp is provided in data
        if (isset($data['timestamp'])) {
            if (is_int($data['timestamp'])) {
                // Assume seconds, convert to nanoseconds
                return $data['timestamp'] * 1000000000;
            }
            if (is_string($data['timestamp'])) {
                $timestamp = strtotime($data['timestamp']);
                if ($timestamp !== false) {
                    return $timestamp * 1000000000;
                }
            }
        }

        // Use current time in nanoseconds
        return (int) (microtime(true) * 1000000000);
    }

    /**
     * Sanitize label value to meet Loki requirements
     * Labels must match regex: [a-zA-Z_][a-zA-Z0-9_]*
     */
    private function sanitizeLabelValue(string $value): string
    {
        // Replace spaces and special characters with underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $value);

        // Ensure it starts with letter or underscore
        if ($sanitized !== null && !preg_match('/^[a-zA-Z_]/', $sanitized)) {
            $sanitized = '_' . $sanitized;
        }

        return $sanitized ?? 'unknown';
    }

    /**
     * Add a static label to all log entries
     *
     * @param string $key Label key
     * @param string $value Label value
     * @return self
     */
    public function addLabel(string $key, string $value): self
    {
        $this->staticLabels[$key] = $value;
        return $this;
    }

    /**
     * Get all static labels
     *
     * @return array<string, string>
     */
    public function getStaticLabels(): array
    {
        return $this->staticLabels;
    }
}
