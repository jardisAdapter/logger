<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Formatter\LogTeamsFormat;
use JardisAdapter\Logger\Transport\HttpTransport;

/**
 * Sends log entries to Microsoft Teams channels via Incoming Webhooks
 * Uses Teams MessageCard format for rich, formatted notifications
 *
 * Reference: https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/connectors-using
 */
class LogTeams extends LogCommand
{
    private string $webhookUrl;
    private int $timeout;
    private int $retryAttempts;
    private ?LogTeamsFormat $formatter = null;
    private ?HttpTransport $transport = null;

    /**
     * Constructor to initialize Teams logging.
     *
     * @param string $logLevel The level of logging, e.g., 'error', 'warning', 'info'.
     * @param string $webhookUrl The Teams webhook URL where log messages will be sent.
     * @param int $timeout Request timeout in seconds (default: 10)
     * @param int $retryAttempts Number of retry attempts on failure (default: 3)
     */
    public function __construct(
        string $logLevel,
        string $webhookUrl,
        int $timeout = 10,
        int $retryAttempts = 3
    ) {
        parent::__construct($logLevel);

        $this->webhookUrl = $webhookUrl;
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
    }

    private function getFormatter(): LogTeamsFormat
    {
        return $this->formatter ??= new LogTeamsFormat();
    }

    private function getTransport(): HttpTransport
    {
        return $this->transport ??= new HttpTransport(
            'POST',
            ['Content-Type' => 'application/json'],
            $this->timeout,
            $this->retryAttempts
        );
    }

    protected function log(array $logData): bool
    {
        $payload = $this->getFormatter()->__invoke($logData);

        if ($this->stream()) {
            return (bool) fwrite($this->stream(), $payload . "\n");
        }

        return $this->getTransport()->send($this->webhookUrl, $payload);
    }

    /**
     * Get webhook URL
     */
    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    /**
     * Get timeout
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get retry attempts
     */
    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }
}
