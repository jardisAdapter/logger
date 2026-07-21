<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

/**
 * Class LogNull is a subclass of LogCommand that provides a logging
 * implementation that essentially discards any log entries it receives.
 * This can be useful for scenarios where logging is disabled or not required,
 * and the log messages need to be harmlessly ignored.
 */
class LogNull extends LogCommand
{
    public function __invoke(string $level, string $message, ?array $data = []): ?string
    {
        return null;
    }
}
