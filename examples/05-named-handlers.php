<?php

declare(strict_types=1);

/**
 * Example 5: Named Handlers - Dynamic Management
 *
 * Name your handlers for easy retrieval, removal, and management at runtime.
 * Perfect for complex applications where you need to modify logging behavior
 * dynamically based on configuration, feature flags, or runtime conditions.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Handler\LogFile;
use Psr\Log\LogLevel;

$logger = (new Logger('PaymentService'))
    ->addConsole(LogLevel::DEBUG, 'console')
    ->addFile(LogLevel::INFO, '/tmp/app.log', 'app_log')
    ->addFile(LogLevel::ERROR, '/tmp/error.log', 'error_log')
    ->addSlack(LogLevel::CRITICAL, 'https://hooks.slack.com/...', 'slack_alerts');

echo "Registered 4 handlers with names\n\n";

// Retrieve a specific handler by name
$appHandler = $logger->getHandler('app_log');
echo "Retrieved 'app_log' handler: " . get_class($appHandler) . "\n";

// Get all handlers of a specific type
$fileHandlers = $logger->getHandlersByClass(LogFile::class);
echo "Found " . count($fileHandlers) . " file handlers\n\n";

// Log with all handlers
$logger->info('Payment processed successfully');
$logger->error('Payment gateway timeout');

// Remove a handler dynamically (e.g., disable Slack in dev environment)
$logger->removeHandler('slack_alerts');
echo "\nRemoved Slack handler - now only 3 handlers active\n";

// Critical logs no longer go to Slack
$logger->critical('Database connection lost');

echo "\nRemaining handlers: " . count($logger->getHandlers()) . "\n";
