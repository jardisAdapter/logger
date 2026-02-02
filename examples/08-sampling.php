<?php

declare(strict_types=1);

/**
 * Example 8: Log Sampling - Volume Reduction
 *
 * High-traffic applications generate massive logs. Sampling lets you reduce
 * volume intelligently: log only 10% of DEBUG messages, but always log errors.
 * Multiple strategies: percentage, rate limiting, smart (level-based), fingerprint (deduplication).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Handler\{LogFile, LogSampling};
use Psr\Log\LogLevel;

// Strategy 1: Percentage-based sampling (10% of logs)
$fileHandler = new LogFile(LogLevel::DEBUG, '/tmp/sampled.log');
$logger = (new Logger('HighTraffic'))
    ->addSampling($fileHandler, LogSampling::STRATEGY_PERCENTAGE, ['percentage' => 10]);

echo "Simulating 1000 high-frequency debug logs...\n";
for ($i = 0; $i < 1000; $i++) {
    $logger->debug("High-frequency event #{$i}");
}
echo "Only ~100 out of 1000 logs written (10% sample rate)\n\n";

// Strategy 2: Smart sampling (always log ERROR+, sample DEBUG/INFO)
$smartHandler = new LogFile(LogLevel::DEBUG, '/tmp/smart.log');
$logger2 = (new Logger('SmartSampling'))
    ->addSampling($smartHandler, LogSampling::STRATEGY_SMART, [
        'alwaysLogLevels' => ['error', 'critical', 'alert', 'emergency'],
        'samplePercentage' => 5  // Only 5% of DEBUG/INFO
    ]);

$logger2->debug('Debug message');  // 5% chance
$logger2->info('Info message');    // 5% chance
$logger2->error('Error message');  // 100% - always logged!

echo "Smart sampling: ERROR/CRITICAL always logged, DEBUG/INFO sampled\n";
echo "Perfect for production: reduce noise, never miss critical issues!\n";
