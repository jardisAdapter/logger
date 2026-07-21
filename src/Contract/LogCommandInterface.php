<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Contract;

interface LogCommandInterface
{
    /**
     * @param string $level
     * @param string $message
     * @param ?array<string, mixed> $data
     * @return string|array<string, mixed>|null
     */
    public function __invoke(string $level, string $message, ?array $data = []);

    public function setContext(string $context): self;

    public function setFormat(LogFormatInterface $logFormat): self;

    /**
     * Returns a unique identifier for this handler instance.
     * This allows multiple instances of the same handler class to be registered.
     *
     * @return string Unique handler identifier
     */
    public function getHandlerId(): string;

    /**
     * Sets an optional name for this handler instance.
     * Named handlers can be retrieved or removed by name from the logger.
     *
     * @param string|null $name Optional human-readable name for the handler
     * @return self Returns the current instance for method chaining
     */
    public function setHandlerName(?string $name): self;

    /**
     * Returns the optional name set for this handler instance.
     *
     * @return string|null The handler name, or null if not set
     */
    public function getHandlerName(): ?string;
}
