<?php

declare(strict_types=1);

/**
 * Example 2: File Logging with Context
 *
 * Log to files with context interpolation. Variables in curly braces {var}
 * are automatically replaced with values from the context array.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use Psr\Log\LogLevel;

$logger = (new Logger('UserService'))->addFile(LogLevel::DEBUG, '/tmp/app.log');

// Simple message
$logger->info('User logged in');

// Message with context interpolation
$logger->info('User {username} logged in from {ip}', [
    'username' => 'john.doe',
    'ip' => '192.168.1.100',
    'user_id' => 42,
    'session_id' => 'abc123'
]);

// Error with full context
$logger->error('Failed to process payment for order {orderId}', [
    'orderId' => 12345,
    'amount' => 99.99,
    'currency' => 'EUR',
    'gateway' => 'stripe'
]);

echo "Logs written to /tmp/app.log\n";
