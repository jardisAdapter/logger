<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Contract\LogFormatInterface;
use JardisAdapter\Logger\Contract\StreamableLogCommandInterface;
use JardisAdapter\Logger\Data\LogLevel;

/**
 * Intelligent log sampling handler that wraps another handler and reduces log volume
 * while maintaining visibility of important messages.
 *
 * Sampling Strategies:
 * - 'rate': Log first N messages per second, drop rest
 * - 'percentage': Randomly sample X% of logs
 * - 'smart': Always log ERROR+, sample INFO/DEBUG
 * - 'fingerprint': Deduplicate identical logs within time window
 *
 * Uses lazy connection - wrapped handler is only invoked when logs pass sampling filter.
 */
class LogSampling implements StreamableLogCommandInterface
{
    public const STRATEGY_RATE = 'rate';
    public const STRATEGY_PERCENTAGE = 'percentage';
    public const STRATEGY_SMART = 'smart';
    public const STRATEGY_FINGERPRINT = 'fingerprint';

    private StreamableLogCommandInterface $wrappedHandler;
    private string $strategy;
    /** @var array<string, mixed> */
    private array $config;
    private string $handlerId;
    private ?string $handlerName = null;

    // Rate limiting state
    private int $currentSecond = 0;
    private int $messageCountThisSecond = 0;

    // Fingerprint state
    /** @var array<string, array{count: int, first_seen: int, last_seen: int}> */
    private array $fingerprints = [];

    /**
     * @param StreamableLogCommandInterface $wrappedHandler The handler to wrap with sampling
     * @param string $strategy Sampling strategy: 'rate', 'percentage', 'smart', 'fingerprint'
     * @param array<string, mixed> $config Configuration array with strategy-specific options:
     *   - rate: ['rate' => 100] (logs per second)
     *   - percentage: ['percentage' => 10] (10% sampling)
     *   - smart: ['alwaysLogLevels' => ['error', 'critical'], 'samplePercentage' => 10]
     *   - fingerprint: ['window' => 60] (seconds), ['similarity' => 0.95]
     */
    public function __construct(
        StreamableLogCommandInterface $wrappedHandler,
        string $strategy = self::STRATEGY_SMART,
        array $config = []
    ) {
        $this->wrappedHandler = $wrappedHandler;
        $this->strategy = $strategy;
        $this->config = array_merge($this->getDefaultConfig($strategy), $config);
        $this->handlerId = uniqid('handler_', true);
    }

    /**
     * @return string|array<string, mixed>|null
     */
    public function __invoke(string $level, string $message, ?array $data = [])
    {
        if (!$this->shouldLog($level, $message, $data)) {
            return null;
        }

        return ($this->wrappedHandler)($level, $message, $data);
    }

    public function setContext(string $context): self
    {
        $this->wrappedHandler->setContext($context);
        return $this;
    }

    public function setFormat(LogFormatInterface $logFormat): self
    {
        $this->wrappedHandler->setFormat($logFormat);
        return $this;
    }

    /**
     * @param resource $stream
     */
    public function setStream($stream): self
    {
        $this->wrappedHandler->setStream($stream);
        return $this;
    }

    /**
     * Determines if a log message should be processed based on the configured sampling strategy.
     *
     * @param string $level Log level (e.g., 'info', 'error')
     * @param string $message Log message
     * @param array<string, mixed>|null $data Additional context data
     * @return bool True if log should be processed, false if it should be dropped
     */
    private function shouldLog(string $level, string $message, ?array $data): bool
    {
        return match ($this->strategy) {
            self::STRATEGY_RATE => $this->rateSampling(),
            self::STRATEGY_PERCENTAGE => $this->percentageSampling(),
            self::STRATEGY_SMART => $this->smartSampling($level),
            self::STRATEGY_FINGERPRINT => $this->fingerprintSampling($level, $message),
            default => true,
        };
    }

    /**
     * Rate-based sampling: Log first N messages per second.
     */
    private function rateSampling(): bool
    {
        $currentSecond = (int) time();

        // Reset counter if we moved to a new second
        if ($currentSecond !== $this->currentSecond) {
            $this->currentSecond = $currentSecond;
            $this->messageCountThisSecond = 0;
        }

        $this->messageCountThisSecond++;

        return $this->messageCountThisSecond <= $this->config['rate'];
    }

    /**
     * Percentage-based sampling: Randomly sample X% of logs.
     */
    private function percentageSampling(): bool
    {
        return (random_int(1, 100) <= $this->config['percentage']);
    }

    /**
     * Smart sampling: Always log ERROR+, sample INFO/DEBUG.
     */
    private function smartSampling(string $level): bool
    {
        $levelValue = LogLevel::COLLECTION[strtolower($level)] ?? 4;
        $alwaysLogLevels = $this->config['alwaysLogLevels'];

        // Always log if level is in the always-log list
        foreach ($alwaysLogLevels as $alwaysLevel) {
            $alwaysLevelValue = LogLevel::COLLECTION[strtolower($alwaysLevel)] ?? 4;
            if ($levelValue >= $alwaysLevelValue) {
                return true;
            }
        }

        // Sample other levels
        return (random_int(1, 100) <= $this->config['samplePercentage']);
    }

    /**
     * Fingerprint-based sampling: Deduplicate identical logs within time window.
     */
    private function fingerprintSampling(string $level, string $message): bool
    {
        $fingerprint = $this->generateFingerprint($level, $message);
        $now = time();
        $window = $this->config['window'];

        // Clean up old fingerprints
        $this->cleanupFingerprints($now, $window);

        // Check if we've seen this fingerprint recently
        if (isset($this->fingerprints[$fingerprint])) {
            $entry = &$this->fingerprints[$fingerprint];
            $entry['count']++;
            $entry['last_seen'] = $now;
            return false; // Drop duplicate
        }

        // New fingerprint
        $this->fingerprints[$fingerprint] = [
            'count' => 1,
            'first_seen' => $now,
            'last_seen' => $now,
        ];

        return true;
    }

    /**
     * Generate fingerprint for a log message.
     */
    private function generateFingerprint(string $level, string $message): string
    {
        // Use first 200 chars to avoid huge fingerprints
        $truncated = substr($message, 0, 200);
        return md5($level . ':' . $truncated);
    }

    /**
     * Clean up fingerprints older than the time window.
     */
    private function cleanupFingerprints(int $now, int $window): void
    {
        foreach ($this->fingerprints as $fingerprint => $entry) {
            if (($now - $entry['last_seen']) > $window) {
                unset($this->fingerprints[$fingerprint]);
            }
        }
    }

    /**
     * Get default configuration for a strategy.
     *
     * @return array<string, mixed>
     */
    private function getDefaultConfig(string $strategy): array
    {
        return match ($strategy) {
            self::STRATEGY_RATE => ['rate' => 100],
            self::STRATEGY_PERCENTAGE => ['percentage' => 10],
            self::STRATEGY_SMART => [
                'alwaysLogLevels' => ['error', 'critical', 'alert', 'emergency'],
                'samplePercentage' => 10,
            ],
            self::STRATEGY_FINGERPRINT => ['window' => 60],
            default => [],
        };
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
     * Get sampling statistics (useful for monitoring/debugging).
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'strategy' => $this->strategy,
            'config' => $this->config,
            'fingerprints_tracked' => count($this->fingerprints),
            'current_second_count' => $this->messageCountThisSecond,
        ];
    }
}
