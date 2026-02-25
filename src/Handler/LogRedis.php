<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use Redis;
use RedisException;

/**
 * Class LogRedis
 *
 * This class extends LogCommand and provides functionality to log data into
 * a Redis store with lazy connection. Connection is only established when first log is written.
 * It supports time-to-live (TTL) for the log entries to define their expiration period.
 */
class LogRedis extends LogCommand
{
    private ?Redis $redis = null;
    private string $host;
    private int $port;
    private float $timeout;
    private ?string $password;
    private int $database;
    private int $ttl;
    private bool $isConnected = false;

    /**
     * Constructor method to initialize the class with required dependencies.
     *
     * @param string $logLevel The log level to be used for the logger.
     * @param string $host Redis host (default: 'localhost')
     * @param int $port Redis port (default: 6379)
     * @param float $timeout Connection timeout in seconds (default: 2.5)
     * @param string|null $password Redis password (optional)
     * @param int $database Redis database number (default: 0)
     * @param int $ttl The time-to-live for cached entries in seconds (default: 3600)
     *
     * @return void
     */
    public function __construct(
        string $logLevel,
        string $host = 'localhost',
        int $port = 6379,
        float $timeout = 2.5,
        ?string $password = null,
        int $database = 0,
        int $ttl = 3600
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->password = $password;
        $this->database = $database;
        $this->ttl = $ttl;

        parent::__construct($logLevel);
    }

    protected function log(array $logData): bool
    {
        // Lazy connect: only connect to Redis when first log is written
        if (!$this->isConnected) {
            $this->connect();
        }

        if (!$this->redis) {
            return false;
        }

        try {
            return $this->redis->setex($this->hash(), $this->ttl, $this->encode($logData));
        } catch (RedisException $e) {
            return false;
        }
    }

    private function connect(): void
    {
        try {
            $this->redis = new Redis();

            if (!$this->redis->connect($this->host, $this->port, $this->timeout)) {
                $this->redis = null;
                $this->isConnected = true; // Mark as attempted to avoid retry loops
                return;
            }

            if ($this->password !== null) {
                $this->redis->auth($this->password);
            }

            if ($this->database !== 0) {
                $this->redis->select($this->database);
            }

            $this->isConnected = true;
        } catch (RedisException $e) {
            $this->redis = null;
            $this->isConnected = true; // Mark as attempted
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
     * Get the Redis connection (mainly for testing)
     *
     * @return Redis|null
     */
    public function getRedis(): ?Redis
    {
        return $this->redis;
    }
}
