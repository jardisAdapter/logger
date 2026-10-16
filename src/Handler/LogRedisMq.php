<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Formatter\LogJsonFormat;
use Closure;
use Exception;
use Redis;

/**
 * Streams logs to Redis Pub/Sub.
 *
 * Perfect for:
 * - Real-time log processing and analytics
 * - Distributed log aggregation across microservices
 * - Event-driven architectures
 * - Log streaming to custom consumers
 *
 * Usage:
 * ```php
 * $redis = new Redis();
 * $redis->connect('localhost', 6379);
 * $logger->addHandler(new LogRedisMq($redis, 'logs'));
 * ```
 *
 * The first argument is either a ready Redis handle or a provider `Closure(): Redis`.
 * A provider is invoked lazily on the first publish and its result is reused afterwards;
 * construction never calls it. An exception thrown by the provider is not swallowed
 * by the handler's silent failure, it reaches the caller unchanged (and the provider is
 * called again on the next access).
 */
class LogRedisMq implements StreamableLogCommandInterface
{
    /** @var Redis|Closure(): Redis */
    private Redis|Closure $redis;
    private string $channel;
    private string $context = '';
    private ?LogFormatInterface $format = null;
    private string $handlerId;
    private ?string $handlerName = null;

    /**
     * @param Redis|Closure(): Redis $redis Connected Redis instance or lazy provider
     * @param string $channel Redis Pub/Sub channel name
     * @throws Exception If Redis instance is not valid
     */
    public function __construct(Redis|Closure $redis, string $channel)
    {
        $this->redis = $redis;
        $this->channel = $channel;
        $this->handlerId = uniqid('handler_', true);
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
        // Resolved outside the silent-failure block: provider errors reach the caller unchanged.
        $redis = $this->getRedis();

        try {
            $logData = [
                'context' => $this->context,
                'level' => $level,
                'message' => $message,
                'data' => $data ?? [],
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $formattedMessage = $this->format()($logData);
            $this->publish($redis, $formattedMessage);

            return $formattedMessage;
        } catch (Exception $e) {
            // Silent failure - logging should never break application
            return null;
        }
    }

    /**
     * @throws Exception
     */
    private function publish(Redis $redis, string $message): void
    {
        $result = $redis->publish($this->channel, $message);

        if ($result === false) {
            throw new Exception('Failed to publish to Redis channel');
        }
    }

    /**
     * Get the Redis connection (resolves a lazy provider once, memoized on success).
     */
    public function getRedis(): Redis
    {
        $redis = $this->redis;
        if ($redis instanceof Closure) {
            $redis = $redis();
            $this->redis = $redis;
        }

        return $redis;
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
        // Redis Pub/Sub doesn't use file streams
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
            'platform' => 'redis',
            'channel' => $this->channel,
            'context' => $this->context,
        ];
    }
}
