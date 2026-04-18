<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Formatter;

use JardisAdapter\Logger\Formatter\LogBrowserConsoleFormat;
use PHPUnit\Framework\TestCase;

class LogBrowserConsoleFormatTest extends TestCase
{
    public function testBuildsRow(): void
    {
        $formatter = new LogBrowserConsoleFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
            'context' => 'TestContext',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertEquals('info', $decoded['type']);
    }

    public function testAccumulatesRows(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke(['level' => 'info', 'message' => 'First']);
        $formatter->__invoke(['level' => 'error', 'message' => 'Second']);
        $formatter->__invoke(['level' => 'debug', 'message' => 'Third']);

        $rows = $formatter->getRows();
        $this->assertCount(3, $rows);
    }

    public function testMapsLevelToType(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $tests = [
            ['level' => 'emergency', 'expectedType' => 'error'],
            ['level' => 'alert', 'expectedType' => 'error'],
            ['level' => 'critical', 'expectedType' => 'error'],
            ['level' => 'error', 'expectedType' => 'error'],
            ['level' => 'warning', 'expectedType' => 'warn'],
            ['level' => 'notice', 'expectedType' => 'info'],
            ['level' => 'info', 'expectedType' => 'info'],
            ['level' => 'debug', 'expectedType' => 'log'],
        ];

        foreach ($tests as $test) {
            $result = $formatter->__invoke([
                'level' => $test['level'],
                'message' => 'Test',
            ]);
            $decoded = json_decode($result, true);
            $this->assertEquals($test['expectedType'], $decoded['type'], "Failed for level: {$test['level']}");
        }
    }

    public function testReset(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke(['level' => 'info', 'message' => 'First']);
        $formatter->__invoke(['level' => 'info', 'message' => 'Second']);

        $this->assertCount(2, $formatter->getRows());

        $formatter->reset();

        $this->assertCount(0, $formatter->getRows());
    }

    public function testGetAccumulatedDataReturnsFullPayload(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke(['level' => 'info', 'message' => 'Test 1', 'context' => 'Context1']);
        $formatter->__invoke(['level' => 'error', 'message' => 'Test 2', 'context' => 'Context2']);

        // Call with empty array to get accumulated data
        $result = $formatter->__invoke([]);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('4.1.0', $decoded['version']);
        $this->assertEquals(['log', 'backtrace', 'type'], $decoded['columns']);
        $this->assertArrayHasKey('rows', $decoded);
        $this->assertCount(2, $decoded['rows']);
    }

    public function testRowStructure(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke([
            'level' => 'info',
            'message' => 'Test message',
            'context' => 'TestContext',
            'data' => ['key' => 'value'],
        ]);

        $rows = $formatter->getRows();
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertIsArray($row);
        $this->assertCount(3, $row); // [messageParts, backtrace, type]

        // Check message parts
        $this->assertIsArray($row[0]);
        $this->assertContains('Test message', $row[0]);
        $this->assertContains('[TestContext]', $row[0]);

        // Check backtrace
        $this->assertIsString($row[1]);

        // Check type
        $this->assertEquals('info', $row[2]);
    }

    public function testIncludesDataInMessageParts(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke([
            'level' => 'info',
            'message' => 'Test',
            'data' => ['user_id' => 123, 'action' => 'login'],
        ]);

        $rows = $formatter->getRows();
        $messageParts = $rows[0][0];

        $this->assertIsArray($messageParts);
        $this->assertGreaterThan(1, count($messageParts));
        $this->assertIsArray($messageParts[1]); // Data should be in parts
    }

    public function testBacktraceFromFileAndLine(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke([
            'level' => 'info',
            'message' => 'Test',
            'file' => '/path/to/file.php',
            'line' => 42,
        ]);

        $rows = $formatter->getRows();
        $backtrace = $rows[0][1];

        $this->assertEquals('/path/to/file.php:42', $backtrace);
    }

    public function testBacktraceUnknownWhenNotProvided(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke([
            'level' => 'info',
            'message' => 'Test',
        ]);

        $rows = $formatter->getRows();
        $backtrace = $rows[0][1];

        $this->assertEquals('unknown', $backtrace);
    }

    public function testEmptyDataNotIncludedInMessageParts(): void
    {
        $formatter = new LogBrowserConsoleFormat();

        $formatter->__invoke([
            'level' => 'info',
            'message' => 'Test message',
            'context' => 'TestContext',
            'data' => [],
        ]);

        $rows = $formatter->getRows();
        $messageParts = $rows[0][0];

        // Should have message and context, but not empty data
        $this->assertCount(2, $messageParts);
        $this->assertEquals('Test message', $messageParts[0]);
        $this->assertEquals('[TestContext]', $messageParts[1]);
    }
}
