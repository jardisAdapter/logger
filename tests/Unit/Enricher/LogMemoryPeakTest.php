<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Enricher;

use JardisAdapter\Logger\Enricher\LogMemoryPeak;
use PHPUnit\Framework\TestCase;

/**
 * Class LogMemoryPeakTest
 *
 * Unit tests for the LogMemoryPeak class.
 */
class LogMemoryPeakTest extends TestCase
{
    /**
     * Test if __invoke returns a formatted string with memory peak usage.
     */
    public function testInvokeReturnsFormattedMemoryPeakUsage(): void
    {
        $result = (new LogMemoryPeak())();

        // Check if the result is a non-empty string
        $this->assertIsString($result);

        // Ensure the string includes "MB" and "Bytes" as part of the formatted output
        $this->assertStringContainsString('MB', $result, 'Output should contain "MB".');
        $this->assertStringContainsString('Bytes', $result, 'Output should contain "Bytes".');
    }

    /**
     * Test if __invoke handles memory formatting correctly when specific peak values are simulated.
     */
    public function testInvokeHandlesMemoryFormatting(): void
    {
        $result = (new LogMemoryPeak())();

        // Assert the result matches the general format "x.xx MB** (y Bytes)."
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d{2} MB\*\* \(\d+ Bytes\)\.$/',
            $result,
            'Output format should match "x.xx MB** (y Bytes)."'
        );
    }
}
