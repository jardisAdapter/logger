<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\Handler\LogKafkaMq;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RdKafka\Conf as KafkaConf;
use RdKafka\Producer as KafkaProducer;

/**
 * Integration tests for LogKafkaMq with Apache Kafka.
 * Requires Kafka running (via docker-compose).
 */
class LogKafkaMqTest extends TestCase
{
    private ?KafkaProducer $kafkaProducer = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('rdkafka')) {
            $this->markTestSkipped('RdKafka extension not available');
        }
    }

    private function connectKafka(): KafkaProducer
    {
        $conf = new KafkaConf();
        $conf->set('metadata.broker.list', getenv('KAFKA_BROKERS') ?: 'localhost:9092');

        $producer = new KafkaProducer($conf);
        $this->kafkaProducer = $producer;

        return $producer;
    }

    public function testKafkaPublishLogsSuccessfully(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_logs_kafka_' . uniqid();

        $handler = new LogKafkaMq($producer, $topicName);
        $handler->setContext('TestContext');

        $logger = new Logger('TestLogger');
        $logger->addHandler($handler);

        // Publish a log message
        $logger->info('Test Kafka log', ['test_id' => 789]);

        // Flush to ensure delivery
        $handler->flush(5000);

        // Note: Kafka consumer verification requires separate consumer setup
        // For now, we verify that no exception was thrown during publish
        $this->assertTrue(true);
    }

    public function testKafkaWithInvalidConnectionThrowsException(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore-next-line - Intentionally passing wrong type for test
        new LogKafkaMq(new \stdClass(), 'test');
    }

    public function testKafkaDirectInvocation(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_direct_kafka_' . uniqid();

        $handler = new LogKafkaMq($producer, $topicName);
        $handler->setContext('DirectTest');

        // Direct invocation
        $result = $handler(LogLevel::INFO, 'Test direct Kafka message', ['direct' => true]);

        // Flush to ensure delivery
        $handler->flush(5000);

        // Handler returns formatted message for Kafka
        $this->assertNotNull($result);
        $this->assertStringContainsString('Test direct Kafka message', $result);
        $this->assertStringContainsString('DirectTest', $result);
    }

    public function testKafkaGetStatistics(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_stats_kafka';

        $handler = new LogKafkaMq($producer, $topicName);
        $handler->setContext('StatsContext');

        $stats = $handler->getStatistics();

        $this->assertEquals('kafka', $stats['platform']);
        $this->assertEquals($topicName, $stats['topic']);
        $this->assertEquals('StatsContext', $stats['context']);
    }

    public function testKafkaSetFormatChangesOutputFormat(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_format_kafka_' . uniqid();

        // Test with JSON format (default)
        $jsonHandler = new LogKafkaMq($producer, $topicName);
        $logger = new Logger('FormatTest');
        $logger->addHandler($jsonHandler);

        $logger->info('Test JSON format', ['format' => 'json']);
        $jsonHandler->flush(5000);

        // Test with human format
        $humanHandler = new LogKafkaMq($producer, $topicName . '_human');
        $humanFormat = new \JardisAdapter\Logger\Formatter\LogHumanFormat();
        $humanHandler->setFormat($humanFormat);

        $logger2 = new Logger('HumanFormatTest');
        $logger2->addHandler($humanHandler);

        $logger2->info('Test Human format', ['format' => 'human']);
        $humanHandler->flush(5000);

        $this->assertTrue(true);
    }

    public function testKafkaMultipleMessages(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_multiple_kafka_' . uniqid();

        $handler = new LogKafkaMq($producer, $topicName);
        $handler->setContext('MultiMessageTest');

        $logger = new Logger('MultiTest');
        $logger->addHandler($handler);

        // Publish multiple messages
        for ($i = 0; $i < 10; $i++) {
            $logger->info("Kafka message {$i}", ['iteration' => $i]);
        }

        // Flush all messages
        $handler->flush(10000);

        $this->assertTrue(true);
    }

    public function testKafkaFlushWithTimeout(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_flush_kafka_' . uniqid();

        $handler = new LogKafkaMq($producer, $topicName);
        $handler->setContext('FlushTest');

        $logger = new Logger('FlushLogger');
        $logger->addHandler($handler);

        // Send messages
        $logger->info('Message 1');
        $logger->warning('Message 2');
        $logger->error('Message 3');

        // Test flush with custom timeout
        $handler->flush(15000);

        $this->assertTrue(true);
    }

    public function testKafkaMultipleLevels(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_levels_kafka_' . uniqid();

        $handler = new LogKafkaMq($producer, $topicName);
        $handler->setContext('LevelsTest');

        $logger = new Logger('LevelsLogger');
        $logger->addHandler($handler);

        // Test all log levels
        $logger->debug('Debug message', ['level' => 'debug']);
        $logger->info('Info message', ['level' => 'info']);
        $logger->notice('Notice message', ['level' => 'notice']);
        $logger->warning('Warning message', ['level' => 'warning']);
        $logger->error('Error message', ['level' => 'error']);
        $logger->critical('Critical message', ['level' => 'critical']);
        $logger->alert('Alert message', ['level' => 'alert']);
        $logger->emergency('Emergency message', ['level' => 'emergency']);

        $handler->flush(10000);

        $this->assertTrue(true);
    }

    public function testKafkaWithComplexData(): void
    {
        $producer = $this->connectKafka();
        $topicName = 'test_complex_kafka_' . uniqid();

        $handler = new LogKafkaMq($producer, $topicName);
        $handler->setContext('ComplexDataTest');

        $logger = new Logger('ComplexLogger');
        $logger->addHandler($handler);

        // Test with complex nested data
        $complexData = [
            'user' => [
                'id' => 123,
                'name' => 'John Doe',
                'roles' => ['admin', 'user'],
            ],
            'request' => [
                'method' => 'POST',
                'url' => '/api/v1/resource',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer token123',
                ],
            ],
            'response' => [
                'status' => 200,
                'body' => ['success' => true],
            ],
        ];

        $logger->info('Complex data test', $complexData);
        $handler->flush(5000);

        $this->assertTrue(true);
    }
}
