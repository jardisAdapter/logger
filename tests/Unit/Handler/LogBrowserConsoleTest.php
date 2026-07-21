<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogBrowserConsole;
use PHPUnit\Framework\TestCase;

class LogBrowserConsoleTest extends TestCase
{
    private $stream;

    protected function setUp(): void
    {
        $this->stream = fopen('php://memory', 'r+');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    public function testBasicLogging(): void
    {
        $handler = new LogBrowserConsole('debug');
        $handler->setStream($this->stream);
        $handler->setContext('TestContext');

        $result = $handler('info', 'Test message', []);

        $this->assertNotNull($result);

        rewind($this->stream);
        $output = stream_get_contents($this->stream);
        $this->assertNotEmpty($output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertEquals('info', $decoded['type']);
    }

    public function testLogLevelMapping(): void
    {
        $handler = new LogBrowserConsole('debug');
        $handler->setStream($this->stream);

        $testCases = [
            ['error', 'error'],
            ['warning', 'warn'],
            ['info', 'info'],
            ['debug', 'log'],
            ['emergency', 'error'],
            ['critical', 'error'],
        ];

        foreach ($testCases as [$level, $expectedType]) {
            rewind($this->stream);
            ftruncate($this->stream, 0);

            $handler($level, "Test {$level}", []);

            rewind($this->stream);
            $output = stream_get_contents($this->stream);
            $decoded = json_decode($output, true);

            $this->assertEquals($expectedType, $decoded['type'], "Level {$level} should map to {$expectedType}");
        }
    }

    public function testContextInclusion(): void
    {
        $handler = new LogBrowserConsole('info');
        $handler->setStream($this->stream);
        $handler->setContext('OrderService');

        $handler('info', 'Order created', ['order_id' => 123]);

        rewind($this->stream);
        $output = stream_get_contents($this->stream);
        $decoded = json_decode($output, true);

        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('context', $decoded['data']);
        $this->assertEquals('OrderService', $decoded['data']['context']);
    }

    public function testAdditionalDataInclusion(): void
    {
        $handler = new LogBrowserConsole('info');
        $handler->setStream($this->stream);

        $handler('info', 'User login', ['user_id' => 456, 'ip' => '192.168.1.1']);

        rewind($this->stream);
        $output = stream_get_contents($this->stream);
        $decoded = json_decode($output, true);

        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('data', $decoded['data']);
    }

    public function testRowAccumulation(): void
    {
        $handler = new LogBrowserConsole('debug');
        $handler->setContext('TestContext');

        // Without stream - logs accumulate in rows
        $handler('info', 'First message', []);
        $handler('error', 'Second message', []);
        $handler('debug', 'Third message', []);

        $rows = $handler->getRows();

        $this->assertCount(3, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertIsArray($rows[1]);
        $this->assertIsArray($rows[2]);
    }

    public function testFlushMethod(): void
    {
        $handler = new LogBrowserConsole('info');
        $handler->setContext('TestContext');

        $handler('info', 'Message before flush', []);

        $rowsBefore = $handler->getRows();
        $this->assertCount(1, $rowsBefore);

        // Flush should trigger header send
        $handler->flush();

        // Note: We can't actually test header sending in CLI context
        // but we can verify the method doesn't throw
        $this->assertTrue(true);
    }

    public function testLogLevelFiltering(): void
    {
        $handler = new LogBrowserConsole('warning');
        $handler->setStream($this->stream);

        // Should log (warning level and above)
        $result1 = $handler('error', 'Error message', []);
        $this->assertNotNull($result1);

        // Should not log (below warning level)
        $result2 = $handler('info', 'Info message', []);
        $this->assertNull($result2);

        // Should not log (below warning level)
        $result3 = $handler('debug', 'Debug message', []);
        $this->assertNull($result3);
    }

    public function testEmptyDataArray(): void
    {
        $handler = new LogBrowserConsole('info');
        $handler->setStream($this->stream);

        $handler('info', 'Simple message', []);

        rewind($this->stream);
        $output = stream_get_contents($this->stream);
        $decoded = json_decode($output, true);

        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('message', $decoded);
    }

    public function testHandlerIdAndName(): void
    {
        $handler = new LogBrowserConsole('info');

        // Test auto-generated handler ID
        $handlerId = $handler->getHandlerId();
        $this->assertNotEmpty($handlerId);
        $this->assertStringStartsWith('handler_', $handlerId);

        // Test handler name
        $this->assertNull($handler->getHandlerName());

        $handler->setHandlerName('browser_logger');
        $this->assertEquals('browser_logger', $handler->getHandlerName());
    }

    public function testHeaderSizeLimitWithStream(): void
    {
        $handler = new LogBrowserConsole('info');
        $handler->setStream($this->stream);

        // Create a large message that would exceed header size limit
        $largeData = str_repeat('A', 50000); // 50KB per message

        // Log 5 messages (total 250KB which exceeds 240KB limit)
        for ($i = 0; $i < 5; $i++) {
            $handler('info', "Message {$i}", ['large_data' => $largeData]);
        }

        rewind($this->stream);
        $output = stream_get_contents($this->stream);

        // With stream, all messages should be logged
        $this->assertNotEmpty($output);

        // Count number of log lines
        $lineCount = substr_count($output, "\n");
        $this->assertEquals(5, $lineCount);
    }

    public function testMultipleLogsAccumulate(): void
    {
        $handler = new LogBrowserConsole('debug');

        $handler('info', 'Message 1', []);
        $handler('error', 'Message 2', []);
        $handler('debug', 'Message 3', []);
        $handler('warning', 'Message 4', []);

        $rows = $handler->getRows();

        $this->assertCount(4, $rows);
    }

    public function testSetFormatOverridesDefaultFormatter(): void
    {
        $handler = new LogBrowserConsole('info');
        $handler->setStream($this->stream);

        // Set custom format (though BrowserConsole uses its own internal formatter)
        $customFormat = new \JardisAdapter\Logger\Formatter\LogJsonFormat();
        $handler->setFormat($customFormat);

        $handler('info', 'Test message', []);

        rewind($this->stream);
        $output = stream_get_contents($this->stream);

        // Output should still be present
        $this->assertNotEmpty($output);
    }

    public function testLoggingWithStream(): void
    {
        $handler = new LogBrowserConsole('info');
        $handler->setStream($this->stream);

        $handler('info', 'Test with stream', ['key' => 'value']);

        rewind($this->stream);
        $output = stream_get_contents($this->stream);

        $this->assertNotEmpty($output);
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('Test with stream', $decoded['message']);
    }

    public function testLoggingWithoutStreamAccumulatesRows(): void
    {
        $handler = new LogBrowserConsole('info');

        // Without stream, logs should accumulate in formatter
        $handler('info', 'First', []);
        $handler('error', 'Second', []);

        $rows = $handler->getRows();

        $this->assertCount(2, $rows);
        $this->assertIsArray($rows[0]);
        $this->assertIsArray($rows[1]);
    }
}
