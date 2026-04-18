<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use Redis;
use RedisException;

/**
 * Class LogRedis
 *
 * Stores log entries in Redis with configurable TTL.
 * The Redis connection must be injected from the outside (Dependency Inversion).
 */
class LogRedis extends LogCommand
{
    private Redis $redis;
    private int $ttl;

    /**
     * @param string $logLevel The log level to be used for the logger.
     * @param Redis $redis Connected Redis instance (caller manages connection lifecycle)
     * @param int $ttl The time-to-live for log entries in seconds (default: 3600)
     */
    public function __construct(
        string $logLevel,
        Redis $redis,
        int $ttl = 3600
    ) {
        $this->redis = $redis;
        $this->ttl = $ttl;

        parent::__construct($logLevel);
    }

    protected function log(array $logData): bool
    {
        try {
            return $this->redis->setex($this->hash(), $this->ttl, $this->encode($logData));
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
     * Get the Redis connection.
     */
    public function getRedis(): Redis
    {
        return $this->redis;
    }
}
