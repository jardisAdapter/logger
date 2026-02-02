<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Formatter;

use JardisAdapter\Logger\Contract\LogFormatInterface;

/**
 * Formats log data into ChromeLogger protocol format
 * Accumulates rows for batch sending via X-ChromeLogger-Data header
 *
 * This formatter is stateful and accumulates rows until reset() is called.
 * It returns individual rows when called with log data, and complete payload when called with empty array.
 *
 * Reference: https://craig.is/writing/chrome-logger
 */
class LogBrowserConsoleFormat implements LogFormatInterface
{
    private const PROTOCOL_VERSION = '4.1.0';

    /** @var array<int, array<int, mixed>> */
    private array $rows = [];

    /**
     * Format log data into ChromeLogger format
     *
     * When called with log data: adds row and returns JSON representation
     * When called with empty array: returns complete ChromeLogger payload with all accumulated rows
     *
     * @param array<string|int, mixed> $logData
     * @return string JSON representation
     */
    public function __invoke(array $logData): string
    {
        // If empty array, return accumulated data for header
        if (empty($logData)) {
            return $this->getAccumulatedData();
        }

        // Build and accumulate row
        $row = $this->buildRow($logData);
        $this->rows[] = $row;

        // Return single row for stream output
        $output = json_encode([
            'message' => $logData['message'] ?? '',
            'data' => $logData,
            'type' => $this->mapLevelToType($logData['level'] ?? 'info'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $output !== false ? $output : '{}';
    }

    /**
     * Build a ChromeLogger row from log data
     *
     * @param array<string|int, mixed> $logData
     * @return array<int, mixed>
     */
    private function buildRow(array $logData): array
    {
        $type = $this->mapLevelToType($logData['level'] ?? 'info');
        $message = $logData['message'] ?? '';

        // Build message parts
        $messageParts = [$message];

        // Add context if present
        if (isset($logData['context']) && $logData['context'] !== '') {
            $messageParts[] = "[{$logData['context']}]";
        }

        // Add data if present
        if (isset($logData['data']) && !empty($logData['data'])) {
            $messageParts[] = $logData['data'];
        }

        // Build backtrace (optional - simplified for header size)
        $backtrace = 'unknown';
        if (isset($logData['file']) && isset($logData['line'])) {
            $backtrace = "{$logData['file']}:{$logData['line']}";
        }

        return [
            $messageParts,  // Log data (message + context + data)
            $backtrace,     // Backtrace string
            $type,          // Log type
        ];
    }

    /**
     * Map PSR-3 log level to ChromeLogger type
     */
    private function mapLevelToType(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical', 'error' => 'error',
            'warning' => 'warn',
            'notice', 'info' => 'info',
            'debug' => 'log',
            default => 'log',
        };
    }

    /**
     * Get accumulated data for ChromeLogger header
     */
    private function getAccumulatedData(): string
    {
        $data = [
            'version' => self::PROTOCOL_VERSION,
            'columns' => ['log', 'backtrace', 'type'],
            'rows' => $this->rows,
        ];

        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * Reset accumulated rows
     */
    public function reset(): void
    {
        $this->rows = [];
    }

    /**
     * Get current accumulated rows (for testing)
     *
     * @return array<int, array<int, mixed>>
     */
    public function getRows(): array
    {
        return $this->rows;
    }
}
