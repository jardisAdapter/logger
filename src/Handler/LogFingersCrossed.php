<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Data\LogLevel;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Buffers log messages and only writes them when an activation threshold is reached.
 * Perfect for debugging: collect DEBUG/INFO logs, discard them unless ERROR occurs.
 *
 * Strategy:
 * - Buffers all logs below activation level
 * - Once activation level is reached, flushes entire buffer + continues logging
 * - After flush, optionally stops buffering (pass-through mode)
 *
 * Use Cases:
 * - Development: See full context (DEBUG logs) only when errors occur
 * - Production: Reduce log volume while maintaining debugging capability
 * - Cost optimization: Write detailed logs only when needed
 *
 * Usage:
 * ```php
 * $fileHandler = new LogFile(LogLevel::DEBUG, '/var/log/app.log');
 * $fingersCrossed = new LogFingersCrossed(
 *     $fileHandler,
 *     LogLevel::ERROR,  // Activate on ERROR or above
 *     100,              // Buffer size
 *     true              // Stop buffering after activation
 * );
 * $logger->addHandler($fingersCrossed);
 *
 * // These are buffered
 * $logger->debug('Starting process');
 * $logger->info('Processing item 1');
 * $logger->info('Processing item 2');
 *
 * // This triggers flush - all 4 messages written
 * $logger->error('Failed to process item 3');
 * ```
 */
class LogFingersCrossed implements StreamableLogCommandInterface
{
    private StreamableLogCommandInterface $wrappedHandler;
    private int $activationLevel;
    private int $bufferSize;
    private bool $stopBufferingAfterActivation;
    private bool $isActivated = false;
    /** @var array<int, array{level: string, message: string, data: array<string, mixed>|null}> */
    private array $buffer = [];
    private string $handlerId;
    private ?string $handlerName = null;

    /**
     * @param StreamableLogCommandInterface $wrappedHandler Handler to use when activated
     * @param string $activationLevel Log level that triggers buffer flush (default: ERROR)
     * @param int $bufferSize Maximum buffer size (default: 100 messages)
     * @param bool $stopBufferingAfterActivation If true, switches to pass-through after first activation
     */
    public function __construct(
        StreamableLogCommandInterface $wrappedHandler,
        string $activationLevel = PsrLogLevel::ERROR,
        int $bufferSize = 100,
        bool $stopBufferingAfterActivation = true
    ) {
        $this->wrappedHandler = $wrappedHandler;
        $this->activationLevel = LogLevel::COLLECTION[strtolower($activationLevel)] ?? 4;
        $this->bufferSize = max(1, $bufferSize);
        $this->stopBufferingAfterActivation = $stopBufferingAfterActivation;
        $this->handlerId = uniqid('handler_', true);
    }

    /**
     * @return string|null
     */
    public function __invoke(string $level, string $message, ?array $data = []): ?string
    {
        $levelValue = LogLevel::COLLECTION[strtolower($level)] ?? 4;

        // If already activated and stop-buffering is enabled, pass through directly
        if ($this->isActivated && $this->stopBufferingAfterActivation) {
            $result = ($this->wrappedHandler)($level, $message, $data);
            return is_string($result) ? $result : null;
        }

        // Check if this message activates the handler
        if ($levelValue >= $this->activationLevel) {
            return $this->activate($level, $message, $data);
        }

        // Buffer the message
        $this->addToBuffer($level, $message, $data);

        return null;
    }

    /**
     * Activates handler: flushes buffer and processes current message.
     *
     * @param array<string, mixed>|null $data
     * @return string|null
     */
    private function activate(string $level, string $message, ?array $data): ?string
    {
        $this->isActivated = true;

        // Flush buffer first
        foreach ($this->buffer as $bufferedLog) {
            ($this->wrappedHandler)(
                $bufferedLog['level'],
                $bufferedLog['message'],
                $bufferedLog['data']
            );
        }

        // Clear buffer after flush
        $this->buffer = [];

        // Process current message
        $result = ($this->wrappedHandler)($level, $message, $data);
        return is_string($result) ? $result : null;
    }

    /**
     * Adds message to buffer, respecting buffer size limit.
     *
     * @param array<string, mixed>|null $data
     */
    private function addToBuffer(string $level, string $message, ?array $data): void
    {
        // If buffer is full, remove oldest entry (FIFO)
        if (count($this->buffer) >= $this->bufferSize) {
            array_shift($this->buffer);
        }

        $this->buffer[] = [
            'level' => $level,
            'message' => $message,
            'data' => $data,
        ];
    }

    public function setContext(string $context): self
    {
        $this->wrappedHandler->setContext($context);
        return $this;
    }

    public function setFormat(LogFormatInterface $logFormat): self
    {
        $this->wrappedHandler->setFormat($logFormat);
        return $this;
    }

    /**
     * @param resource $stream
     */
    public function setStream($stream): self
    {
        $this->wrappedHandler->setStream($stream);
        return $this;
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

    /**
     * Manually flush buffer without activation.
     * Useful for cleanup or forcing buffer write.
     */
    public function flush(): void
    {
        foreach ($this->buffer as $bufferedLog) {
            ($this->wrappedHandler)(
                $bufferedLog['level'],
                $bufferedLog['message'],
                $bufferedLog['data']
            );
        }

        $this->buffer = [];
    }

    /**
     * Reset handler state (mainly for testing).
     */
    public function reset(): void
    {
        $this->isActivated = false;
        $this->buffer = [];
    }

    /**
     * Get statistics about buffering.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'buffer_size' => count($this->buffer),
            'buffer_capacity' => $this->bufferSize,
            'is_activated' => $this->isActivated,
            'activation_level' => array_search($this->activationLevel, LogLevel::COLLECTION),
            'stop_buffering_after_activation' => $this->stopBufferingAfterActivation,
        ];
    }
}
