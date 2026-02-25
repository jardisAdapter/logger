<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Integration;

use JardisAdapter\Logger\Handler\LogFile;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Integration tests for multiple handler scenarios.
 * Tests the ability to register multiple handlers of the same class,
 * which is a key Enterprise requirement (e.g., app.log + error.log).
 */
class MultipleHandlersTest extends TestCase
{
    private string $appLogPath;
    private string $errorLogPath;

    protected function setUp(): void
    {
        $this->appLogPath = sys_get_temp_dir() . '/app_' . uniqid() . '.log';
        $this->errorLogPath = sys_get_temp_dir() . '/error_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->appLogPath)) {
            unlink($this->appLogPath);
        }
        if (file_exists($this->errorLogPath)) {
            unlink($this->errorLogPath);
        }
    }

    public function testMultipleLogFileHandlersWithDifferentLevels(): void
    {
        $logger = new Logger('TestContext');

        // Handler 1: All logs (DEBUG and above) -> app.log
        $appHandler = new LogFile(LogLevel::DEBUG, $this->appLogPath);
        $appHandler->setHandlerName('app_log');

        // Handler 2: Only errors (ERROR and above) -> error.log
        $errorHandler = new LogFile(LogLevel::ERROR, $this->errorLogPath);
        $errorHandler->setHandlerName('error_log');

        $logger->addHandler($appHandler);
        $logger->addHandler($errorHandler);

        // Log messages at different levels
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        $logger->critical('Critical message');

        // Read app.log - should contain all messages
        $appLogContents = file_get_contents($this->appLogPath);
        $this->assertStringContainsString('Debug message', $appLogContents);
        $this->assertStringContainsString('Info message', $appLogContents);
        $this->assertStringContainsString('Warning message', $appLogContents);
        $this->assertStringContainsString('Error message', $appLogContents);
        $this->assertStringContainsString('Critical message', $appLogContents);

        // Read error.log - should contain only error and critical
        $errorLogContents = file_get_contents($this->errorLogPath);
        $this->assertStringNotContainsString('Debug message', $errorLogContents);
        $this->assertStringNotContainsString('Info message', $errorLogContents);
        $this->assertStringNotContainsString('Warning message', $errorLogContents);
        $this->assertStringContainsString('Error message', $errorLogContents);
        $this->assertStringContainsString('Critical message', $errorLogContents);
    }

    public function testMultipleLogFileHandlersSameLevelDifferentFiles(): void
    {
        $logger = new Logger('TestContext');

        // Both handlers at INFO level, but writing to different files
        $handler1 = new LogFile(LogLevel::INFO, $this->appLogPath);
        $handler1->setHandlerName('file1');

        $handler2 = new LogFile(LogLevel::INFO, $this->errorLogPath);
        $handler2->setHandlerName('file2');

        $logger->addHandler($handler1);
        $logger->addHandler($handler2);

        $logger->info('Test message');

        // Both files should contain the same message
        $file1Contents = file_get_contents($this->appLogPath);
        $file2Contents = file_get_contents($this->errorLogPath);

        $this->assertStringContainsString('Test message', $file1Contents);
        $this->assertStringContainsString('Test message', $file2Contents);
    }

    public function testRetrieveAndRemoveNamedHandlers(): void
    {
        $logger = new Logger('TestContext');

        $appHandler = new LogFile(LogLevel::DEBUG, $this->appLogPath);
        $appHandler->setHandlerName('app_log');

        $errorHandler = new LogFile(LogLevel::ERROR, $this->errorLogPath);
        $errorHandler->setHandlerName('error_log');

        $logger->addHandler($appHandler);
        $logger->addHandler($errorHandler);

        // Retrieve handlers by name
        $retrieved = $logger->getHandler('app_log');
        $this->assertSame($appHandler, $retrieved);

        // Remove error handler
        $removed = $logger->removeHandler('error_log');
        $this->assertTrue($removed);

        // Verify it's gone
        $this->assertNull($logger->getHandler('error_log'));

        // app_log should still be there
        $this->assertNotNull($logger->getHandler('app_log'));
    }

    public function testGetHandlersByClassWithMultipleInstances(): void
    {
        $logger = new Logger('TestContext');

        $handler1 = new LogFile(LogLevel::DEBUG, $this->appLogPath);
        $handler2 = new LogFile(LogLevel::ERROR, $this->errorLogPath);

        $logger->addHandler($handler1);
        $logger->addHandler($handler2);

        $fileHandlers = $logger->getHandlersByClass(LogFile::class);

        $this->assertCount(2, $fileHandlers);
        $this->assertContains($handler1, $fileHandlers);
        $this->assertContains($handler2, $fileHandlers);
    }

    public function testEnterpriseScenarioMultipleDestinations(): void
    {
        $logger = new Logger('OrderService');

        // Scenario: Enterprise app with multiple log destinations
        // 1. All logs to main app.log
        $allLogsHandler = new LogFile(LogLevel::DEBUG, $this->appLogPath);
        $allLogsHandler->setHandlerName('all_logs');

        // 2. Only errors to error.log for monitoring
        $errorLogsHandler = new LogFile(LogLevel::ERROR, $this->errorLogPath);
        $errorLogsHandler->setHandlerName('error_logs');

        $logger->addHandler($allLogsHandler);
        $logger->addHandler($errorLogsHandler);

        // Simulate business logic with various log levels
        $logger->debug('Order validation started', ['orderId' => 12345]);
        $logger->info('Order processed successfully', ['orderId' => 12345, 'amount' => 99.99]);
        $logger->error('Payment gateway timeout', ['orderId' => 12345, 'gateway' => 'stripe']);

        // Verify all logs are in app.log
        $appLog = file_get_contents($this->appLogPath);
        $this->assertStringContainsString('Order validation started', $appLog);
        $this->assertStringContainsString('Order processed successfully', $appLog);
        $this->assertStringContainsString('Payment gateway timeout', $appLog);

        // Verify only error is in error.log
        $errorLog = file_get_contents($this->errorLogPath);
        $this->assertStringNotContainsString('Order validation started', $errorLog);
        $this->assertStringNotContainsString('Order processed successfully', $errorLog);
        $this->assertStringContainsString('Payment gateway timeout', $errorLog);
    }
}
