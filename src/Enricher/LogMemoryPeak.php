<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Enricher;

use JardisAdapter\Logger\Contract\LogEnricherInterface;

/**
 * Class LogDataMemoryPeak
 *
 * Provides functionality to retrieve and format the memory peak usage during script execution.
 */
class LogMemoryPeak implements LogEnricherInterface
{
    /**
     * Invokes the method to retrieve the peak memory usage of the script.
     *
     * @return string Returns a formatted string displaying the peak memory usage in megabytes and bytes.
     */
    public function __invoke(): string
    {
        $peak = memory_get_peak_usage();

        return number_format($peak / 1024 / 1024, 2) . " MB** (" . $peak . " Bytes).";
    }
}
