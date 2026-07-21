<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Formatter;

use DateTime;
use InvalidArgumentException;
use JardisAdapter\Logger\Contract\LogFormatInterface;

/**
 * Provides a human-readable format for log data.
 * Implements the LogFormatInterface.
 */
class LogHumanFormat implements LogFormatInterface
{
    /**
     * Handles the invocation of the class, processing the provided log data and formatting it into a readable string.
     *
     * @param array<string, mixed> $logData An array containing log information, where keys are the field names,
     * and values are the corresponding data. The values must be scalar to be processed and included in the output.
     * @return string Returns a formatted string containing the processed log information.
     * @throws InvalidArgumentException Throws an exception if the provided log data is invalid or an error occurs
     * during processing.
     */
    public function __invoke(array $logData): string
    {
        $result = "Logging information : \n";
        try {
            foreach ($logData as $key => $value) {
                $key = strtoupper($key);
                if (is_scalar($value)) {
                    $result .= "$key : $value\n";
                } elseif (is_array($value)) {
                    $result .= $key . ': ' . json_encode($value) . "\n";
                } elseif (is_callable($value)) {
                    $result .= $key . ': ' . $value() . "\n";
                } elseif ($value instanceof DateTime) {
                    $result .= $key . ': ' . $value->format('Y-m-d H:i:s') . "\n";
                }
            }
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid log data provided.', 0, $e);
        }

        return $result;
    }
}
