<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Formatter\LogJsonFormat;
use Exception;

/**
 * Streams logs to RabbitMQ via AMQP.
 *
 * Perfect for:
 * - Real-time log processing and analytics
 * - Distributed log aggregation across microservices
 * - Event-driven architectures
 * - Log streaming to ELK, Splunk, or custom consumers
 *
 * Usage:
 * ```php
 * $connection = new AMQPConnection(['host' => 'localhost']);
 * $connection->connect();
 * $logger->addHandler(new LogRabbitMq($connection, 'logs'));
 * ```
 */
class LogRabbitMq implements StreamableLogCommandInterface
{
    private AMQPConnection $connection;
    private ?AMQPChannel $channel = null;
    private ?AMQPExchange $exchange = null;
    private string $exchangeName;
    private string $context = '';
    private ?LogFormatInterface $format = null;
    private string $handlerId;
    private ?string $handlerName = null;

    /**
     * @param AMQPConnection $connection Connected AMQP connection
     * @param string $exchangeName Exchange name for log publishing
     * @throws Exception If connection is not established
     */
    public function __construct(AMQPConnection $connection, string $exchangeName)
    {
        if (!$connection->isConnected()) {
            throw new Exception('AMQPConnection must be connected before use');
        }

        $this->connection = $connection;
        $this->exchangeName = $exchangeName;
        $this->handlerId = uniqid('handler_', true);
    }

    /**
     * Lazy initialization of AMQP exchange.
     * Creates channel and exchange only when first log message is published.
     *
     * @throws Exception If exchange setup fails
     */
    private function getExchange(): AMQPExchange
    {
        if ($this->exchange === null) {
            $this->channel = new AMQPChannel($this->connection);
            $this->exchange = new AMQPExchange($this->channel);
            $this->exchange->setName($this->exchangeName);
            $this->exchange->setType(AMQP_EX_TYPE_FANOUT);
            $this->exchange->declareExchange();
        }

        return $this->exchange;
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
     * @throws Exception
     */
    private function publish(string $message): void
    {
        // AMQPExchange::publish() returns void; throws exception on failure
        $this->getExchange()->publish(
            $message,
            '', // Routing key (empty for fanout)
            AMQP_NOPARAM,
            ['delivery_mode' => 2] // Persistent messages
        );
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
        // RabbitMQ doesn't use file streams
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
     * Get statistics about the handler.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'platform' => 'rabbitmq',
            'exchange' => $this->exchangeName,
            'context' => $this->context,
        ];
    }
}
