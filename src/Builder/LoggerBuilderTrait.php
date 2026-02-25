<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Builder;

use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Handler;

/**
 * Trait providing fluent API methods for adding log handlers.
 *
 * This trait creates handlers directly, providing real methods (not magic methods)
 * for perfect IDE autocomplete support.
 */
trait LoggerBuilderTrait
{
    /**
     * Must be implemented by the using class.
     * @param LogCommandInterface $logCommand
     * @return self
     */
    abstract public function addHandler(LogCommandInterface $logCommand): self;

    /**
     * Configures a handler with optional name and format, then adds it to the logger.
     *
     * @param LogCommandInterface $handler The handler to configure
     * @param string|null $name Optional handler name
     * @param LogFormatInterface|null $format Optional custom formatter
     * @return void
     */
    private function configureHandler(
        LogCommandInterface $handler,
        ?string $name = null,
        ?LogFormatInterface $format = null
    ): void {
        if ($name !== null) {
            $handler->setHandlerName($name);
        }

        if ($format !== null) {
            $handler->setFormat($format);
        }

        $this->addHandler($handler);
    }

    /**
     * Adds a file handler.
     */
    public function addFile(
        string $logLevel,
        string $filePath,
        ?string $name = null,
        ?LogFormatInterface $format = null
    ): self {
        $handler = new Handler\LogFile($logLevel, $filePath);
        $this->configureHandler($handler, $name, $format);
        return $this;
    }

