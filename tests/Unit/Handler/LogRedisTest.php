<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogRedis;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogRedisTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $logRedis = new LogRedis(LogLevel::INFO);

        $this->assertInstanceOf(LogRedis::class, $logRedis);
    }

    public function testConstructorWithCustomParameters(): void
    {
        $logRedis = new LogRedis(
            logLevel: LogLevel::ERROR,
            host: 'redis-server',
            port: 6380,
            timeout: 5.0,
            password: 'secret',
            database: 1,
            ttl: 7200
        );

        $this->assertInstanceOf(LogRedis::class, $logRedis);
    }

    public function testLazyConnection(): void
    {
        // Create handler but don't log yet
        $logRedis = new LogRedis(
            logLevel: LogLevel::INFO,
            host: 'non-existent-host',
            port: 9999
        );

        // Connection should not have been attempted yet
        $this->assertNull($logRedis->getRedis());
    }

    public function testConnectionFailureDoesNotThrow(): void
    {
        $logRedis = new LogRedis(
            logLevel: LogLevel::INFO,
            host: 'invalid-host',
            port: 9999,
            timeout: 0.1
        );

        // This should not throw, just return false
        $result = $logRedis(LogLevel::INFO, 'Test message', ['key' => 'value']);

        // Result will be false or empty string depending on implementation
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    public function testEncodeJsonSuccess(): void
    {
        $logRedis = new class('info') extends LogRedis {
            public function testEncode($value): string
            {
                return $this->encode($value);
            }
        };

        $data = ['key' => 'value'];
        $expectedJson = json_encode($data);

        $this->assertSame(
            $expectedJson,
            $logRedis->testEncode($data),
            'Encode should correctly return JSON string for an array.'
        );
    }

    public function testEncodeFallbackToSerialization(): void
    {
        $logRedis = new class('info') extends LogRedis {
            public function testEncode($value): string
            {
                return $this->encode($value);
            }
        };

        // Simulate invalid JSON by encoding a resource
        $data = fopen('php://memory', 'r'); // resources cannot be JSON-encoded

        $result = $logRedis->testEncode($data);

        $this->assertStringContainsString(
            'i:0',
            $result,
            'Encode should fall back to serialization when JSON encoding fails.'
        );
    }
}
