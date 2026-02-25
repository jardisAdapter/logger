<?php

declare(strict_types=1);

/**
 * Example 7: FingersCrossed - Smart Buffering
 *
 * Production game-changer: Buffer DEBUG logs in memory and only write them
 * when an ERROR occurs. Get full debugging context only when you need it,
 * without flooding logs during normal operation. Saves disk space and money!
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Handler\LogFile;
use Psr\Log\LogLevel;

$fileHandler = new LogFile(LogLevel::DEBUG, '/tmp/debug.log');

$logger = (new Logger('ProductionApp'))
    ->addFingersCrossed(
        $fileHandler,
        LogLevel::ERROR,  // Activate on ERROR or above
        100,              // Buffer up to 100 messages
        true              // Stop buffering after first activation
    );

// These DEBUG logs are buffered in memory, NOT written to disk yet
$logger->debug('Step 1: Loading user profile');
$logger->debug('Step 2: Validating permissions');
$logger->debug('Step 3: Fetching order history');
$logger->info('Processing payment for order 12345');

echo "4 logs buffered in memory, nothing written to disk yet...\n";

// This ERROR triggers the buffer flush - ALL 5 messages get written!
$logger->error('Database connection timeout!');

echo "ERROR triggered flush - all 5 logs (including buffered DEBUG) written to /tmp/debug.log\n";
echo "Perfect for debugging production issues with full context!\n";
