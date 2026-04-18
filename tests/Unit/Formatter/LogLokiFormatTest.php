<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Formatter;

use JardisAdapter\Logger\Formatter\LogLokiFormat;
use PHPUnit\Framework\TestCase;

class LogLokiFormatTest extends TestCase
{
    public function testFormatsBasicLogData(): void
    {
        $formatter = new LogLokiFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
            'context' => 'TestContext',
            'data' => ['key' => 'value'],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('streams', $decoded);
        $this->assertCount(1, $decoded['streams']);

        $stream = $decoded['streams'][0];
        $this->assertArrayHasKey('stream', $stream);
        $this->assertArrayHasKey('values', $stream);

        $labels = $stream['stream'];
        $this->assertEquals('info', $labels['level']);
        $this->assertEquals('TestContext', $labels['context']);
    }

    public function testIncludesStaticLabels(): void
    {
        $formatter = new LogLokiFormat(['app' => 'myapp', 'env' => 'production']);
        $logData = [
            'level' => 'error',
            'message' => 'Error occurred',
            'context' => '',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $labels = $decoded['streams'][0]['stream'];
        $this->assertEquals('myapp', $labels['app']);
        $this->assertEquals('production', $labels['env']);
        $this->assertEquals('error', $labels['level']);
    }

    public function testSanitizesLabelValues(): void
    {
        $formatter = new LogLokiFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'context' => 'My-Context With Spaces!',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $labels = $decoded['streams'][0]['stream'];
        $this->assertEquals('My_Context_With_Spaces_', $labels['context']);
    }

    public function testSanitizationStartsWithLetterOrUnderscore(): void
    {
        $formatter = new LogLokiFormat();
        $formatter->addLabel('mykey', '123value');
        $logData = ['level' => 'info', 'message' => 'Test'];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $labels = $decoded['streams'][0]['stream'];
        // Label value starting with number gets _ prefix
        $this->assertEquals('_123value', $labels['mykey']);
    }

    public function testBuildsLogLineWithData(): void
    {
        $formatter = new LogLokiFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
            'data' => ['user_id' => 123, 'action' => 'login'],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $logLine = $decoded['streams'][0]['values'][0][1];
        $this->assertStringContainsString('Test message', $logLine);
        $this->assertStringContainsString('"user_id":123', $logLine);
        $this->assertStringContainsString('"action":"login"', $logLine);
    }

    public function testGeneratesNanosecondTimestamp(): void
    {
        $formatter = new LogLokiFormat();
        $logData = ['level' => 'info', 'message' => 'Test'];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $timestamp = $decoded['streams'][0]['values'][0][0];
        $this->assertIsString($timestamp);
        $this->assertGreaterThan(1000000000000000000, (int) $timestamp); // Nanoseconds
    }

    public function testUsesProvidedTimestamp(): void
    {
        $formatter = new LogLokiFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'timestamp' => 1234567890,
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $timestamp = $decoded['streams'][0]['values'][0][0];
        $this->assertEquals('1234567890000000000', $timestamp); // Seconds to nanoseconds
    }

    public function testAddLabel(): void
    {
        $formatter = new LogLokiFormat();
        $formatter->addLabel('new_key', 'new_value');

        $logData = ['level' => 'info', 'message' => 'Test'];
        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $labels = $decoded['streams'][0]['stream'];
        $this->assertEquals('new_value', $labels['new_key']);
    }

    public function testGetStaticLabels(): void
    {
        $staticLabels = ['app' => 'myapp', 'env' => 'test'];
        $formatter = new LogLokiFormat($staticLabels);

        $this->assertEquals($staticLabels, $formatter->getStaticLabels());
    }

    public function testHandlesEmptyData(): void
    {
        $formatter = new LogLokiFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
            'data' => [],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $logLine = $decoded['streams'][0]['values'][0][1];
        $this->assertEquals('Test message', $logLine);
    }

    public function testHandlesEmptyContext(): void
    {
        $formatter = new LogLokiFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'context' => '',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $labels = $decoded['streams'][0]['stream'];
        $this->assertArrayNotHasKey('context', $labels);
    }
}
