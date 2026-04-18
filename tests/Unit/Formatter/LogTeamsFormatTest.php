<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Formatter;

use JardisAdapter\Logger\Formatter\LogTeamsFormat;
use PHPUnit\Framework\TestCase;

class LogTeamsFormatTest extends TestCase
{
    public function testFormatsMessageCard(): void
    {
        $formatter = new LogTeamsFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
            'context' => 'TestContext',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('MessageCard', $decoded['@type']);
        $this->assertEquals('https://schema.org/extensions', $decoded['@context']);
        $this->assertEquals('Test message', $decoded['summary']);
        $this->assertEquals('💬 Information', $decoded['title']);
        $this->assertEquals('007BFF', $decoded['themeColor']);
    }

    public function testBuildsFacts(): void
    {
        $formatter = new LogTeamsFormat();
        $logData = [
            'level' => 'error',
            'message' => 'Error occurred',
            'context' => 'OrderService',
            'timestamp' => '2024-01-15 10:30:00',
            'data' => ['user_id' => 123],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $facts = $decoded['sections'][0]['facts'];
        $this->assertIsArray($facts);

        // Check level fact
        $levelFact = array_filter($facts, fn($f) => $f['name'] === 'Level');
        $this->assertCount(1, $levelFact);
        $this->assertEquals('ERROR', array_values($levelFact)[0]['value']);

        // Check context fact
        $contextFact = array_filter($facts, fn($f) => $f['name'] === 'Context');
        $this->assertCount(1, $contextFact);
        $this->assertEquals('OrderService', array_values($contextFact)[0]['value']);

        // Check timestamp fact
        $timestampFact = array_filter($facts, fn($f) => $f['name'] === 'Timestamp');
        $this->assertCount(1, $timestampFact);
        $this->assertEquals('2024-01-15 10:30:00', array_values($timestampFact)[0]['value']);
    }

    public function testTruncatesLongValues(): void
    {
        $formatter = new LogTeamsFormat();
        $longString = str_repeat('a', 150);
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'data' => ['long_value' => $longString],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $facts = $decoded['sections'][0]['facts'];
        $longValueFact = array_filter($facts, fn($f) => $f['name'] === 'Long_value');
        $this->assertCount(1, $longValueFact);

        $value = array_values($longValueFact)[0]['value'];
        $this->assertLessThanOrEqual(100, strlen($value));
        $this->assertStringEndsWith('...', $value);
    }

    public function testHandlesBooleanAndNull(): void
    {
        $formatter = new LogTeamsFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'data' => [
                'is_active' => true,
                'is_deleted' => false,
                'nullable' => null,
            ],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $facts = $decoded['sections'][0]['facts'];

        $activeFact = array_filter($facts, fn($f) => $f['name'] === 'Is_active');
        $this->assertEquals('true', array_values($activeFact)[0]['value']);

        $deletedFact = array_filter($facts, fn($f) => $f['name'] === 'Is_deleted');
        $this->assertEquals('false', array_values($deletedFact)[0]['value']);

        $nullableFact = array_filter($facts, fn($f) => $f['name'] === 'Nullable');
        $this->assertEquals('null', array_values($nullableFact)[0]['value']);
    }

    public function testLimitsFactsToFive(): void
    {
        $formatter = new LogTeamsFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'context' => 'TestContext',
            'timestamp' => '2024-01-15 10:30:00',
            'data' => [
                'field1' => 'value1',
                'field2' => 'value2',
                'field3' => 'value3',
                'field4' => 'value4',
                'field5' => 'value5',
                'field6' => 'value6',
                'field7' => 'value7',
            ],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $facts = $decoded['sections'][0]['facts'];

        // Level + Context + Timestamp + 5 data fields + "Additional Fields" indicator
        $this->assertCount(9, $facts);

        // Check for "Additional Fields" indicator
        $additionalFieldsFact = array_filter($facts, fn($f) => $f['name'] === 'Additional Fields');
        $this->assertCount(1, $additionalFieldsFact);
        $this->assertEquals('+2 more...', array_values($additionalFieldsFact)[0]['value']);
    }

    public function testColorMapping(): void
    {
        $formatter = new LogTeamsFormat();

        $tests = [
            ['level' => 'emergency', 'color' => 'FF0000'],
            ['level' => 'alert', 'color' => 'FF0000'],
            ['level' => 'critical', 'color' => 'FF0000'],
            ['level' => 'error', 'color' => 'DC3545'],
            ['level' => 'warning', 'color' => 'FFC107'],
            ['level' => 'notice', 'color' => '17A2B8'],
            ['level' => 'info', 'color' => '007BFF'],
            ['level' => 'debug', 'color' => '6C757D'],
        ];

        foreach ($tests as $test) {
            $result = $formatter->__invoke([
                'level' => $test['level'],
                'message' => 'Test',
            ]);
            $decoded = json_decode($result, true);
            $this->assertEquals($test['color'], $decoded['themeColor'], "Failed for level: {$test['level']}");
        }
    }

    public function testTitleMapping(): void
    {
        $formatter = new LogTeamsFormat();

        $tests = [
            ['level' => 'emergency', 'title' => '🚨 Emergency'],
            ['level' => 'alert', 'title' => '🔴 Alert'],
            ['level' => 'critical', 'title' => '❌ Critical'],
            ['level' => 'error', 'title' => '❗ Error'],
            ['level' => 'warning', 'title' => '⚠️ Warning'],
            ['level' => 'notice', 'title' => 'ℹ️ Notice'],
            ['level' => 'info', 'title' => '💬 Information'],
            ['level' => 'debug', 'title' => '🐛 Debug'],
        ];

        foreach ($tests as $test) {
            $result = $formatter->__invoke([
                'level' => $test['level'],
                'message' => 'Test',
            ]);
            $decoded = json_decode($result, true);
            $this->assertEquals($test['title'], $decoded['title'], "Failed for level: {$test['level']}");
        }
    }

    public function testActivitySubtitleWithContext(): void
    {
        $formatter = new LogTeamsFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
            'context' => 'OrderService',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertEquals('Context: OrderService', $decoded['sections'][0]['activitySubtitle']);
    }

    public function testActivitySubtitleWithoutContext(): void
    {
        $formatter = new LogTeamsFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test message',
            'context' => '',
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertArrayNotHasKey('activitySubtitle', $decoded['sections'][0]);
    }

    public function testHandlesComplexDataStructures(): void
    {
        $formatter = new LogTeamsFormat();
        $logData = [
            'level' => 'info',
            'message' => 'Test',
            'data' => [
                'array' => ['a', 'b', 'c'],
                'object' => ['key' => 'value'],
            ],
        ];

        $result = $formatter->__invoke($logData);
        $decoded = json_decode($result, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('sections', $decoded);
    }
}
