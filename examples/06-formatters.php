<?php

declare(strict_types=1);

/**
 * Example 6: Multiple Formatters - Same Data, Different Output
 *
 * Use different formatters for different handlers. Human-readable for console,
 * JSON for log aggregation tools, custom formats for specific integrations.
 * Each handler can have its own formatter.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Formatter\{LogLineFormat, LogJsonFormat, LogHumanFormat};
use Psr\Log\LogLevel;

$logger = (new Logger('UserService'))
    ->addConsole(LogLevel::INFO, 'console', new LogHumanFormat())     // Multi-line, readable
    ->addFile(LogLevel::INFO, '/tmp/app.log', 'json', new LogJsonFormat())  // JSON for parsing
    ->addFile(LogLevel::INFO, '/tmp/simple.log', 'line', new LogLineFormat()); // Single line

$logger->info('User registration completed', [
    'user_id' => 123,
    'username' => 'john.doe',
    'email' => 'john@example.com',
    'registration_ip' => '192.168.1.100'
]);

echo "Same log entry formatted in 3 different ways:\n";
echo "- Console: Human-readable multi-line format\n";
echo "- /tmp/app.log: JSON format for log aggregators\n";
echo "- /tmp/simple.log: Compact single-line format\n";
