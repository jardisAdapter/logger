<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Formatter;

use JardisAdapter\Logger\Contract\LogFormatInterface;

/**
 * Formats log data into Slack message format with attachments
 * Supports emoji, color-coded levels, and structured data display
 *
 * Reference: https://api.slack.com/messaging/webhooks
 */
class LogSlackFormat implements LogFormatInterface
{
    /**
     * Format log data into Slack webhook JSON format
     *
     * @param array<string|int, mixed> $logData
     * @return string JSON payload
     */
    public function __invoke(array $logData): string
    {
        $message = $logData['message'] ?? '';
        $level = $logData['level'] ?? 'info';

        $emoji = $this->getLevelEmoji($level);
        $text = "{$emoji} {$message}";

        $payload = [
            'text' => $text,
        ];

        // Add context and data as attachment if present
        if (isset($logData['context']) || isset($logData['data'])) {
            $fields = [];

            if (isset($logData['context'])) {
                $fields[] = [
                    'title' => 'Context',
                    'value' => $logData['context'],
                    'short' => true,
                ];
            }

            if (isset($logData['level'])) {
                $fields[] = [
                    'title' => 'Level',
                    'value' => strtoupper($logData['level']),
                    'short' => true,
                ];
            }

            if (!empty($logData['data']) && is_array($logData['data'])) {
                $fields[] = [
                    'title' => 'Data',
                    'value' => '```' . json_encode($logData['data'], JSON_PRETTY_PRINT) . '```',
                    'short' => false,
                ];
            }

            $payload['attachments'] = [
                [
                    'color' => $this->getLevelColor($level),
                    'fields' => $fields,
                    'footer' => 'JardisCore Logger',
                    'ts' => time(),
                ],
            ];
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * Get emoji for log level
     */
    private function getLevelEmoji(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical' => ':rotating_light:',
            'error' => ':x:',
            'warning' => ':warning:',
            'notice' => ':information_source:',
            'info' => ':speech_balloon:',
            'debug' => ':bug:',
            default => ':memo:',
        };
    }

    /**
     * Get color for log level
     */
    private function getLevelColor(string $level): string
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical' => 'danger',
            'error' => '#ff0000',
            'warning' => 'warning',
            'notice', 'info' => '#2196F3',
            'debug' => '#607D8B',
            default => '#cccccc',
        };
    }
}
