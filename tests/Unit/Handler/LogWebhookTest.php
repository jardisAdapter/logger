<?php

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogWebhook;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogWebhookTest extends TestCase
{
    public function testConstructorWithValidUrl(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook'
        );

        $this->assertEquals('https://example.com/webhook', $webhook->getUrl());
        $this->assertEquals('POST', $webhook->getMethod());
        $this->assertEquals(10, $webhook->getTimeout());
        $this->assertEquals(3, $webhook->getRetryAttempts());
    }

    public function testConstructorWithInvalidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid webhook URL');

        new LogWebhook(LogLevel::INFO, 'not-a-valid-url');
    }

    public function testConstructorWithInvalidTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be between 1 and 300 seconds');

        new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook',
            timeout: 0
        );
    }

    public function testConstructorWithInvalidRetryAttempts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry attempts must be between 0 and 10');

        new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook',
            retryAttempts: 11
        );
    }

    public function testCustomHeaders(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook',
            headers: [
                'Authorization' => 'Bearer token123',
                'X-Custom-Header' => 'value'
            ]
        );

        $headers = $webhook->getHeaders();
        $this->assertEquals('Bearer token123', $headers['Authorization']);
        $this->assertEquals('value', $headers['X-Custom-Header']);
        $this->assertEquals('application/json', $headers['Content-Type']); // Default
    }

    public function testSetHeader(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook'
        );

        $webhook->setHeader('X-API-Key', 'secret123');

        $headers = $webhook->getHeaders();
        $this->assertEquals('secret123', $headers['X-API-Key']);
    }

    public function testCustomMethod(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook',
            method: 'PUT'
        );

        $this->assertEquals('PUT', $webhook->getMethod());
    }

    public function testCustomBodyFormatter(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook'
        );

        $called = false;
        $capturedMessage = '';
        $capturedData = [];

        $webhook->setBodyFormatter(function ($message, $data) use (&$called, &$capturedMessage, &$capturedData) {
            $called = true;
            $capturedMessage = $message;
            $capturedData = $data;
            return json_encode(['custom' => $message]);
        });

        // Use stream to test without actual HTTP call
        $stream = fopen('php://memory', 'w+');
        $webhook->setStream($stream);

        $logger = new Logger('TestContext');
        $logger->addHandler($webhook);
        $logger->info('Test message', ['key' => 'value']);

        $this->assertTrue($called);
        $this->assertStringContainsString('Test message', $capturedMessage);
        $this->assertArrayHasKey('data', $capturedData);
        $this->assertEquals(['key' => 'value'], $capturedData['data']);
    }

    public function testDefaultJsonFormat(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook'
        );

        $stream = fopen('php://memory', 'w+');
        $webhook->setStream($stream);

        $logger = new Logger('TestContext');
        $logger->addHandler($webhook);
        $logger->info('Test message', ['key' => 'value']);

        rewind($stream);
        $output = stream_get_contents($stream);

        $json = json_decode($output, true);
        $this->assertIsArray($json);
        $this->assertStringContainsString('Test message', $json['message'] ?? '');
    }

    public function testDifferentHttpMethods(): void
    {
        $methods = ['POST', 'PUT', 'PATCH'];

        foreach ($methods as $method) {
            $webhook = new LogWebhook(
                LogLevel::INFO,
                'https://example.com/webhook',
                method: $method
            );

            $this->assertEquals($method, $webhook->getMethod());
        }
    }

    public function testCustomTimeout(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook',
            timeout: 30
        );

        $this->assertEquals(30, $webhook->getTimeout());
    }

    public function testCustomRetryAttempts(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook',
            retryAttempts: 5
        );

        $this->assertEquals(5, $webhook->getRetryAttempts());
    }

    public function testZeroRetryAttempts(): void
    {
        $webhook = new LogWebhook(
            LogLevel::INFO,
            'https://example.com/webhook',
            retryAttempts: 0
        );

        $this->assertEquals(0, $webhook->getRetryAttempts());
    }
}
