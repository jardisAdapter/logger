<?php

declare(strict_types=1);

/**
 * Example 9: Conditional Routing - Dynamic Decision Making
 *
 * Route logs to different handlers based on runtime conditions.
 * Perfect for multi-tenant apps, A/B testing, feature flags, user-specific logging.
 * Make intelligent routing decisions based on log content, level, or context.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Handler\LogFile;
use Psr\Log\LogLevel;

$adminHandler = new LogFile(LogLevel::DEBUG, '/tmp/admin.log');
$vipHandler = new LogFile(LogLevel::INFO, '/tmp/vip.log');
$criticalHandler = new LogFile(LogLevel::CRITICAL, '/tmp/critical.log');
$defaultHandler = new LogFile(LogLevel::INFO, '/tmp/default.log');

$logger = (new Logger('MultiTenant'))
    ->addConditional([
        // Route admin users to detailed debug log
        [fn($level, $msg, $ctx) => isset($ctx['user_type']) && $ctx['user_type'] === 'admin', $adminHandler],

        // Route VIP customers to special log
        [fn($level, $msg, $ctx) => isset($ctx['customer_tier']) && $ctx['customer_tier'] === 'vip', $vipHandler],

        // All critical logs go to critical handler
        [fn($level, $msg, $ctx) => $level === LogLevel::CRITICAL, $criticalHandler],
    ], $defaultHandler);  // Fallback for everything else

// Admin user action → admin.log
$logger->debug('Accessing admin dashboard', ['user_type' => 'admin', 'user_id' => 1]);

// VIP customer activity → vip.log
$logger->info('VIP purchase completed', ['customer_tier' => 'vip', 'customer_id' => 999]);

// Critical system issue → critical.log
$logger->critical('Database cluster down');

// Regular user → default.log
$logger->info('User logged in', ['user_id' => 42]);

echo "Logs intelligently routed based on context:\n";
echo "- Admin actions → /tmp/admin.log\n";
echo "- VIP customers → /tmp/vip.log\n";
echo "- Critical events → /tmp/critical.log\n";
echo "- Everything else → /tmp/default.log\n";
