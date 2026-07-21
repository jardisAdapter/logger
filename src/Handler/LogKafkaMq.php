<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Formatter\LogJsonFormat;
use Exception;
use RdKafka\Producer;
use RdKafka\ProducerTopic;

/**
 * Streams logs to Apache Kafka.
 *
 * Perfect for:
 * - Real-time log processing and analytics
 * - Distributed log aggregation across microservices
 * - Event-driven architectures
 * - Log streaming to ELK, Splunk, or custom consumers
 * - High-throughput log ingestion
 *
 * Usage:
 * ```php
 * $producer = new RdKafka\Producer();
 * $producer->addBrokers('localhost:9092');
 * $logger->addHandler(new LogKafkaMq($producer, 'logs'));
 * ```
 */
class LogKafkaMq implements StreamableLogCommandInterface
{
    private Producer $producer;
    private ?ProducerTopic $topic = null;
    private string $topicName;
    private string $context = '';
    private ?LogFormatInterface $format = null;
    private string $handlerId;
    private ?string $handlerName = null;

    /**
     * @param Producer $producer Configured Kafka producer instance
     * @param string $topicName Kafka topic name
     */
    public function __construct(Producer $producer, string $topicName)
    {
        $this->producer = $producer;
        $this->topicName = $topicName;
        $this->handlerId = uniqid('handler_', true);
    }

    /**
     * Lazy initialization of Kafka topic.
     * Creates topic only when first log message is published.
     */
    private function getTopic(): ProducerTopic
    {
        return $this->topic ??= $this->producer->newTopic($this->topicName);
    }

    /**
     * Lazy initialization of formatter.
     */
    private function format(): LogFormatInterface
    {
        return $this->format ??= new LogJsonFormat();
    }

    public function __invoke(string $level, string $message, ?array $data = []): ?string
    {
        try {
            $logData = [
                'context' => $this->context,
                'level' => $level,
                'message' => $message,
                'data' => $data ?? [],
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $formattedMessage = $this->format()($logData);
            $this->publish($formattedMessage);

            return $formattedMessage;
        } catch (Exception $e) {
            // Silent failure - logging should never break application
            return null;
        }
    }

    /**
     * Publishes a message to Kafka topic.
     *
     * Uses automatic partition assignment (-1, equivalent to RD_KAFKA_PARTITION_UA constant)
     * allowing Kafka to distribute messages across partitions based on its internal algorithm.
     *
     * @param string $message The formatted log message to publish
     * @throws Exception If publishing fails
     */
    private function publish(string $message): void
    {
        // RD_KAFKA_PARTITION_UA (-1) lets Kafka choose partition automatically
        $this->getTopic()->produce(-1, 0, $message);

        // Trigger delivery (non-blocking)
        $this->producer->poll(0);
    }

    public function setContext(string $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function setFormat(LogFormatInterface $logFormat): self
    {
        $this->format = $logFormat;
        return $this;
    }

    /**
     * @param resource $stream
     */
    public function setStream($stream): self
    {
        // Kafka doesn't use file streams
        return $this;
    }

    public function getHandlerId(): string
    {
        return $this->handlerId;
    }

    public function setHandlerName(?string $name): self
    {
        $this->handlerName = $name;
        return $this;
    }

    public function getHandlerName(): ?string
    {
        return $this->handlerName;
    }

    /**
     * Flush pending messages.
     * Call this before application shutdown to ensure all messages are delivered.
     *
     * @param int $timeoutMs Timeout in milliseconds
     */
    public function flush(int $timeoutMs = 10000): void
    {
        $this->producer->flush($timeoutMs);
    }

    /**
     * Get statistics about the handler.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'platform' => 'kafka',
            'topic' => $this->topicName,
            'context' => $this->context,
        ];
    }
}
