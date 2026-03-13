<?php

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogConsole;
use JardisAdapter\Logger\Data\LogData;
use JardisAdapter\Logger\Tests\Helpers\StreamHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogConsoleTest extends TestCase
{
    public function testWriteToStreamSuccess(): void
    {
        $logger = new LogConsole(LogLevel::INFO);
        $result = StreamHelper::invokeLoggerAndGetContent($logger, LogLevel::INFO, 'message', ['key' => 'value']);

        $this->assertStringContainsString(
            '"context": "", "level": "info", "message": "message", "data": "{"key":"value"}"',
            $result
        );
    }

    public function testWriteContextToStreamSuccess(): void
    {
        $logger = new LogConsole(LogLevel::INFO);
        $logger->setContext('TestContext');

        $result = StreamHelper::invokeLoggerAndGetContent($logger, LogLevel::INFO, 'message', ['key' => 'value']);

        $this->assertStringContainsString(
            '"context": "TestContext", "level": "info", "message": "message", "data": "{"key":"value"}"',
            $result
        );
    }

    public function testNotWriteLevel(): void
    {
        $logger = new LogConsole(LogLevel::ERROR);
        $result = StreamHelper::invokeLoggerAndGetContent($logger, LogLevel::WARNING, 'message', ['key' => 'value']);

        $this->assertEquals('', $result);
    }

    public function testSetLogData(): void
    {
        $logger = new LogConsole(LogLevel::ERROR);
        $logger->setLogData(new LogData());

        $result = StreamHelper::invokeLoggerAndGetContent($logger, LogLevel::WARNING, 'message', ['key' => 'value']);

        $this->assertEquals('', $result);
    }

    public function testLogFalse(): void
    {
        $logger = new LogConsole(LogLevel::WARNING);
        $logger->setStream();

        $result = $logger(LogLevel::WARNING, 'message', ['key' => 'value']);

        $this->assertEquals('', $result);
    }
}
