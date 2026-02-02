<?php

declare(strict_types=1);

namespace JardisAdapter\Logger;

use Exception;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Logger class provides a flexible and extensible interface for logging messages
 * at various levels of severity. It implements the LoggerInterface and supports
 * adding multiple log command handlers.
 *
 * Fluent API methods (addFile, addSlack, etc.) are provided via LoggerBuilderTrait.
 */
class Logger implements LoggerInterface
{
    use Builder\LoggerBuilderTrait;

    private string $context;
    /** @var array<string, LogCommandInterface> $logCommand */
    private array $logCommand;
    /** @var array<string, string> Maps handler names to handler IDs */
    private array $handlerNameMap;
    /** @var callable|null */
    private $errorHandler = null;

    public function __construct(string $context)
    {
        $this->context = $context;
        $this->logCommand = [];
        $this->handlerNameMap = [];
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
     * Adds a log command handler to the current log command interface.
     * Multiple instances of the same handler class can now be added.
     * Each handler gets a unique ID automatically.
     *
     * @param LogCommandInterface $logCommand The logging handler to be added.
     * @return self Returns the current instance for method chaining.
     */
    public function addHandler(LogCommandInterface $logCommand): self
    {
        $handlerId = $logCommand->getHandlerId();
        $logCommand->setContext($this->context);
        $this->logCommand[$handlerId] = $logCommand;

        // Register name mapping if handler has a name
        $handlerName = $logCommand->getHandlerName();
        if ($handlerName !== null) {
            $this->handlerNameMap[$handlerName] = $handlerId;
        }

        return $this;
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
     * Removes a handler by its name or handler ID.
     *
     * @param string $nameOrId The name or handler ID to remove.
     * @return bool True if the handler was removed, false otherwise.
     */
    public function removeHandler(string $nameOrId): bool
    {
        // Try as name first
        $handlerId = $this->handlerNameMap[$nameOrId] ?? null;

        // If not found as name, try as handler ID
        if ($handlerId === null && isset($this->logCommand[$nameOrId])) {
            $handlerId = $nameOrId;
        }

        if ($handlerId !== null && isset($this->logCommand[$handlerId])) {
            // Remove from name map if it has a name
            $handler = $this->logCommand[$handlerId];
            $handlerName = $handler->getHandlerName();
            if ($handlerName !== null && isset($this->handlerNameMap[$handlerName])) {
                unset($this->handlerNameMap[$handlerName]);
            }

            unset($this->logCommand[$handlerId]);

            return true;
        }

        return false;
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

    /**
     * Sets an error handler to be called when a log handler throws an exception.
     *
     * @param callable $errorHandler Callback with signature:
     *                               function(Exception $e, string $handlerId,
     *                               string $level, string $message, array $context): void
     * @return self Returns the current instance for method chaining.
     */
    public function setErrorHandler(callable $errorHandler): self
    {
        $this->errorHandler = $errorHandler;

        return $this;
    }
}
