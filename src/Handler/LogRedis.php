<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use Closure;
use Redis;
use RedisException;

/**
 * Class LogRedis
 *
 * Stores log entries in Redis with configurable TTL.
 * The Redis connection must be injected from the outside (Dependency Inversion):
 * either a ready Redis handle or a provider `Closure(): Redis`. A provider is invoked
 * lazily on the first log write (or getRedis()) and its result is reused afterwards;
 * construction never calls it. An exception thrown by the provider is not swallowed
 * by the handler, it reaches the caller unchanged (and the provider is called again
 * on the next access).
 */
class LogRedis extends LogCommand
{
    /** @var Redis|Closure(): Redis */
    private Redis|Closure $redis;
    private int $ttl;

    /**
     * @param string $logLevel The log level to be used for the logger.
     * @param Redis|Closure(): Redis $redis Connected Redis instance (caller manages connection lifecycle)
     *                                      or a lazy provider
     * @param int $ttl The time-to-live for log entries in seconds (default: 3600)
     */
    public function __construct(
        string $logLevel,
        Redis|Closure $redis,
        int $ttl = 3600
    ) {
        $this->redis = $redis;
        $this->ttl = $ttl;

        parent::__construct($logLevel);
    }

    protected function log(array $logData): bool
    {
        $redis = $this->getRedis();
        try {
            return $redis->setex($this->hash(), $this->ttl, $this->encode($logData));
        } catch (RedisException $e) {
            return false;
        }
    }

    private function hash(): string
    {
        return 'Redis' . uniqid('', true);
    }

    /**
     * Encodes the given value to a string format using JSON encoding,
     * and falls back to serialization if encoding fails.
     *
     * @param mixed $value The value to be encoded.
     *
     * @return string The encoded string representation of the value.
     */
    protected function encode($value): string
    {
        $result = json_encode($value);
        if ($result === false || json_last_error() !== JSON_ERROR_NONE) {
            $result = serialize($value);
        }

        return $result;
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
}
