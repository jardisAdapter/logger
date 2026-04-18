<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Contract;

/**
 * Interface for log commands that support stream-based output.
 * Extends LogCommandInterface to provide stream handling capabilities.
 */
interface StreamableLogCommandInterface extends LogCommandInterface
{
    /**
     * Sets the output stream for the log command.
     *
     * @param resource $stream The stream resource to write log output to.
     * @return self Returns the current instance for method chaining.
     */
    public function setStream($stream): self;
}
