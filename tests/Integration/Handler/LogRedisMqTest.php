<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\LoggerBuilder;
use JardisAdapter\Logger\Handler\LogRedisMq;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Redis;

/**
 * Integration tests for LogRedisMq with Redis Pub/Sub.
 * Requires Redis running (via docker-compose).
 */
class LogRedisMqTest extends TestCase
{
    private ?Redis $redis = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }
    }

    protected function tearDown(): void
    {
        if ($this->redis) {
            $this->redis->close();
        }

        parent::tearDown();
    }

    private function connectRedis(): Redis
    {
        $redis = new Redis();
        $host = getenv('REDIS_HOST') ?: 'localhost';
        $port = 6379;

        if (!$redis->connect($host, $port, 2.0)) {
            $this->fail("Could not connect to Redis at {$host}:{$port}");
        }

        $this->redis = $redis;
        return $redis;
    }

    public function testRedisPublishLogsSuccessfully(): void
    {
        $redis = $this->connectRedis();
        $channel = 'test_logs_redis_' . uniqid();

        $handler = new LogRedisMq($redis, $channel);
        $handler->setContext('TestContext');

        // Publish a log message directly (can't test subscriber in same process)
        $result = $handler(LogLevel::INFO, 'Test Redis log', ['test_id' => 123]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Test Redis log', $result);
        $this->assertStringContainsString('TestContext', $result);
        $this->assertStringContainsString('"test_id":123', $result);
    }

    public function testHandlerReturnsFormattedMessage(): void
    {
        $redis = $this->connectRedis();
        $channel = 'test_return_' . uniqid();

        $handler = new LogRedisMq($redis, $channel);
        $handler->setContext('TestContext');

        $result = $handler(LogLevel::INFO, 'Test message', ['key' => 'value']);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Test message', $result);
        $this->assertStringContainsString('TestContext', $result);
    }

    public function testRedisWithInvalidConnectionThrowsException(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line - Intentionally passing wrong type for test
        new LogRedisMq(new \stdClass(), 'test');
    }

    public function testGetStatistics(): void
    {
        $redis = $this->connectRedis();
        $handler = new LogRedisMq($redis, 'test_channel');
        $handler->setContext('TestContext');

        $stats = $handler->getStatistics();

        $this->assertEquals('redis', $stats['platform']);
        $this->assertEquals('test_channel', $stats['channel']);
        $this->assertEquals('TestContext', $stats['context']);
    }

    public function testSetFormatChangesOutputFormat(): void
    {
        $redis = $this->connectRedis();

        // Test with JSON format (default)
        $jsonHandler = new LogRedisMq($redis, 'test_format_json');
        $result = $jsonHandler(LogLevel::INFO, 'Test JSON', []);
        $this->assertJson($result ?? '');
        $this->assertStringContainsString('"message":"Test JSON"', $result ?? '');

        // Test with human format (multi-line)
        $humanHandler = new LogRedisMq($redis, 'test_format_human');
        $humanFormat = new \JardisAdapter\Logger\Formatter\LogHumanFormat();
        $humanHandler->setFormat($humanFormat);

        $result = $humanHandler(LogLevel::INFO, 'Test Human', []);
        $this->assertStringContainsString('Test Human', $result ?? '');
        // LogHumanFormat uses multi-line format with newlines
        $this->assertStringContainsString("\n", $result ?? '');
    }

    public function testRedisWithLogger(): void
    {
        $redis = $this->connectRedis();
        $channel = 'test_logger_redis_' . uniqid();

        $handler = new LogRedisMq($redis, $channel);
        $handler->setContext('LoggerTest');

        $logger = (new LoggerBuilder('RedisLogger'))
            ->addHandler($handler)
            ->getLogger();

        // Should not throw exception
        $logger->info('Test Redis via Logger', ['integration' => true]);
        $logger->error('Test Redis error', ['error_code' => 500]);

        $this->assertTrue(true);
    }

    public function testRedisMultipleMessages(): void
    {
        $redis = $this->connectRedis();
        $channel = 'test_multiple_redis_' . uniqid();

        $handler = new LogRedisMq($redis, $channel);
        $handler->setContext('MultiMessageTest');

        $logger = (new LoggerBuilder('MultiTest'))
            ->addHandler($handler)
            ->getLogger();

        // Publish multiple messages
        for ($i = 0; $i < 10; $i++) {
            $logger->info("Message {$i}", ['iteration' => $i]);
        }

        // All messages should be published without exception
        $this->assertTrue(true);
    }

    public function testRedisMultipleLevels(): void
    {
        $redis = $this->connectRedis();
        $channel = 'test_levels_redis_' . uniqid();

        $handler = new LogRedisMq($redis, $channel);
        $handler->setContext('LevelsTest');

        $logger = (new LoggerBuilder('LevelsLogger'))
            ->addHandler($handler)
            ->getLogger();

        // Test all log levels
        $logger->debug('Debug message', ['level' => 'debug']);
        $logger->info('Info message', ['level' => 'info']);
        $logger->notice('Notice message', ['level' => 'notice']);
        $logger->warning('Warning message', ['level' => 'warning']);
        $logger->error('Error message', ['level' => 'error']);
        $logger->critical('Critical message', ['level' => 'critical']);
        $logger->alert('Alert message', ['level' => 'alert']);
        $logger->emergency('Emergency message', ['level' => 'emergency']);

        $this->assertTrue(true);
    }
}
