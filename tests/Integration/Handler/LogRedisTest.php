<?php

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\Handler\LogRedis;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Redis;

class LogRedisTest extends TestCase
{
    private string $redisHost;
    private int $redisPort;
    private ?string $redisPassword;
    private ?Redis $redis = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Load from ENV or use defaults
        // Note: Inside Docker network, we always use internal port 6379
        $this->redisHost = getenv('REDIS_HOST') ?: 'redis';
        $this->redisPort = 6379; // Always use internal port inside Docker network
        $this->redisPassword = getenv('REDIS_PASSWORD') ?: null;

        // Create Redis connection for cleanup
        $this->redis = new Redis();
        try {
            echo "\nTrying to connect to {$this->redisHost}:{$this->redisPort}...\n";

            if (!$this->redis->connect($this->redisHost, $this->redisPort, 2.5)) {
                $this->markTestSkipped("Redis server not available at {$this->redisHost}:{$this->redisPort}");
            }

            if ($this->redisPassword) {
                $this->redis->auth($this->redisPassword);
            }

            // Flush test database
            $this->redis->select(1); // Use database 1 for tests
            $this->redis->flushDB();

            echo "Redis connected successfully!\n";
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

        $redisHandler = new LogRedis(
            logLevel: LogLevel::INFO,
            host: $this->redisHost,
            port: $this->redisPort,
            password: $this->redisPassword,
            database: 1,
            ttl: 300
        );

        $logger->addHandler($redisHandler);
        $logger->info('Test message', ['key' => 'value', 'number' => 42]);

        // Give Redis a moment to process
        usleep(100000); // 100ms

        // Verify data was written to Redis
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

        $redisHandler = new LogRedis(
            logLevel: LogLevel::INFO,
            host: $this->redisHost,
            port: $this->redisPort,
            password: $this->redisPassword,
            database: 1,
            ttl: 2 // 2 seconds
        );

        $logger->addHandler($redisHandler);
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

        $redisHandler = new LogRedis(
            logLevel: LogLevel::DEBUG,
            host: $this->redisHost,
            port: $this->redisPort,
            password: $this->redisPassword,
            database: 1,
            ttl: 300
        );

        $logger->addHandler($redisHandler);

        // Log multiple messages
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

        $redisHandler = new LogRedis(
            logLevel: LogLevel::INFO,
            host: $this->redisHost,
            port: $this->redisPort,
            password: $this->redisPassword,
            database: 1,
            ttl: 300
        );

        $logger->addHandler($redisHandler);

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

    public function testConnectionWithPassword(): void
    {
        if (!$this->redisPassword) {
            $this->markTestSkipped('No Redis password configured');
        }

        $logger = new Logger('TestContext');

        $redisHandler = new LogRedis(
            logLevel: LogLevel::INFO,
            host: $this->redisHost,
            port: $this->redisPort,
            password: $this->redisPassword,
            database: 1
        );

        $logger->addHandler($redisHandler);
        $logger->info('Password auth test');

        usleep(100000);

        $keys = $this->redis->keys('Redis*');
        $this->assertGreaterThan(0, count($keys));
    }

    public function testLazyConnectionNotEstablishedUntilLog(): void
    {
        $redisHandler = new LogRedis(
            logLevel: LogLevel::INFO,
            host: $this->redisHost,
            port: $this->redisPort,
            database: 1
        );

        // Connection should not be established yet
        $this->assertNull($redisHandler->getRedis());

        // Now trigger a log
        $logger = new Logger('TestContext');
        $logger->addHandler($redisHandler);
        $logger->info('First log');

        usleep(50000);

        // Now connection should be established
        $this->assertInstanceOf(Redis::class, $redisHandler->getRedis());
    }

    public function testConnectionFailureHandledGracefully(): void
    {
        $logger = new Logger('TestContext');

        $redisHandler = new LogRedis(
            logLevel: LogLevel::INFO,
            host: 'non-existent-host',
            port: 9999,
            timeout: 0.5
        );

        $logger->addHandler($redisHandler);

        // Should not throw exception
        $logger->info('This should fail gracefully');

        $this->assertTrue(true); // Test passes if no exception thrown
    }
}
