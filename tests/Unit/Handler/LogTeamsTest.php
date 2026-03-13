<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogTeams;
use JardisAdapter\Logger\Tests\Helpers\StreamHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogTeamsTest extends TestCase
{
    private function invokeLoggerAndGetJson(
        string $level,
        string $message,
        array $data = [],
        ?string $context = null
    ): array {
        $logger = new LogTeams($level, 'https://outlook.office.com/webhook/TEST');
        if ($context !== null) {
            $logger->setContext($context);
        }
        return StreamHelper::invokeLoggerAndGetJson($logger, $level, $message, $data);
    }

    public function testTeamsFormatsMessageCard(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test message', ['key' => 'value']);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('@type', $decoded);
        $this->assertEquals('MessageCard', $decoded['@type']);
        $this->assertArrayHasKey('title', $decoded);
        $this->assertArrayHasKey('sections', $decoded);
    }

    public function testTeamsIncludesMessageInActivityTitle(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test message');

        $this->assertArrayHasKey('sections', $decoded);
        $this->assertIsArray($decoded['sections']);
        $this->assertGreaterThan(0, count($decoded['sections']));

        $section = $decoded['sections'][0];
        $this->assertArrayHasKey('activityTitle', $section);
        $this->assertEquals('Test message', $section['activityTitle']);
    }

    public function testTeamsSetsColorBasedOnLevel(): void
    {
        $testCases = [
            [LogLevel::ERROR, 'DC3545'],
            [LogLevel::WARNING, 'FFC107'],
            [LogLevel::INFO, '007BFF'],
            [LogLevel::DEBUG, '6C757D'],
        ];

        foreach ($testCases as [$level, $expectedColor]) {
            $decoded = $this->invokeLoggerAndGetJson($level, "Test {$level}");

            $this->assertArrayHasKey('themeColor', $decoded);
            $this->assertEquals($expectedColor, $decoded['themeColor'], "Color mismatch for level {$level}");
        }
    }

    public function testTeamsSetsTitleWithEmojiBasedOnLevel(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::ERROR, 'Error message');

        $this->assertArrayHasKey('title', $decoded);
        $this->assertStringContainsString('Error', $decoded['title']);
        $this->assertStringContainsString('❗', $decoded['title']);
    }

    public function testTeamsIncludesFactsWithLevel(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test with facts');

        $section = $decoded['sections'][0];
        $this->assertArrayHasKey('facts', $section);
        $this->assertIsArray($section['facts']);

        $levelFact = array_filter($section['facts'], fn($f) => $f['name'] === 'Level');
        $this->assertNotEmpty($levelFact);

        $levelFact = reset($levelFact);
        $this->assertEquals('INFO', $levelFact['value']);
    }

    public function testTeamsIncludesContextInFacts(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test with context', [], 'OrderService');

        $section = $decoded['sections'][0];
        $this->assertArrayHasKey('facts', $section);

        $contextFact = array_filter($section['facts'], fn($f) => $f['name'] === 'Context');
        $this->assertNotEmpty($contextFact);

        $contextFact = reset($contextFact);
        $this->assertEquals('OrderService', $contextFact['value']);
    }

    public function testTeamsIncludesContextInActivitySubtitle(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test message', [], 'PaymentService');

        $section = $decoded['sections'][0];
        $this->assertArrayHasKey('activitySubtitle', $section);
        $this->assertStringContainsString('PaymentService', $section['activitySubtitle']);
    }

    public function testTeamsIncludesCustomDataAsFacts(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(
            LogLevel::INFO,
            'User action',
            ['user_id' => 123, 'action' => 'login']
        );

        $section = $decoded['sections'][0];
        $this->assertArrayHasKey('facts', $section);

        $facts = $section['facts'];
        $userIdFact = array_filter($facts, fn($f) => $f['name'] === 'User_id');
        $this->assertNotEmpty($userIdFact);

        $actionFact = array_filter($facts, fn($f) => $f['name'] === 'Action');
        $this->assertNotEmpty($actionFact);
    }

    public function testTeamsLimitsNumberOfFacts(): void
    {
        $largeData = [];
        for ($i = 0; $i < 10; $i++) {
            $largeData["field_{$i}"] = "value_{$i}";
        }

        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test with many facts', $largeData);

        $section = $decoded['sections'][0];
        $facts = $section['facts'];

        // Should limit to 5 custom facts + level fact + "Additional Fields" indicator
        $this->assertLessThanOrEqual(10, count($facts));

        // Check for "Additional Fields" indicator
        $additionalFact = array_filter($facts, fn($f) => $f['name'] === 'Additional Fields');
        $this->assertNotEmpty($additionalFact);
    }

    public function testTeamsFormatsComplexDataAsJson(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(
            LogLevel::INFO,
            'Test complex data',
            ['nested' => ['foo' => 'bar', 'baz' => 123]]
        );

        $section = $decoded['sections'][0];
        $facts = $section['facts'];

        $nestedFact = array_filter($facts, fn($f) => $f['name'] === 'Nested');
        $this->assertNotEmpty($nestedFact);

        $nestedFact = reset($nestedFact);
        $this->assertJson($nestedFact['value']);
    }

    public function testTeamsTruncatesLongValues(): void
    {
        $longString = str_repeat('x', 200);

        $decoded = $this->invokeLoggerAndGetJson(
            LogLevel::INFO,
            'Test truncation',
            ['long_field' => $longString]
        );

        $section = $decoded['sections'][0];
        $facts = $section['facts'];

        $longFact = array_filter($facts, fn($f) => $f['name'] === 'Long_field');
        $this->assertNotEmpty($longFact);

        $longFact = reset($longFact);
        $this->assertLessThanOrEqual(100, strlen($longFact['value']));
        $this->assertStringEndsWith('...', $longFact['value']);
    }

    public function testTeamsHandlesBooleanValues(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(
            LogLevel::INFO,
            'Test booleans',
            ['is_active' => true, 'is_deleted' => false]
        );

        $section = $decoded['sections'][0];
        $facts = $section['facts'];

        $trueFact = array_filter($facts, fn($f) => $f['name'] === 'Is_active');
        $this->assertNotEmpty($trueFact);
        $trueFact = reset($trueFact);
        $this->assertEquals('true', $trueFact['value']);

        $falseFact = array_filter($facts, fn($f) => $f['name'] === 'Is_deleted');
        $this->assertNotEmpty($falseFact);
        $falseFact = reset($falseFact);
        $this->assertEquals('false', $falseFact['value']);
    }

    public function testTeamsHandlesNullValues(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(
            LogLevel::INFO,
            'Test null',
            ['nullable_field' => null]
        );

        $section = $decoded['sections'][0];
        $facts = $section['facts'];

        $nullFact = array_filter($facts, fn($f) => $f['name'] === 'Nullable_field');
        $this->assertNotEmpty($nullFact);
        $nullFact = reset($nullFact);
        $this->assertEquals('null', $nullFact['value']);
    }

    public function testTeamsIncludesSummary(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'This is a test message for summary');

        $this->assertArrayHasKey('summary', $decoded);
        $this->assertNotEmpty($decoded['summary']);
    }

    public function testTeamsGetters(): void
    {
        $webhookUrl = 'https://outlook.office.com/webhook/TEST123';
        $logger = new LogTeams(LogLevel::INFO, $webhookUrl, 15, 5);

        $this->assertEquals($webhookUrl, $logger->getWebhookUrl());
        $this->assertEquals(15, $logger->getTimeout());
        $this->assertEquals(5, $logger->getRetryAttempts());
    }

    public function testTeamsHandlerIdAndName(): void
    {
        $logger = new LogTeams(LogLevel::INFO, 'https://outlook.office.com/webhook/TEST');

        // Test auto-generated handler ID
        $handlerId = $logger->getHandlerId();
        $this->assertNotEmpty($handlerId);
        $this->assertStringStartsWith('handler_', $handlerId);

        // Test handler name
        $this->assertNull($logger->getHandlerName());

        $logger->setHandlerName('teams_logger');
        $this->assertEquals('teams_logger', $logger->getHandlerName());
    }
}
