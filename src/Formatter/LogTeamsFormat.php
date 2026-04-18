<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Formatter;

use JardisAdapter\Logger\Contract\LogFormatInterface;

/**
 * Formats log data into Microsoft Teams MessageCard format
 * Supports rich notifications with color-coded levels, facts, and markdown
 *
 * Reference: https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using
 */
class LogTeamsFormat implements LogFormatInterface
{
    /**
     * Format log data into Teams MessageCard JSON format
     *
     * @param array<string|int, mixed> $logData
     * @return string JSON payload
     */
    public function __invoke(array $logData): string
    {
        $level = $logData['level'] ?? 'info';
        $message = $logData['message'] ?? '';
        $context = $logData['context'] ?? '';

        $color = $this->getLevelColor($level);
        $title = $this->getLevelTitle($level);

        // Build MessageCard payload
        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => substr($message, 0, 80), // Summary for notifications
            'themeColor' => $color,
            'title' => $title,
            'sections' => [
                [
                    'activityTitle' => $message,
                    'activitySubtitle' => $context !== '' ? "Context: {$context}" : null,
                    'facts' => $this->buildFacts($logData),
                ],
            ],
        ];

        // Remove null values to clean up JSON
        $payload['sections'][0] = array_filter($payload['sections'][0], fn($v) => $v !== null);

        // Add markdown support if facts exist
        if (!empty($payload['sections'][0]['facts'])) {
            $payload['sections'][0]['markdown'] = true;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * Build facts array for MessageCard
     *
     * @param array<string|int, mixed> $data
     * @return array<int, array<string, string>>
     */
    private function buildFacts(array $data): array
    {
        $facts = [];

        // Add level
        if (isset($data['level'])) {
            $facts[] = [
                'name' => 'Level',
                'value' => strtoupper($data['level']),
            ];
        }

        // Add context
        if (isset($data['context']) && $data['context'] !== '') {
            $facts[] = [
                'name' => 'Context',
                'value' => $data['context'],
            ];
        }

        // Add timestamp
        if (isset($data['timestamp'])) {
            $facts[] = [
                'name' => 'Timestamp',
                'value' => $data['timestamp'],
            ];
        }

        // Add custom data as facts
        if (isset($data['data']) && is_array($data['data']) && !empty($data['data'])) {
            // Limit number of facts to prevent overly large cards
            $customData = array_slice($data['data'], 0, 5, true);

            foreach ($customData as $key => $value) {
                $facts[] = [
                    'name' => ucfirst((string) $key),
                    'value' => $this->formatValue($value),
                ];
            }

            // Add indicator if there's more data
            if (count($data['data']) > 5) {
                $remaining = count($data['data']) - 5;
                $facts[] = [
                    'name' => 'Additional Fields',
                    'value' => "+{$remaining} more...",
                ];
            }
        }

        return $facts;
    }

    /**
     * Format a value for display in Teams
     *
     * @param mixed $value
     */
    private function formatValue($value): string
    {
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                // Truncate very long JSON
                return strlen($json) > 100 ? substr($json, 0, 97) . '...' : $json;
            }
            return '[complex data]';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        $stringValue = (string) $value;
        // Truncate very long strings
        return strlen($stringValue) > 100 ? substr($stringValue, 0, 97) . '...' : $stringValue;
    }

    /**
     * Get color for log level (hex format for Teams)
     */
    private function getLevelColor(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical' => 'FF0000', // Red
            'error' => 'DC3545',                           // Dark red
            'warning' => 'FFC107',                         // Yellow/Orange
            'notice' => '17A2B8',                          // Cyan
            'info' => '007BFF',                            // Blue
            'debug' => '6C757D',                           // Gray
            default => 'CCCCCC',                           // Light gray
        };
    }

    /**
     * Get title for log level
     */
    private function getLevelTitle(string $level): string
    {
        return match (strtolower($level)) {
            'emergency' => '🚨 Emergency',
            'alert' => '🔴 Alert',
            'critical' => '❌ Critical',
            'error' => '❗ Error',
            'warning' => '⚠️ Warning',
            'notice' => 'ℹ️ Notice',
            'info' => '💬 Information',
            'debug' => '🐛 Debug',
            default => '📝 Log Entry',
        };
    }
}
