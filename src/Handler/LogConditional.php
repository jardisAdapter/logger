<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;

/**
 * Routes logs to different handlers based on runtime conditions.
 * Implements Chain of Responsibility pattern with conditional routing.
 *
 * Supports conditional routing based on:
 * - Log level
 * - Context data (user_id, tenant_id, etc.)
 * - Time-based conditions
 * - Custom callable conditions
 *
 * Usage:
 * ```php
 * $conditional = new LogConditional([
 *     [fn($level) => $level >= LogLevel::ERROR, $slackHandler],
 *     [fn($level, $msg, $ctx) => $ctx['user_id'] === 'admin', $debugHandler],
 * ], $defaultHandler);
 * ```
 */
class LogConditional implements StreamableLogCommandInterface
{
    /**
     * @var array<int, array{0: callable, 1: StreamableLogCommandInterface}>
     */
    private array $conditionalHandlers;

    private ?StreamableLogCommandInterface $fallbackHandler;
    private string $handlerId;
    private ?string $handlerName = null;

    /**
     * @param array<int, array{0: callable, 1: StreamableLogCommandInterface}> $conditionalHandlers
     *        Array of [condition, handler] pairs.
     *        Condition is a callable(string $level, string $message, ?array $data): bool
     * @param StreamableLogCommandInterface|null $fallbackHandler Handler to use if no condition matches
     */
    public function __construct(
        array $conditionalHandlers,
        ?StreamableLogCommandInterface $fallbackHandler = null
    ) {
        $this->conditionalHandlers = $conditionalHandlers;
        $this->fallbackHandler = $fallbackHandler;
        $this->handlerId = uniqid('handler_', true);
    }

    /**
     * @return string|null
     */
    public function __invoke(string $level, string $message, ?array $data = []): ?string
    {
        // Iterate through conditions in order
        foreach ($this->conditionalHandlers as [$condition, $handler]) {
            if ($condition($level, $message, $data)) {
                $result = $handler($level, $message, $data);
                return is_string($result) ? $result : null;
            }
        }

        // No condition matched - use fallback
        if ($this->fallbackHandler) {
            $result = ($this->fallbackHandler)($level, $message, $data);
            return is_string($result) ? $result : null;
        }

        // No fallback - log is dropped
        return null;
    }

    public function setContext(string $context): self
    {
        // Propagate context to all handlers
        foreach ($this->conditionalHandlers as [$condition, $handler]) {
            $handler->setContext($context);
        }

        if ($this->fallbackHandler) {
            $this->fallbackHandler->setContext($context);
        }

        return $this;
    }

    public function setFormat(LogFormatInterface $logFormat): self
    {
        // Propagate format to all handlers
        foreach ($this->conditionalHandlers as [$condition, $handler]) {
            $handler->setFormat($logFormat);
        }

        if ($this->fallbackHandler) {
            $this->fallbackHandler->setFormat($logFormat);
        }

        return $this;
    }

    /**
     * @param resource $stream
     */
    public function setStream($stream): self
    {
        // Propagate stream to all handlers
        foreach ($this->conditionalHandlers as [$condition, $handler]) {
            $handler->setStream($stream);
        }

        if ($this->fallbackHandler) {
            $this->fallbackHandler->setStream($stream);
        }

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
     * Get statistics about conditional routing.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'condition_count' => count($this->conditionalHandlers),
            'has_fallback' => $this->fallbackHandler !== null,
        ];
    }
}
