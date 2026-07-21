<?php

declare(strict_types=1);

namespace JardisAdapter\Logger;

use Exception;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Immutable Logger implementing PSR-3 LoggerInterface.
 *
 * Configured via LoggerBuilder — no mutation after construction.
 * Use LoggerBuilder to create instances with handlers and error handlers.
 */
class Logger implements LoggerInterface
{
    /** @var array<string, LogCommandInterface> $logCommand */
    private array $logCommand;
    /** @var array<string, string> Maps handler names to handler IDs */
    private array $handlerNameMap;
    /** @var callable|null */
    private $errorHandler;

    /**
     * @param array<string, LogCommandInterface> $handlers Pre-configured handlers keyed by handler ID
     * @param array<string, string> $handlerNameMap Maps handler names to handler IDs
     * @param callable|null $errorHandler Error handler for handler exceptions
     */
    public function __construct(
        array $handlers = [],
        array $handlerNameMap = [],
        ?callable $errorHandler = null
    ) {
        $this->logCommand = $handlers;
        $this->handlerNameMap = $handlerNameMap;
        $this->errorHandler = $errorHandler;
    }

    public function debug(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::DEBUG, $message, $context);
    }

    public function info(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::INFO, $message, $context);
    }

    public function notice(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::NOTICE, $message, $context);
    }

    public function warning(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::WARNING, $message, $context);
    }

    public function error(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::ERROR, $message, $context);
    }

    public function critical(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::CRITICAL, $message, $context);
    }

    public function alert(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::ALERT, $message, $context);
    }

    public function emergency(\Stringable|string $message, array $context = []): void
    {
        $this->log(PsrLogLevel::EMERGENCY, $message, $context);
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        if (empty($this->logCommand)) {
            return;
        }

        $messageString = (string) $message;

        foreach ($this->logCommand as $handlerId => $logCommand) {
            try {
                $logCommand($level, $messageString, $context);
            } catch (Exception $e) {
                if ($this->errorHandler) {
                    ($this->errorHandler)($e, $handlerId, $level, $messageString, $context);
                }
                // Continue with other handlers even if one fails
            }
        }
    }

    /**
     * Retrieves a handler by its name.
     *
     * @param string $name The name of the handler to retrieve.
     * @return LogCommandInterface|null The handler instance, or null if not found.
     */
    public function getHandler(string $name): ?LogCommandInterface
    {
        $handlerId = $this->handlerNameMap[$name] ?? null;
        if ($handlerId !== null) {
            return $this->logCommand[$handlerId] ?? null;
        }

        return null;
    }

    /**
     * Returns all registered handlers.
     *
     * @return array<string, LogCommandInterface> Array of handlers keyed by handler ID.
     */
    public function getHandlers(): array
    {
        return $this->logCommand;
    }

    /**
     * Returns all handlers of a specific class type.
     *
     * @param string $className The fully qualified class name to filter by.
     * @return array<string, LogCommandInterface> Array of handlers of the specified type.
     */
    public function getHandlersByClass(string $className): array
    {
        return array_filter(
            $this->logCommand,
            fn($handler) => $handler instanceof $className
        );
    }
}
