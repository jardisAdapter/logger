<?php

declare(strict_types=1);

/**
 * Example 3: Multiple Handlers - Different Outputs
 *
 * The real power: chain multiple handlers with different log levels.
 * Each handler independently decides if it should process the log based on severity.
 * Debug goes to console only, info goes to console and file, errors go everywhere.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use Psr\Log\LogLevel;

$logger = (new Logger('OrderService'))
    ->addConsole(LogLevel::DEBUG)              // Console: everything DEBUG and above
    ->addFile(LogLevel::INFO, '/tmp/app.log')  // File: only INFO and above
    ->addSlack(LogLevel::ERROR, 'https://hooks.slack.com/services/YOUR/WEBHOOK');  // Slack: only ERROR and above

// DEBUG → Console only
$logger->debug('Validating order data');

// INFO → Console + File
$logger->info('Order {orderId} received', ['orderId' => 12345]);

// ERROR → Console + File + Slack
$logger->error('Payment gateway timeout', [
    'gateway' => 'stripe',
    'timeout' => 30,
    'orderId' => 12345
]);

echo "Logs distributed to appropriate handlers based on severity\n";
