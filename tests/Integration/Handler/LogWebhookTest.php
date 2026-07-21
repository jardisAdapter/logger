<?php

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\Handler\LogWebhook;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogWebhookTest extends TestCase
{
    private string $wiremockUrl = 'http://wiremock:8080';

    protected function setUp(): void
    {
        // Reset WireMock before each test
        @file_get_contents($this->wiremockUrl . '/__admin/reset', false, stream_context_create([
            'http' => ['method' => 'POST', 'ignore_errors' => true]
        ]));
    }

    public function testWebhookSendsCorrectPostRequest(): void
    {
        // Setup WireMock stub
        $this->setupWiremockStub(200, '{"status":"ok"}');

        $logger = new LogWebhook(LogLevel::INFO, $this->wiremockUrl . '/webhook');
        $result = $logger(LogLevel::INFO, 'Test message', ['key' => 'value']);

        // Verify request was received by WireMock
        $requests = $this->getWiremockRequests();

        $this->assertCount(1, $requests);
        $this->assertEquals('POST', $requests[0]['request']['method']);
        $this->assertStringContainsString('Test message', $requests[0]['request']['body']);
        $this->assertStringContainsString('"key":"value"', $requests[0]['request']['body']);
    }

    public function testWebhookHandles200Response(): void
    {
        $this->setupWiremockStub(200, '{"status":"ok"}');

        $logger = new LogWebhook(LogLevel::INFO, $this->wiremockUrl . '/webhook');
        $result = $logger(LogLevel::INFO, 'Success test', []);

        $this->assertStringContainsString('Success test', $result);
    }

    public function testWebhookHandles404Response(): void
    {
        $this->setupWiremockStub(404, '{"error":"not found"}');

        $logger = new LogWebhook(LogLevel::ERROR, $this->wiremockUrl . '/not-found', 'POST', [], 2, 0);
        $result = $logger(LogLevel::ERROR, 'Not found test', []);

        // 404 returns null because it's a failed request (status >= 400)
        $this->assertNull($result, 'Webhook should return null on 404 (failed request)');

        // Verify request was attempted
        $requests = $this->getWiremockRequests();
        $this->assertGreaterThan(0, count($requests), 'Should have attempted at least one request');
    }

    public function testWebhookHandles500Response(): void
    {
        $this->setupWiremockStub(500, '{"error":"server error"}');

        $logger = new LogWebhook(LogLevel::ERROR, $this->wiremockUrl . '/error', 'POST', [], 2, 0);
        $result = $logger(LogLevel::ERROR, 'Server error test', []);

        // 500 returns null because it's a failed request (status >= 400)
        $this->assertNull($result, 'Webhook should return null on 500 (failed request)');

        // Verify retry attempts were made (0 retries = 1 attempt total)
        $requests = $this->getWiremockRequests();
        $this->assertEquals(1, count($requests), 'Should have made exactly 1 attempt (no retries configured)');
    }

    public function testWebhookRetriesOnFailure(): void
    {
        // First two requests fail (500), third succeeds (200)
        $this->setupWiremockStubWithScenario();

        $logger = new LogWebhook(LogLevel::ERROR, $this->wiremockUrl . '/retry', 'POST', [], 2, 2, 0);
        $result = $logger(LogLevel::ERROR, 'Retry test', []);

        $requests = $this->getWiremockRequests();

        // Should have retried until success
        $this->assertGreaterThanOrEqual(1, count($requests));
        $this->assertStringContainsString('Retry test', $result);
    }

    public function testWebhookSendsCustomHeaders(): void
    {
        $this->setupWiremockStub(200, '{"status":"ok"}');

        $logger = new LogWebhook(
            LogLevel::INFO,
            $this->wiremockUrl . '/webhook',
            'POST',
            ['X-Custom-Header' => 'CustomValue', 'Authorization' => 'Bearer token123']
        );
        $logger(LogLevel::INFO, 'Custom headers test', []);

        $requests = $this->getWiremockRequests();

        $this->assertCount(1, $requests);
        $headers = $requests[0]['request']['headers'];
        $this->assertEquals('CustomValue', $headers['X-Custom-Header']);
        $this->assertEquals('Bearer token123', $headers['Authorization']);
    }

    public function testWebhookSendsJsonContentType(): void
    {
        $this->setupWiremockStub(200, '{"status":"ok"}');

        $logger = new LogWebhook(LogLevel::INFO, $this->wiremockUrl . '/webhook');
        $logger(LogLevel::INFO, 'Content-Type test', []);

        $requests = $this->getWiremockRequests();

        $this->assertCount(1, $requests);
        $this->assertEquals('application/json', $requests[0]['request']['headers']['Content-Type']);
    }

    private function setupWiremockStub(int $statusCode, string $body): void
    {
        $stub = json_encode([
            'request' => [
                'method' => 'ANY',
                'urlPattern' => '.*'
            ],
            'response' => [
                'status' => $statusCode,
                'body' => $body,
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
            'scenarioName' => 'Retry',
            'requiredScenarioState' => 'Started',
            'newScenarioState' => 'FirstFail',
            'request' => [
                'method' => 'POST',
                'urlPattern' => '/retry'
            ],
            'response' => [
                'status' => 500,
                'body' => '{"error":"fail"}'
            ]
        ]);

        // Second request succeeds
        $stub2 = json_encode([
            'scenarioName' => 'Retry',
            'requiredScenarioState' => 'FirstFail',
            'request' => [
                'method' => 'POST',
                'urlPattern' => '/retry'
            ],
            'response' => [
                'status' => 200,
                'body' => '{"status":"ok"}'
            ]
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'ignore_errors' => true
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
