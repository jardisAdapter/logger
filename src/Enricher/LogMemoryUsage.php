<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Enricher;

use JardisAdapter\Logger\Contract\LogEnricherInterface;

/**
 * A class that logs the current memory usage of the script.
 *
 * This class is designed to be invoked directly to retrieve the memory usage
 * in megabytes. It utilizes PHP's `memory_get_usage` function to calculate
 * the memory consumed at the time of invocation.
 */
class LogMemoryUsage implements LogEnricherInterface
{
    /**
     * Invoke method to retrieve memory usage statistics.
     *
     * @return string An array containing the memory usage in megabytes followed by raw value in bytes.
     */
    public function __invoke(): string
    {
        $usage = memory_get_usage();

        return number_format($usage / 1024 / 1024, 2) . " MB** (" . $usage . " Bytes).";
    }
}
