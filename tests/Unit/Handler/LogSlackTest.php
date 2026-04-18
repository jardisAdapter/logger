<?php

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogSlack;
use JardisAdapter\Logger\Tests\Helpers\StreamHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogSlackTest extends TestCase
{
    private function invokeLoggerAndGetJson(string $level, string $message, array $data = []): array
    {
        $logger = new LogSlack($level, 'https://hooks.slack.com/services/TEST');
        return StreamHelper::invokeLoggerAndGetJson($logger, $level, $message, $data);
    }

    public function testSlackFormatsMessageWithEmoji(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test message', ['key' => 'value']);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('text', $decoded);
        $this->assertStringContainsString(':speech_balloon:', $decoded['text']);
        $this->assertStringContainsString('Test message', $decoded['text']);
    }

    public function testSlackFormatsErrorWithCorrectEmoji(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::ERROR, 'Error message');

        $this->assertStringContainsString(':x:', $decoded['text']);
        $this->assertStringContainsString('Error message', $decoded['text']);
    }

    public function testSlackFormatsWarningWithCorrectEmoji(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::WARNING, 'Warning message');

        $this->assertStringContainsString(':warning:', $decoded['text']);
        $this->assertStringContainsString('Warning message', $decoded['text']);
    }

    public function testSlackIncludesAttachmentsWithContext(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Message with context', ['foo' => 'bar']);

        $this->assertArrayHasKey('attachments', $decoded);
        $this->assertIsArray($decoded['attachments']);
        $this->assertGreaterThan(0, count($decoded['attachments']));
    }

    public function testSlackIncludesLevelInAttachment(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::ERROR, 'Error with level');

        $this->assertArrayHasKey('attachments', $decoded);
        $attachment = $decoded['attachments'][0];

        $levelField = array_filter($attachment['fields'], fn($f) => $f['title'] === 'Level');
        $this->assertNotEmpty($levelField);
    }

    public function testSlackSetsColorBasedOnLevel(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::ERROR, 'Error color test');

        $this->assertArrayHasKey('attachments', $decoded);
        $attachment = $decoded['attachments'][0];

        $this->assertArrayHasKey('color', $attachment);
        $this->assertEquals('#ff0000', $attachment['color']);
    }

    public function testSlackIncludesDataFieldWhenProvided(): void
    {
        $decoded = $this->invokeLoggerAndGetJson(LogLevel::INFO, 'Test with data', ['user_id' => 123, 'action' => 'login']);

        $this->assertArrayHasKey('attachments', $decoded);
        $attachment = $decoded['attachments'][0];

        $dataField = array_filter($attachment['fields'], fn($f) => $f['title'] === 'Data');
        $this->assertNotEmpty($dataField);
    }
}
