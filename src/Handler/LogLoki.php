<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Formatter\LogLokiFormat;
use JardisAdapter\Logger\Transport\HttpTransport;

/**
 * Sends log entries to Grafana Loki using the Push API
 *
 * Loki is a horizontally-scalable, highly-available log aggregation system
 * inspired by Prometheus. It indexes metadata (labels) rather than full-text,
 * making it extremely efficient for large-scale logging.
 *
 * Reference: https://grafana.com/docs/loki/latest/api/#post-lokiapiv1push
 */
class LogLoki extends LogCommand
{
    private string $lokiUrl;
    /** @var array<string, string> */
    private array $staticLabels;
    private int $timeout;
    private int $retryAttempts;
    private ?LogLokiFormat $formatter = null;
    private ?HttpTransport $transport = null;

    /**
     * Constructor to initialize Loki logging.
     *
     * @param string $logLevel The logging level
     * @param string $lokiUrl The Loki push API endpoint (e.g., http://loki:3100/loki/api/v1/push)
     * @param array<string, string> $staticLabels Static labels to attach to all log entries
     *                                             (e.g., ['app' => 'myapp', 'env' => 'production'])
     * @param int $timeout Request timeout in seconds (default: 10)
     * @param int $retryAttempts Number of retry attempts on failure (default: 3)
     */
    public function __construct(
        string $logLevel,
        string $lokiUrl,
        array $staticLabels = [],
        int $timeout = 10,
        int $retryAttempts = 3
    ) {
        parent::__construct($logLevel);

        $this->lokiUrl = rtrim($lokiUrl, '/');
        $this->staticLabels = $staticLabels;
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
    }

    private function getFormatter(): LogLokiFormat
    {
        return $this->formatter ??= new LogLokiFormat($this->staticLabels);
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

        return $this->getTransport()->send($this->lokiUrl, $payload);
    }

    /**
     * Add a static label to all log entries
     *
     * @param string $key Label key
     * @param string $value Label value
     * @return self
     */
    public function addStaticLabel(string $key, string $value): self
    {
        $this->getFormatter()->addLabel($key, $value);
        return $this;
    }

    /**
     * Get all static labels
     *
     * @return array<string, string>
     */
    public function getStaticLabels(): array
    {
        return $this->getFormatter()->getStaticLabels();
    }

    /**
     * Get Loki URL
     */
    public function getLokiUrl(): string
    {
        return $this->lokiUrl;
    }
}
