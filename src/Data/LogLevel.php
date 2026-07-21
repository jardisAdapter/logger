<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Data;

use Psr\Log\LogLevel as PsrLogLevel;

/**
 * Represents a collection of log levels with their associated numerical severity values.
 * Extends the standardized log levels defined in \Psr\Log\LogLevel.
 */
class LogLevel
{
    public const COLLECTION = [
        PsrLogLevel::EMERGENCY => 7,
        PsrLogLevel::ALERT => 6,
        PsrLogLevel::CRITICAL => 5,
        PsrLogLevel::ERROR => 4,
        PsrLogLevel::WARNING => 3,
        PsrLogLevel::NOTICE => 2,
        PsrLogLevel::INFO => 1,
        PsrLogLevel::DEBUG => 0
    ];
}
