<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Formatter;

use JardisAdapter\Logger\Formatter\LogSlackFormat;
use PHPUnit\Framework\TestCase;

class LogSlackFormatTest extends TestCase
{
    public function testFormatsBasicMessage(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('text', $decoded);
        $this->assertStringContainsString('Test message', $decoded['text']);
        $this->assertStringContainsString(':speech_balloon:', $decoded['text']); // Info emoji
    }

    public function testIncludesEmojiForLevel(): void
    {
        $formatter = new LogSlackFormat();

        $tests = [
            ['level' => 'emergency', 'emoji' => ':rotating_light:'],
            ['level' => 'alert', 'emoji' => ':rotating_light:'],
            ['level' => 'critical', 'emoji' => ':rotating_light:'],
            ['level' => 'error', 'emoji' => ':x:'],
            ['level' => 'warning', 'emoji' => ':warning:'],
            ['level' => 'notice', 'emoji' => ':information_source:'],
            ['level' => 'info', 'emoji' => ':speech_balloon:'],
            ['level' => 'debug', 'emoji' => ':bug:'],
        ];

        foreach ($tests as $test) {
            $result = $formatter->__invoke([
                'level' => $test['level'],
                'message' => 'Test',
            ]);
            $decoded = json_decode($result, true);
            $this->assertStringContainsString($test['emoji'], $decoded['text'], "Failed for level: {$test['level']}");
        }
    }

    public function testCreatesAttachmentWithContext(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'error',
            'message' => 'Error occurred',
            'context' => 'OrderService',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertArrayHasKey('attachments', $decoded);
        $this->assertCount(1, $decoded['attachments']);

        $attachment = $decoded['attachments'][0];
        $this->assertArrayHasKey('fields', $attachment);
        $this->assertArrayHasKey('color', $attachment);

        // Find context field
        $contextField = array_filter($attachment['fields'], fn($f) => $f['title'] === 'Context');
        $this->assertCount(1, $contextField);
        $this->assertEquals('OrderService', array_values($contextField)[0]['value']);
    }

    public function testCreatesAttachmentWithData(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'info',
            'message' => 'User action',
            'data' => ['user_id' => 123, 'action' => 'login'],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertArrayHasKey('attachments', $decoded);
        $attachment = $decoded['attachments'][0];

        // Find data field
        $dataField = array_filter($attachment['fields'], fn($f) => $f['title'] === 'Data');
        $this->assertCount(1, $dataField);

        $dataValue = array_values($dataField)[0]['value'];
        $this->assertStringContainsString('user_id', $dataValue);
        $this->assertStringContainsString('123', $dataValue);
        $this->assertStringContainsString('login', $dataValue);
    }

    public function testColorMappingForLevels(): void
    {
        $formatter = new LogSlackFormat();

        $tests = [
            ['level' => 'emergency', 'color' => 'danger'],
            ['level' => 'alert', 'color' => 'danger'],
            ['level' => 'critical', 'color' => 'danger'],
            ['level' => 'error', 'color' => '#ff0000'],
            ['level' => 'warning', 'color' => 'warning'],
            ['level' => 'notice', 'color' => '#2196F3'],
            ['level' => 'info', 'color' => '#2196F3'],
            ['level' => 'debug', 'color' => '#607D8B'],
        ];

        foreach ($tests as $test) {
            $result = $formatter->__invoke([
                'level' => $test['level'],
                'message' => 'Test',
                'context' => 'Test', // Need context to create attachment
            ]);
            $decoded = json_decode($result, true);
            $this->assertEquals($test['color'], $decoded['attachments'][0]['color'], "Failed for level: {$test['level']}");
        }
    }

    public function testIncludesLevelField(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'warning',
            'message' => 'Warning message',
            'context' => 'TestContext',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $attachment = $decoded['attachments'][0];
        $levelField = array_filter($attachment['fields'], fn($f) => $f['title'] === 'Level');

        $this->assertCount(1, $levelField);
        $this->assertEquals('WARNING', array_values($levelField)[0]['value']);
    }

    public function testShortFieldsForContextAndLevel(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'context' => 'TestContext',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $fields = $decoded['attachments'][0]['fields'];

        $contextField = array_filter($fields, fn($f) => $f['title'] === 'Context');
        $this->assertTrue(array_values($contextField)[0]['short']);

        $levelField = array_filter($fields, fn($f) => $f['title'] === 'Level');
        $this->assertTrue(array_values($levelField)[0]['short']);
    }

    public function testDataFieldIsNotShort(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'data' => ['key' => 'value'],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $fields = $decoded['attachments'][0]['fields'];
        $dataField = array_filter($fields, fn($f) => $f['title'] === 'Data');

        $this->assertFalse(array_values($dataField)[0]['short']);
    }

    public function testIncludesFooterAndTimestamp(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'context' => 'TestContext',
        ];

        $beforeTime = time();
        $result = $formatter->__invoke($logData);
        $afterTime = time();
        $decoded = json_decode($result, true);

        $attachment = $decoded['attachments'][0];
        $this->assertEquals('JardisCore Logger', $attachment['footer']);
        $this->assertGreaterThanOrEqual($beforeTime, $attachment['ts']);
        $this->assertLessThanOrEqual($afterTime, $attachment['ts']);
    }

    public function testNoAttachmentWithoutContextOrData(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Simple message',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertArrayNotHasKey('attachments', $decoded);
        $this->assertArrayHasKey('text', $decoded);
    }

    public function testDataFormattedAsPrettyJson(): void
    {
        $formatter = new LogSlackFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'data' => ['nested' => ['key' => 'value']],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $dataField = array_filter($decoded['attachments'][0]['fields'], fn($f) => $f['title'] === 'Data');
        $dataValue = array_values($dataField)[0]['value'];

        $this->assertStringStartsWith('```', $dataValue);
        $this->assertStringEndsWith('```', $dataValue);
    }
}
