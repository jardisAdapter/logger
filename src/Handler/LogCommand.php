<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Builder\LogData;
use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Data\LogLevel;
use JardisAdapter\Logger\Formatter\LogLineFormat;

/**
 * Handles the formatting and logging of messages with various levels of severity.
 * Implements the StreamableLogCommandInterface and provides functionality for setting log data,
 * formatting, streams, and determining responsibility for logging a given level.
 */
class LogCommand implements StreamableLogCommandInterface
{
    private string $context;
    private int $logLevel;
    private LogData $logData;
    private LogFormatInterface $logFormat;
    /** @var resource|null */
    private $stream = null;
    private bool $ownsStream = false;
    private string $handlerId;
    private ?string $handlerName = null;

    public function __construct(string $logLevel)
    {
        $this->logLevel = LogLevel::COLLECTION[strtolower($logLevel)] ?? 4;
        $this->context = '';
        $this->handlerId = uniqid('handler_', true);
    }

    public function __invoke(string $level, string $message, ?array $data = []): ?string
    {
        if ($this->isResponsible($level)) {
            $logData = $this->logData()($this->context, $level, $message, $data);
            // Format for return value (tests expect formatted string)
            $formatted = $this->format()->__invoke($logData);
            // Pass raw data to handler
            return $this->log($logData) ? $formatted : null;
        }

        return null;
    }

    /**
     * Logs raw data. Handler decides if/how to format.
     *
     * @param array<string|int, mixed> $logData Log data to be written.
     * @return bool Success status
     */
    protected function log(array $logData): bool
    {
        if ($this->stream()) {
            $formatted = $this->format()->__invoke($logData);
            return (bool) fwrite($this->stream(), $formatted);
        }

        return false;
    }

    public function logData(): LogData
    {
        return $this->logData = $this->logData ?? new LogData();
    }

    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function setLogData(LogData $logData): self
    {
        $this->logData = $logData;

        return $this;
    }

    public function setFormat(LogFormatInterface $logFormat): self
    {
        $this->logFormat = $logFormat;

        return $this;
    }

    /**
     * @param resource $stream
     * @param bool $ownsStream Whether this command owns the stream and should close it on destruction.
     */
    public function setStream($stream = null, bool $ownsStream = false): self
    {
        $this->stream = $stream;
        $this->ownsStream = $ownsStream;

        return $this;
    }

    protected function context(): string
    {
        return $this->context;
    }

    protected function format(): LogFormatInterface
    {
        return $this->logFormat = $this->logFormat ?? new LogLineFormat();
    }

    /** @return  resource|null */
    protected function stream()
    {
        return $this->stream;
    }

    protected function isResponsible(string $level): bool
    {
        return $this->logLevel <= LogLevel::COLLECTION[strtolower($level)];
    }

    protected function loglevel(): string
    {
        $level = array_search($this->logLevel, LogLevel::COLLECTION);

        return is_string($level) ? $level : '';
    }

    public function __destruct()
    {
        $this->closeStream();
    }

    protected function closeStream(): void
    {
        // Only close if we own the stream and it's not STDOUT/STDERR
        if ($this->stream && is_resource($this->stream) && $this->ownsStream) {
            if (!in_array($this->stream, [STDOUT, STDERR], true)) {
                fclose($this->stream);
            }
        }
    }

    public function getHandlerId(): string
    {
        return $this->handlerId;
    }

    public function setHandlerName(?string $name): self
    {
        $this->handlerName = $name;

        return $this;
    }

    public function getHandlerName(): ?string
    {
        return $this->handlerName;
    }
}
