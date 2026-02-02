<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use AMQPConnection;
use JardisAdapter\Logger\Handler\LogRabbitMq;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Integration tests for LogRabbitMq with RabbitMQ.
 * Requires RabbitMQ running (via docker-compose).
 */
class LogRabbitMqTest extends TestCase
{
    private ?AMQPConnection $amqpConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('amqp')) {
            $this->markTestSkipped('AMQP extension not available');
        }
    }

    protected function tearDown(): void
    {
        if ($this->amqpConnection && $this->amqpConnection->isConnected()) {
            $this->amqpConnection->disconnect();
        }

        parent::tearDown();
    }

    private function connectRabbitMQ(): AMQPConnection
    {
        $connection = new AMQPConnection([
            'host' => getenv('RABBITMQ_HOST') ?: 'localhost',
            'port' => 5672,
            'login' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
        ]);

        $connection->connect();

        if (!$connection->isConnected()) {
            $this->fail('Could not connect to RabbitMQ');
        }

        $this->amqpConnection = $connection;
        return $connection;
    }

    public function testRabbitMQPublishLogsSuccessfully(): void
    {
        $connection = $this->connectRabbitMQ();
        $exchangeName = 'test_logs_rabbitmq_' . uniqid();

        $handler = new LogRabbitMq($connection, $exchangeName);
        $handler->setContext('TestContext');

        // Publish a log message directly
        $result = $handler(LogLevel::INFO, 'Test RabbitMQ log', ['test_id' => 456]);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Test RabbitMQ log', $result);
        $this->assertStringContainsString('TestContext', $result);
        $this->assertStringContainsString('"test_id":456', $result);
    }

    public function testRabbitMQWithInvalidConnectionThrowsException(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line - Intentionally passing wrong type for test
        new LogRabbitMq(new \stdClass(), 'test');
    }

    public function testRabbitMQWithLogger(): void
    {
        $connection = $this->connectRabbitMQ();
        $exchangeName = 'test_logger_rabbitmq_' . uniqid();

        $handler = new LogRabbitMq($connection, $exchangeName);
        $handler->setContext('LoggerTest');

        $logger = new Logger('RabbitMQLogger');
        $logger->addHandler($handler);

        // Should not throw exception
        $logger->info('Test RabbitMQ via Logger', ['integration' => true]);
        $logger->error('Test RabbitMQ error', ['error_code' => 500]);

        $this->assertTrue(true);
    }

    public function testRabbitMQGetStatistics(): void
    {
        $connection = $this->connectRabbitMQ();
        $exchangeName = 'test_stats_rabbitmq';

        $handler = new LogRabbitMq($connection, $exchangeName);
        $handler->setContext('StatsContext');

        $stats = $handler->getStatistics();

        $this->assertEquals('rabbitmq', $stats['platform']);
        $this->assertEquals($exchangeName, $stats['exchange']);
        $this->assertEquals('StatsContext', $stats['context']);
    }

    public function testRabbitMQSetFormatChangesOutputFormat(): void
    {
        $connection = $this->connectRabbitMQ();
        $exchangeName = 'test_format_rabbitmq_' . uniqid();

        // Test with JSON format (default)
        $jsonHandler = new LogRabbitMq($connection, $exchangeName);
        $result = $jsonHandler(LogLevel::INFO, 'Test JSON', []);
        $this->assertJson($result ?? '');
        $this->assertStringContainsString('"message":"Test JSON"', $result ?? '');

        // Test with human format (multi-line)
        $humanHandler = new LogRabbitMq($connection, $exchangeName . '_human');
        $humanFormat = new \JardisAdapter\Logger\Formatter\LogHumanFormat();
        $humanHandler->setFormat($humanFormat);

        $result = $humanHandler(LogLevel::INFO, 'Test Human', []);
        $this->assertStringContainsString('Test Human', $result ?? '');
        $this->assertStringContainsString("\n", $result ?? '');
    }

    public function testRabbitMQMultipleMessages(): void
    {
        $connection = $this->connectRabbitMQ();
        $exchangeName = 'test_multiple_rabbitmq_' . uniqid();

        $handler = new LogRabbitMq($connection, $exchangeName);
        $handler->setContext('MultiMessageTest');

        $logger = new Logger('MultiTest');
        $logger->addHandler($handler);

        // Publish multiple messages
        for ($i = 0; $i < 10; $i++) {
            $logger->info("Message {$i}", ['iteration' => $i]);
        }

        // All messages should be published without exception
        $this->assertTrue(true);
    }

    public function testRabbitMQUnconnectedConnectionThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AMQPConnection must be connected');

        $connection = new AMQPConnection([
            'host' => 'localhost',
            'port' => 5672,
        ]);
        // Don't connect

        new LogRabbitMq($connection, 'test');
    }
}