    /**
     * Adds a Slack handler.
     */
    public function addSlack(
        string $logLevel,
        string $webhookUrl,
        ?string $name = null,
        int $timeout = 10,
        int $retryAttempts = 3
    ): self {
        $handler = new Handler\LogSlack($logLevel, $webhookUrl, $timeout, $retryAttempts);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a Microsoft Teams handler.
     */
    public function addTeams(
        string $logLevel,
        string $webhookUrl,
        ?string $name = null,
        int $timeout = 10,
        int $retryAttempts = 3
    ): self {
        $handler = new Handler\LogTeams($logLevel, $webhookUrl, $timeout, $retryAttempts);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a Grafana Loki handler.
     *
     * @param string $logLevel
     * @param string $lokiUrl
     * @param array<string, string> $staticLabels
     * @param string|null $name
     * @param int $timeout
     * @param int $retryAttempts
     * @return self
     */
    public function addLoki(
        string $logLevel,
        string $lokiUrl,
        array $staticLabels = [],
        ?string $name = null,
        int $timeout = 10,
        int $retryAttempts = 3
    ): self {
        $handler = new Handler\LogLoki($logLevel, $lokiUrl, $staticLabels, $timeout, $retryAttempts);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a Redis handler.
     */
    public function addRedis(
        string $logLevel,
        string $host = 'localhost',
        int $port = 6379,
        ?string $name = null,
        float $timeout = 2.5,
        ?string $password = null,
        int $database = 0,
        int $ttl = 3600
    ): self {
        $handler = new Handler\LogRedis($logLevel, $host, $port, $timeout, $password, $database, $ttl);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a generic HTTP webhook handler.
     *
     * @param string $logLevel
     * @param string $url
     * @param string|null $name
     * @param string $method
     * @param array<string, string> $headers
     * @param int $timeout
     * @param int $retryAttempts
     * @param int $retryDelay
     * @param callable|null $bodyFormatter
     * @return self
     */
    public function addWebhook(
        string $logLevel,
        string $url,
        ?string $name = null,
        string $method = 'POST',
        array $headers = [],
        int $timeout = 10,
        int $retryAttempts = 3,
        int $retryDelay = 1,
        ?callable $bodyFormatter = null
    ): self {
        $handler = new Handler\LogWebhook(
            $logLevel,
            $url,
            $method,
            $headers,
            $timeout,
            $retryAttempts,
            $retryDelay,
            $bodyFormatter
        );
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a console handler.
     */
    public function addConsole(
        string $logLevel,
        ?string $name = null,
        ?LogFormatInterface $format = null
    ): self {
        $handler = new Handler\LogConsole($logLevel);
        $this->configureHandler($handler, $name, $format);
        return $this;
    }

    /**
     * Adds a syslog handler.
     */
    public function addSyslog(
        string $logLevel,
        ?string $name = null
    ): self {
        $handler = new Handler\LogSyslog($logLevel);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a browser console handler (ChromeLogger).
     */
    public function addBrowserConsole(
        string $logLevel,
        ?string $name = null
    ): self {
        $handler = new Handler\LogBrowserConsole($logLevel);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a database handler.
     */
    public function addDatabase(
        string $logLevel,
        \PDO $pdo,
        ?string $logTable = null,
        ?string $name = null
    ): self {
        $handler = new Handler\LogDatabase($logLevel, $pdo, $logTable);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds an email handler.
     */
    public function addEmail(
        string $logLevel,
        string $toEmail,
        string $fromEmail,
        string $subject = 'Application Log',
        string $smtpHost = 'localhost',
        int $smtpPort = 1025,
        string $smtpUsername = '',
        string $smtpPassword = '',
        string $fromName = 'Logger',
        bool $useHtml = false,
        bool $useTls = false,
        int $rateLimitSeconds = 60,
        ?string $name = null
    ): self {
        $handler = new Handler\LogEmail(
            $logLevel,
            $toEmail,
            $fromEmail,
            $subject,
            $smtpHost,
            $smtpPort,
            $smtpUsername,
            $smtpPassword,
            $fromName,
            $useHtml,
            $useTls,
            $rateLimitSeconds
        );
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds an error_log handler.
     */
    public function addErrorLog(
        string $logLevel,
        ?string $name = null
    ): self {
        $handler = new Handler\LogErrorLog($logLevel);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a Kafka message queue handler.
     */
    public function addKafkaMq(
        \RdKafka\Producer $producer,
        string $topicName,
        ?string $name = null
    ): self {
        $handler = new Handler\LogKafkaMq($producer, $topicName);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a null handler (discards all logs).
     */
    public function addNull(
        string $logLevel,
        ?string $name = null
    ): self {
        $handler = new Handler\LogNull($logLevel);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a RabbitMQ message queue handler.
     */
    public function addRabbitMq(
        \AMQPConnection $connection,
        string $exchangeName,
        ?string $name = null
    ): self {
        $handler = new Handler\LogRabbitMq($connection, $exchangeName);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a Redis Pub/Sub handler.
     */
    public function addRedisMq(
        \Redis $redis,
        string $channel,
        ?string $name = null
    ): self {
        $handler = new Handler\LogRedisMq($redis, $channel);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a LogStash handler.
     *
     * @param string $logLevel
     * @param string $logStashHost
     * @param int $logStashPort
     * @param array<string, mixed>|null $bindTo
     * @param string|null $name
     * @return self
     */
    public function addStash(
        string $logLevel,
        string $logStashHost,
        int $logStashPort,
        ?array $bindTo = null,
        ?string $name = null
    ): self {
        $handler = new Handler\LogStash($logLevel, $logStashHost, $logStashPort, $bindTo);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a FingersCrossed handler (buffers logs, writes only on activation).
     *
     * Buffers all logs and only writes them when activation level is reached.
     * Perfect for production: see debug context only when errors occur.
     *
     * @param StreamableLogCommandInterface $wrappedHandler Handler to use when activated
     * @param string $activationLevel Log level that triggers buffer flush (default: ERROR)
     * @param int $bufferSize Maximum buffer size (default: 100 messages)
     * @param bool $stopBufferingAfterActivation If true, switches to pass-through after activation
     * @param string|null $name Optional handler name
     * @return self
     */
    public function addFingersCrossed(
        StreamableLogCommandInterface $wrappedHandler,
        string $activationLevel = \Psr\Log\LogLevel::ERROR,
        int $bufferSize = 100,
        bool $stopBufferingAfterActivation = true,
        ?string $name = null
    ): self {
        $handler = new Handler\LogFingersCrossed(
            $wrappedHandler,
            $activationLevel,
            $bufferSize,
            $stopBufferingAfterActivation
        );
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a Sampling handler (reduces log volume).
     *
     * Strategies:
     * - 'rate': Log first N messages per second
     * - 'percentage': Randomly sample X% of logs
     * - 'smart': Always log ERROR+, sample INFO/DEBUG
     * - 'fingerprint': Deduplicate identical logs within time window
     *
     * @param StreamableLogCommandInterface $wrappedHandler Handler to wrap with sampling
     * @param string $strategy Sampling strategy (rate|percentage|smart|fingerprint)
     * @param array<string, mixed> $config Strategy-specific configuration
     * @param string|null $name Optional handler name
     * @return self
     */
    public function addSampling(
        StreamableLogCommandInterface $wrappedHandler,
        string $strategy = 'smart',
        array $config = [],
        ?string $name = null
    ): self {
        $handler = new Handler\LogSampling($wrappedHandler, $strategy, $config);
        $this->configureHandler($handler, $name);
        return $this;
    }

    /**
     * Adds a Conditional handler (routes logs based on conditions).
     *
     * Routes logs to different handlers based on runtime conditions.
     *
     * Example:
     * ```php
     * $logger->addConditional([
     *     [fn($level) => $level >= LogLevel::ERROR, $slackHandler],
     *     [fn($level, $msg, $ctx) => $ctx['user_id'] === 'admin', $debugHandler],
     * ], $defaultHandler, 'conditional_router');
     * ```
     *
     * @param array<int, array{0: callable, 1: StreamableLogCommandInterface}> $conditionalHandlers
     *        Array of [condition, handler] pairs
     * @param StreamableLogCommandInterface|null $fallbackHandler Handler to use if no condition matches
     * @param string|null $name Optional handler name
     * @return self
     */
    public function addConditional(
        array $conditionalHandlers,
        ?StreamableLogCommandInterface $fallbackHandler = null,
        ?string $name = null
    ): self {
        $handler = new Handler\LogConditional($conditionalHandlers, $fallbackHandler);
        $this->configureHandler($handler, $name);
        return $this;
    }
}
