<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\Handler\LogLoki;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogLokiTest extends TestCase
{
    private string $wiremockUrl = 'http://wiremock:8080';
    private string $lokiEndpoint;

    protected function setUp(): void
    {
        $this->lokiEndpoint = $this->wiremockUrl . '/loki/api/v1/push';

        // Reset WireMock before each test
        @file_get_contents($this->wiremockUrl . '/__admin/reset', false, stream_context_create([
            'http' => ['method' => 'POST', 'ignore_errors' => true]
        ]));
    }

    public function testLokiSendsCorrectFormat(): void
    {
        $this->setupWiremockStub(204);

        $handler = new LogLoki(
            LogLevel::INFO,
            $this->lokiEndpoint,
            ['app' => 'test_app', 'env' => 'test']
        );

        $result = $handler(LogLevel::INFO, 'Test Loki message', ['user_id' => 123]);

        $this->assertNotNull($result);

        // Verify request structure
        $requests = $this->getWiremockRequests();
        $this->assertCount(1, $requests);

        $body = json_decode($requests[0]['request']['body'], true);

        // Verify Loki format
        $this->assertArrayHasKey('streams', $body);
        $this->assertIsArray($body['streams']);
        $this->assertCount(1, $body['streams']);

        $stream = $body['streams'][0];
        $this->assertArrayHasKey('stream', $stream);
        $this->assertArrayHasKey('values', $stream);

        // Verify labels
        $labels = $stream['stream'];
        $this->assertEquals('test_app', $labels['app']);
        $this->assertEquals('test', $labels['env']);
        $this->assertEquals('info', $labels['level']);

        // Verify values format
        $values = $stream['values'];
        $this->assertCount(1, $values);
        $this->assertCount(2, $values[0]); // [timestamp, message]

        // Verify timestamp is in nanoseconds (19 digits)
        $timestamp = $values[0][0];
        $this->assertIsString($timestamp);
        $this->assertGreaterThanOrEqual(19, strlen($timestamp));

        // Verify message
        $message = $values[0][1];
        $this->assertStringContainsString('Test Loki message', $message);
    }

    public function testLokiIncludesContextAsLabel(): void
    {
        $this->setupWiremockStub(204);

        $handler = new LogLoki(LogLevel::INFO, $this->lokiEndpoint);
        $handler->setContext('OrderService');

        $handler(LogLevel::INFO, 'Order created', []);

        $requests = $this->getWiremockRequests();
        $body = json_decode($requests[0]['request']['body'], true);

        $labels = $body['streams'][0]['stream'];
        $this->assertArrayHasKey('context', $labels);
        $this->assertEquals('OrderService', $labels['context']);
    }

    public function testLokiIncludesAdditionalDataInMessage(): void
    {
        $this->setupWiremockStub(204);

        $handler = new LogLoki(LogLevel::INFO, $this->lokiEndpoint);

        $handler(LogLevel::INFO, 'User login', ['user_id' => 456, 'ip' => '192.168.1.1']);

        $requests = $this->getWiremockRequests();
        $body = json_decode($requests[0]['request']['body'], true);

        $message = $body['streams'][0]['values'][0][1];
        $this->assertStringContainsString('User login', $message);
        $this->assertStringContainsString('user_id', $message);
        $this->assertStringContainsString('456', $message);
    }

    public function testLokiHandlesMultipleLevels(): void
    {
        $this->setupWiremockStub(204);

        $handler = new LogLoki(LogLevel::DEBUG, $this->lokiEndpoint);

        $levels = [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR];

        foreach ($levels as $level) {
            $handler($level, "Test {$level} message", []);
        }

        $requests = $this->getWiremockRequests();
        $this->assertCount(4, $requests);

        // Verify each request has correct level label
        foreach ($requests as $index => $request) {
            $body = json_decode($request['request']['body'], true);
            $labels = $body['streams'][0]['stream'];
            $this->assertArrayHasKey('level', $labels);
        }
    }

    public function testLokiSanitizesLabelValues(): void
    {
        $this->setupWiremockStub(204);

        $handler = new LogLoki(
            LogLevel::INFO,
            $this->lokiEndpoint,
            ['app' => 'my-app-name', 'env' => 'staging/prod']
        );

        $handler(LogLevel::INFO, 'Test sanitization', []);

        $requests = $this->getWiremockRequests();
        $body = json_decode($requests[0]['request']['body'], true);

        $labels = $body['streams'][0]['stream'];

        // Verify sanitization (dashes and slashes replaced with underscores)
        $this->assertEquals('my_app_name', $labels['app']);
        $this->assertEquals('staging_prod', $labels['env']);
    }

    public function testLokiRetriesOnFailure(): void
    {
        $this->setupWiremockStubWithScenario();

        $handler = new LogLoki(
            LogLevel::ERROR,
            $this->wiremockUrl . '/loki-retry',
            [],
            2,  // timeout
            2   // retry attempts
        );

        $result = $handler(LogLevel::ERROR, 'Retry test', []);

        $requests = $this->getWiremockRequests();

        // Should have retried until success
        $this->assertGreaterThanOrEqual(1, count($requests));
        $this->assertNotNull($result);
    }

    public function testLokiHandles204Response(): void
    {
        $this->setupWiremockStub(204);

        $handler = new LogLoki(LogLevel::INFO, $this->lokiEndpoint);
        $result = $handler(LogLevel::INFO, 'Success test', []);

        $this->assertNotNull($result);
    }

    public function testLokiHandlesFailureResponse(): void
    {
        $this->setupWiremockStub(500);

        $handler = new LogLoki(
            LogLevel::ERROR,
            $this->lokiEndpoint,
            [],
            2,  // timeout
            0   // no retries for this test
        );

        $result = $handler(LogLevel::ERROR, 'Failure test', []);

        $this->assertNull($result);
    }

    public function testAddStaticLabel(): void
    {
        $this->setupWiremockStub(204);

        $handler = new LogLoki(LogLevel::INFO, $this->lokiEndpoint, ['app' => 'myapp']);
        $handler->addStaticLabel('version', 'v1.2.3');

        $handler(LogLevel::INFO, 'Test with dynamic label', []);

        $requests = $this->getWiremockRequests();
        $body = json_decode($requests[0]['request']['body'], true);

        $labels = $body['streams'][0]['stream'];
        $this->assertEquals('myapp', $labels['app']);
        $this->assertEquals('v1_2_3', $labels['version']); // Sanitized
    }

    public function testGetStaticLabels(): void
    {
        $handler = new LogLoki(
            LogLevel::INFO,
            $this->lokiEndpoint,
            ['app' => 'myapp', 'env' => 'prod']
        );

        $labels = $handler->getStaticLabels();

        $this->assertEquals(['app' => 'myapp', 'env' => 'prod'], $labels);
    }

    public function testGetLokiUrl(): void
    {
        $handler = new LogLoki(LogLevel::INFO, $this->lokiEndpoint);

        $this->assertEquals($this->lokiEndpoint, $handler->getLokiUrl());
    }

    private function setupWiremockStub(int $statusCode): void
    {
        $stub = json_encode([
            'request' => [
                'method' => 'ANY',
                'urlPattern' => '.*'
            ],
            'response' => [
                'status' => $statusCode,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]
        ]);

        @file_get_contents(
            $this->wiremockUrl . '/__admin/mappings',
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $stub,
                    'ignore_errors' => true
                ]
            ])
        );
    }

    private function setupWiremockStubWithScenario(): void
    {
        // First request fails
        $stub1 = json_encode([
            'scenarioName' => 'LokiRetry',
            'requiredScenarioState' => 'Started',
            'newScenarioState' => 'FirstFail',
            'request' => [
                'method' => 'POST',
                'urlPattern' => '/loki-retry'
            ],
            'response' => [
                'status' => 500
            ]
        ]);

        // Second request succeeds
        $stub2 = json_encode([
            'scenarioName' => 'LokiRetry',
            'requiredScenarioState' => 'FirstFail',
            'request' => [
                'method' => 'POST',
                'urlPattern' => '/loki-retry'
            ],
            'response' => [
                'status' => 204
            ]
        ]);

        @file_get_contents($this->wiremockUrl . '/__admin/mappings', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $stub1,
                'ignore_errors' => true
            ]
        ]));

        @file_get_contents($this->wiremockUrl . '/__admin/mappings', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $stub2,
                'ignore_errors' => true
            ]
        ]));
    }

    private function getWiremockRequests(): array
    {
        $response = @file_get_contents(
            $this->wiremockUrl . '/__admin/requests',
            false,
            stream_context_create(['http' => ['ignore_errors' => true]])
        );

        if ($response === false) {
            return [];
        }

        $data = json_decode($response, true);
        return $data['requests'] ?? [];
    }
}
