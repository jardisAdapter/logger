<?php

declare(strict_types=1);

/**
 * Example 4: Log Enrichers - Automatic Context
 *
 * Enrichers automatically add data to every log entry. No need to manually
 * pass timestamps, request IDs, memory usage, etc. Just configure once,
 * and every log gets enriched automatically.
 *
 * Two methods:
 * - addField(): Adds fields at ROOT level (for indexing, DB columns, searchable)
 * - addExtra(): Adds fields inside 'data' field (business context, dynamic data)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Enricher\{LogDateTime, LogUuid, LogMemoryUsage, LogClientIp};
use JardisCore\Logger\Formatter\LogJsonFormat;
use Psr\Log\LogLevel;

$logger = (new Logger('API'))
    ->addFile(LogLevel::INFO, '/tmp/api.log', 'api', new LogJsonFormat());

// Access the handler by name and add enrichers
$logger->getHandler('api')
    ->logData()
    ->addField('timestamp', new LogDateTime())      // Adds timestamp at root level
    ->addField('request_id', new LogUuid())         // Adds unique request ID at root
    ->addExtra('memory_mb', new LogMemoryUsage())   // Adds to 'data' field
    ->addExtra('client_ip', new LogClientIp());     // Adds to 'data' field

// Now every log automatically includes timestamp, request_id, memory, and IP
$logger->info('API request processed', [
    'endpoint' => '/api/v1/users',
    'method' => 'GET',
    'duration_ms' => 45
]);

$logger->info('Database query executed', [
    'query' => 'SELECT * FROM users',
    'rows' => 150
]);

echo "Every log entry automatically enriched with timestamp, UUID, memory, and IP\n";
echo "Check /tmp/api.log for JSON formatted output\n\n";
echo "JSON structure:\n";
echo "{\n";
echo "  \"context\": \"API\",\n";
echo "  \"level\": \"INFO\",\n";
echo "  \"message\": \"...\",\n";
echo "  \"timestamp\": \"2024-01-15 10:30:00\",  // addField() - root level\n";
echo "  \"request_id\": \"550e8400-...\",        // addField() - root level\n";
echo "  \"data\": {\n";
echo "    \"endpoint\": \"/api/v1/users\",\n";
echo "    \"memory_mb\": 12.5,                  // addExtra() - inside data\n";
echo "    \"client_ip\": \"192.168.1.1\"          // addExtra() - inside data\n";
echo "  }\n";
echo "}\n";
