<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogRedis;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Redis;

class LogRedisTest extends TestCase
{
    private function createRedisMock(): Redis
    {
        $redis = $this->createMock(Redis::class);
        return $redis;
    }

    public function testConstructorWithDefaults(): void
    {
        $logRedis = new LogRedis(LogLevel::INFO, $this->createRedisMock());

        $this->assertInstanceOf(LogRedis::class, $logRedis);
    }

    public function testConstructorWithCustomTtl(): void
    {
        $logRedis = new LogRedis(
            logLevel: LogLevel::ERROR,
            redis: $this->createRedisMock(),
            ttl: 7200
        );

        $this->assertInstanceOf(LogRedis::class, $logRedis);
    }

    public function testGetRedisReturnsSameInstance(): void
    {
        $redis = $this->createRedisMock();
        $logRedis = new LogRedis(LogLevel::INFO, $redis);

        $this->assertSame($redis, $logRedis->getRedis());
    }

    public function testEncodeJsonSuccess(): void
    {
        $logRedis = new class(LogLevel::INFO, new Redis()) extends LogRedis {
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
        $logRedis = new class(LogLevel::INFO, new Redis()) extends LogRedis {
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
