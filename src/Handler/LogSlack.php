<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Formatter\LogSlackFormat;

/**
 * Sends log entries to Slack channels via Incoming Webhooks
 * Uses Slack message format with emoji, attachments, and structured fields
 *
 * Reference: https://api.slack.com/messaging/webhooks
 */
class LogSlack extends LogCommand
{
    private string $webhookUrl;
    private int $timeout;
    private int $retryAttempts;
    private ?LogSlackFormat $formatter = null;
    private ?HttpTransport $transport = null;

    /**
     * Constructor to initialize Slack logging.
     *
     * @param string $logLevel The level of logging, e.g., 'error', 'warning', 'info'.
     * @param string $webhookUrl The Slack webhook URL where log messages will be sent.
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

    private function getFormatter(): LogSlackFormat
    {
        return $this->formatter ??= new LogSlackFormat();
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
}
