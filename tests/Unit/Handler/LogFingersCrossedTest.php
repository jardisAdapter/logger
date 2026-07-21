<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogFile;
use JardisAdapter\Logger\Handler\LogFingersCrossed;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogFingersCrossedTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/test_fingerscrossed_' . uniqid() . '.log';
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

    public function testBuffersLogsUntilActivationLevel(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        // Buffer these - should not write yet
        $fingersCrossed(LogLevel::DEBUG, 'Debug message', []);
        $fingersCrossed(LogLevel::INFO, 'Info message', []);
        $fingersCrossed(LogLevel::WARNING, 'Warning message', []);

        // File should be empty
        $this->assertEquals(0, $this->countLinesInFile());

        // This triggers activation - all 4 messages written
        $fingersCrossed(LogLevel::ERROR, 'Error message', []);

        $this->assertEquals(4, $this->countLinesInFile());
        $this->assertEquals(1, $this->countLinesInFile('Debug message'));
        $this->assertEquals(1, $this->countLinesInFile('Info message'));
        $this->assertEquals(1, $this->countLinesInFile('Warning message'));
        $this->assertEquals(1, $this->countLinesInFile('Error message'));
    }

    public function testStopBufferingAfterActivation(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR, 100, true);

        // Buffer these
        $fingersCrossed(LogLevel::INFO, 'Message 1', []);
        $fingersCrossed(LogLevel::INFO, 'Message 2', []);

        // Activate
        $fingersCrossed(LogLevel::ERROR, 'Error', []);

        // After activation, should write directly (no buffering)
        $fingersCrossed(LogLevel::INFO, 'Message 3', []);
        $fingersCrossed(LogLevel::DEBUG, 'Message 4', []);

        // All 5 messages should be in file
        $this->assertEquals(5, $this->countLinesInFile());
    }

    public function testContinueBufferingAfterActivationWhenConfigured(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR, 100, false);

        // Buffer
        $fingersCrossed(LogLevel::INFO, 'Message 1', []);

        // Activate
        $fingersCrossed(LogLevel::ERROR, 'Error 1', []);

        // After activation, should continue buffering low-level logs
        $fingersCrossed(LogLevel::INFO, 'Message 2', []);
        $fingersCrossed(LogLevel::DEBUG, 'Message 3', []);

        // Only 2 messages in file (Message 1 + Error 1)
        $this->assertEquals(2, $this->countLinesInFile());

        // Second error triggers another flush (Error 2 + buffered Message 2 + Message 3)
        $fingersCrossed(LogLevel::ERROR, 'Error 2', []);

        // Now all 5 messages in file (Message 1, Error 1, Message 2, Message 3, Error 2)
        $this->assertEquals(5, $this->countLinesInFile());
    }

    public function testBufferSizeLimit(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR, 3);

        // Add 5 messages (buffer size is 3)
        $fingersCrossed(LogLevel::INFO, 'Message 1', []);
        $fingersCrossed(LogLevel::INFO, 'Message 2', []);
        $fingersCrossed(LogLevel::INFO, 'Message 3', []);
        $fingersCrossed(LogLevel::INFO, 'Message 4', []);
        $fingersCrossed(LogLevel::INFO, 'Message 5', []);

        // Activate
        $fingersCrossed(LogLevel::ERROR, 'Error', []);

        // Only last 3 buffered messages + error should be written (FIFO)
        $this->assertEquals(4, $this->countLinesInFile());
        $this->assertEquals(0, $this->countLinesInFile('Message 1')); // Dropped
        $this->assertEquals(0, $this->countLinesInFile('Message 2')); // Dropped
        $this->assertEquals(1, $this->countLinesInFile('Message 3'));
        $this->assertEquals(1, $this->countLinesInFile('Message 4'));
        $this->assertEquals(1, $this->countLinesInFile('Message 5'));
        $this->assertEquals(1, $this->countLinesInFile('Error'));
    }

    public function testActivationWithCriticalLevel(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::CRITICAL);

        // These should not activate (including ERROR)
        $fingersCrossed(LogLevel::DEBUG, 'Debug', []);
        $fingersCrossed(LogLevel::ERROR, 'Error', []);
        $fingersCrossed(LogLevel::WARNING, 'Warning', []);

        $this->assertEquals(0, $this->countLinesInFile());

        // This activates (CRITICAL)
        $fingersCrossed(LogLevel::CRITICAL, 'Critical', []);

        $this->assertEquals(4, $this->countLinesInFile());
    }

    public function testManualFlush(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $fingersCrossed(LogLevel::INFO, 'Message 1', []);
        $fingersCrossed(LogLevel::INFO, 'Message 2', []);

        // Manual flush without activation
        $fingersCrossed->flush();

        $this->assertEquals(2, $this->countLinesInFile());

        // Buffer should be empty now
        $stats = $fingersCrossed->getStatistics();
        $this->assertEquals(0, $stats['buffer_size']);
    }

    public function testReset(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $fingersCrossed(LogLevel::INFO, 'Message 1', []);
        $fingersCrossed(LogLevel::ERROR, 'Error', []); // Activates

        $stats = $fingersCrossed->getStatistics();
        $this->assertTrue($stats['is_activated']);

        // Reset
        $fingersCrossed->reset();

        $stats = $fingersCrossed->getStatistics();
        $this->assertFalse($stats['is_activated']);
        $this->assertEquals(0, $stats['buffer_size']);

        // Should buffer again
        $fingersCrossed(LogLevel::INFO, 'Message 2', []);
        $this->assertEquals(2, $this->countLinesInFile()); // Only previous logs
    }

    public function testGetStatistics(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::WARNING, 50, false);

        $fingersCrossed(LogLevel::INFO, 'Test', []);
        $fingersCrossed(LogLevel::DEBUG, 'Test', []);

        $stats = $fingersCrossed->getStatistics();

        $this->assertEquals(2, $stats['buffer_size']);
        $this->assertEquals(50, $stats['buffer_capacity']);
        $this->assertFalse($stats['is_activated']);
        $this->assertEquals('warning', $stats['activation_level']);
        $this->assertFalse($stats['stop_buffering_after_activation']);
    }

    public function testEmergencyLevelActivates(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $fingersCrossed(LogLevel::INFO, 'Info', []);
        $fingersCrossed(LogLevel::EMERGENCY, 'Emergency', []);

        // EMERGENCY is higher than ERROR, should activate
        $this->assertEquals(2, $this->countLinesInFile());
    }

    public function testContextPropagation(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $fingersCrossed->setContext('TestContext');
        $fingersCrossed(LogLevel::INFO, 'Test message', []);
        $fingersCrossed(LogLevel::ERROR, 'Error', []);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('TestContext', $content);
    }

    public function testSetFormatPropagatesToWrappedHandler(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $jsonFormat = new \JardisAdapter\Logger\Formatter\LogJsonFormat();
        $fingersCrossed->setFormat($jsonFormat);

        $fingersCrossed(LogLevel::INFO, 'Info message', []);
        $fingersCrossed(LogLevel::ERROR, 'Error message', []);

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('{', $content);
        $this->assertStringContainsString('"message":', $content);
    }

    public function testSetStreamPropagatesToWrappedHandler(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $stream = fopen($this->tempFile, 'a');
        $fingersCrossed->setStream($stream);

        $this->assertIsResource($stream);
        fclose($stream);
    }

    public function testGetHandlerId(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $handlerId = $fingersCrossed->getHandlerId();

        $this->assertIsString($handlerId);
        $this->assertStringStartsWith('handler_', $handlerId);
    }

    public function testSetAndGetHandlerName(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR);

        $this->assertNull($fingersCrossed->getHandlerName());

        $fingersCrossed->setHandlerName('buffered_handler');
        $this->assertEquals('buffered_handler', $fingersCrossed->getHandlerName());

        $fingersCrossed->setHandlerName(null);
        $this->assertNull($fingersCrossed->getHandlerName());
    }

    public function testBufferSizeMinimumIsOne(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile);

        // Try to create with buffer size 0 or negative
        $fingersCrossed = new LogFingersCrossed($handler, LogLevel::ERROR, 0);

        $stats = $fingersCrossed->getStatistics();
        $this->assertGreaterThanOrEqual(1, $stats['buffer_capacity']);
    }
}
