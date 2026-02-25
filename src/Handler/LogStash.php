<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Formatter\LogJsonFormat;

/**
 * Returns log entries to logStash
 */
class LogStash extends LogCommand
{
    private string $logStashHost;
    private int $logStashPort;
    /** @var string[] */
    private array $bindTo;

    /**
     * Constructor for the logger.
     *
     * @param string $logLevel The logging level (e.g., DEBUG, INFO, WARNING, ERROR).
     * @param string $logStashHost The hostname or IP address of the Logstash server.
     * @param int $logStashPort The port number for the Logstash server.
     * @param array<string, string>|null $bindTo Optional binding configuration for network interfaces.
     * @return void
     */
    public function __construct(string $logLevel, string $logStashHost, int $logStashPort, ?array $bindTo = null)
    {
        $this->logStashHost = $logStashHost;
        $this->logStashPort = $logStashPort;
        $this->bindTo = $bindTo ?? ['bindto' => '0:0'];
        $this->setFormat(new LogJsonFormat());

        parent::__construct($logLevel);
    }

    protected function log(array $logData): bool
    {
        $isConnected = true;
        if (!is_resource($this->stream())) {
            $isConnected = $this->connect();
        }

        return $isConnected && parent::log($logData);
    }

    protected function connect(): bool
    {
        $context = stream_context_create(['socket' => $this->bindTo]);

        $stream = stream_socket_client(
            "tcp://{$this->logStashHost}:{$this->logStashPort}",
            $errorNumber,
            $errorString,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        $stream = is_resource($stream) ? $stream : null;
        $this->setStream($stream);

        return true;
    }
}
