<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogFile;
use JardisAdapter\Logger\Handler\LogSampling;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogSamplingTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/test_sampling_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    private function countLinesInFile(string $pattern = null): int
    {
        if (!file_exists($this->tempFile)) {
            return 0;
        }

        $content = file_get_contents($this->tempFile);
        if ($pattern === null) {
            return substr_count($content, "\n");
        }

        return substr_count($content, $pattern);
    }
    public function testRateSamplingAllowsConfiguredMessagesPerSecond(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'rate', ['rate' => 3]);

        // Log 4 messages
        $logger(LogLevel::INFO, 'Message 1');
        $logger(LogLevel::INFO, 'Message 2');
        $logger(LogLevel::INFO, 'Message 3');
        $logger(LogLevel::INFO, 'Message 4'); // Should be dropped

        // Only first 3 should be in file
        $this->assertEquals(3, $this->countLinesInFile('Message'));
    }

    public function testRateSamplingResetsEverySecond(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'rate', ['rate' => 2]);

        // Fill up current second
        $logger(LogLevel::INFO, 'Message 1');
        $logger(LogLevel::INFO, 'Message 2');
        $logger(LogLevel::INFO, 'Message 3'); // Dropped

        $this->assertEquals(2, $this->countLinesInFile('Message'));

        // Wait for next second
        sleep(1);

        // Should allow messages again
        $logger(LogLevel::INFO, 'Message 4');
        $this->assertEquals(3, $this->countLinesInFile('Message'));
    }

    public function testPercentageSamplingSamplesApproximatelyCorrectAmount(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'percentage', ['percentage' => 50]);

        for ($i = 0; $i < 1000; $i++) {
            $logger(LogLevel::INFO, "Msg$i");
        }

        $count = $this->countLinesInFile('Msg');

        // With 50% sampling and 1000 messages, expect roughly 400-600 to pass (40-60%)
        $this->assertGreaterThan(400, $count);
        $this->assertLessThan(600, $count);
    }

    public function testSmartSamplingAlwaysLogsErrors(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'smart', [
            'alwaysLogLevels' => ['error', 'critical', 'alert', 'emergency'],
            'samplePercentage' => 0, // 0% sampling for non-error levels
        ]);

        // Errors should always pass
        $logger(LogLevel::ERROR, 'Error message');
        $logger(LogLevel::CRITICAL, 'Critical message');
        $logger(LogLevel::ALERT, 'Alert message');

        // Info/Debug should be dropped (0% sampling)
        for ($i = 0; $i < 100; $i++) {
            $logger(LogLevel::INFO, "Info $i");
            $logger(LogLevel::DEBUG, "Debug $i");
        }

        // Should only have 3 error-level logs
        $this->assertEquals(1, $this->countLinesInFile('Error message'));
        $this->assertEquals(1, $this->countLinesInFile('Critical message'));
        $this->assertEquals(1, $this->countLinesInFile('Alert message'));
        $this->assertEquals(0, $this->countLinesInFile('Info'));
        $this->assertEquals(0, $this->countLinesInFile('Debug'));
    }

    public function testSmartSamplingSamplesInfoLogs(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'smart', [
            'alwaysLogLevels' => ['error'],
            'samplePercentage' => 50,
        ]);

        for ($i = 0; $i < 1000; $i++) {
            $logger(LogLevel::INFO, "InfoMsg$i");
        }

        $count = $this->countLinesInFile('InfoMsg');

        // Should sample roughly 50% of info logs
        $this->assertGreaterThan(400, $count);
        $this->assertLessThan(600, $count);
    }

    public function testFingerprintSamplingDeduplicatesIdenticalMessages(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'fingerprint', ['window' => 60]);

        // Log same message 5 times
        $logger(LogLevel::INFO, 'Repeated message');
        $logger(LogLevel::INFO, 'Repeated message');
        $logger(LogLevel::INFO, 'Repeated message');
        $logger(LogLevel::INFO, 'Repeated message');
        $logger(LogLevel::INFO, 'Repeated message');

        // Different message
        $logger(LogLevel::INFO, 'Different message');

        // Only first occurrence + different message should be logged
        $this->assertEquals(1, $this->countLinesInFile('Repeated message'));
        $this->assertEquals(1, $this->countLinesInFile('Different message'));
    }

    public function testFingerprintSamplingCleansUpOldFingerprints(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'fingerprint', ['window' => 1]);

        // Log a message
        $logger(LogLevel::INFO, 'Message 1');
        $this->assertEquals(1, $this->countLinesInFile('Message 1'));

        // Wait for window to expire
        sleep(2);

        // Same message should pass again after window expiration
        $logger(LogLevel::INFO, 'Message 1');
        $this->assertEquals(2, $this->countLinesInFile('Message 1'));
    }

    public function testSetContextPropagatesToWrappedHandler(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'percentage', ['percentage' => 100]);

        $logger->setContext('TestContext');
        $logger(LogLevel::INFO, 'Test message');

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('TestContext', $content);
    }

    public function testGetStatisticsReturnsCorrectData(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'rate', ['rate' => 10]);

        $stats = $logger->getStatistics();

        $this->assertIsArray($stats);
        $this->assertEquals('rate', $stats['strategy']);
        $this->assertEquals(10, $stats['config']['rate']);
        $this->assertArrayHasKey('fingerprints_tracked', $stats);
        $this->assertArrayHasKey('current_second_count', $stats);
    }

    public function testDefaultSmartConfigurationAlwaysLogsHighSeverity(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'smart'); // Default config

        // Default should always log error, critical, alert, emergency
        $logger(LogLevel::ERROR, 'Error');
        $logger(LogLevel::EMERGENCY, 'Emergency');

        $this->assertEquals(1, $this->countLinesInFile('Error'));
        $this->assertEquals(1, $this->countLinesInFile('Emergency'));
    }

    public function testDefaultRateConfiguration(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'rate'); // Default config

        $stats = $logger->getStatistics();

        // Default rate should be 100 per second
        $this->assertEquals(100, $stats['config']['rate']);
    }

    public function testFingerprintDifferentiatesByLogLevel(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $logger = new LogSampling($handler, 'fingerprint', ['window' => 60]);

        // Same message, different levels should be treated as different
        $logger(LogLevel::INFO, 'Message');
        $logger(LogLevel::ERROR, 'Message');

        // Both should be logged (different fingerprints due to level)
        $this->assertEquals(1, $this->countLinesInFile('"level": "info"'));
        $this->assertEquals(1, $this->countLinesInFile('"level": "error"'));
    }
}
