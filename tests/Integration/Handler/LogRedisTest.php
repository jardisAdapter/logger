<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\Handler\LogRedis;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Redis;

class LogRedisTest extends TestCase
{
    private ?Redis $redis = null;

    protected function setUp(): void
    {
        parent::setUp();

        $host = getenv('REDIS_HOST') ?: 'redis';
        $port = 6379;
        $password = getenv('REDIS_PASSWORD') ?: null;

        $this->redis = new Redis();
        try {
            if (!$this->redis->connect($host, $port, 2.5)) {
                $this->markTestSkipped("Redis server not available at {$host}:{$port}");
            }

            if ($password) {
                $this->redis->auth($password);
            }

            $this->redis->select(1);
            $this->redis->flushDB();
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis server not available: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->redis) {
            $this->redis->flushDB();
            $this->redis->close();
        }
        parent::tearDown();
    }

    public function testLogToRedis(): void
    {
        $logger = new Logger('TestContext');
        $logger->addHandler(new LogRedis(LogLevel::INFO, $this->redis, ttl: 300));
        $logger->info('Test message', ['key' => 'value', 'number' => 42]);

        usleep(100000);

        $keys = $this->redis->keys('Redis*');
        $this->assertGreaterThan(0, count($keys), 'Should have at least one key in Redis');

        if (count($keys) > 0) {
            $data = $this->redis->get($keys[0]);
            $this->assertIsString($data);

            $decoded = json_decode($data, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('message', $decoded);
            $this->assertStringContainsString('Test message', $decoded['message']);
        }
    }

    public function testLogWithTTL(): void
    {
        $logger = new Logger('TestContext');
        $logger->addHandler(new LogRedis(LogLevel::INFO, $this->redis, ttl: 2));
        $logger->info('TTL test message');

        usleep(100000);

        $keys = $this->redis->keys('Redis*');
        $this->assertGreaterThan(0, count($keys));

        if (count($keys) > 0) {
            $ttl = $this->redis->ttl($keys[0]);
            $this->assertGreaterThan(0, $ttl);
            $this->assertLessThanOrEqual(2, $ttl);
        }
    }

    public function testMultipleLogsToRedis(): void
    {
        $logger = new Logger('TestContext');
        $logger->addHandler(new LogRedis(LogLevel::DEBUG, $this->redis, ttl: 300));

        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');

        usleep(200000);

        $keys = $this->redis->keys('Redis*');
        $this->assertCount(4, $keys, 'Should have 4 keys in Redis');
    }

    public function testLogWithDifferentDataTypes(): void
    {
        $logger = new Logger('TestContext');
        $logger->addHandler(new LogRedis(LogLevel::INFO, $this->redis, ttl: 300));

        $complexData = [
            'string' => 'test',
            'number' => 123,
            'float' => 45.67,
            'boolean' => true,
            'null' => null,
            'array' => [1, 2, 3],
            'nested' => ['a' => 'b', 'c' => ['d' => 'e']],
        ];

        $logger->info('Complex data test', $complexData);

        usleep(100000);

        $keys = $this->redis->keys('Redis*');
        $this->assertGreaterThan(0, count($keys));

        if (count($keys) > 0) {
            $data = $this->redis->get($keys[0]);
            $decoded = json_decode($data, true);

            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('data', $decoded);
        }
    }

    public function testGetRedisReturnsSameInstance(): void
    {
        $handler = new LogRedis(LogLevel::INFO, $this->redis, ttl: 300);

        $this->assertSame($this->redis, $handler->getRedis());
    }
}
