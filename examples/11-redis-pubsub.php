<?php

declare(strict_types=1);

/**
 * Example 11: Redis Pub/Sub - Real-Time Log Streaming
 *
 * Stream logs to Redis Pub/Sub for real-time processing. Perfect for:
 * - Distributed log aggregation across microservices
 * - Real-time dashboards and monitoring
 * - Event-driven architectures
 * - Multiple log consumers (each subscriber gets a copy)
 *
 * Redis Pub/Sub is fire-and-forget (no persistence), ideal for ephemeral logs.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Enricher\{LogDateTime, LogUuid};
use JardisCore\Logger\Formatter\LogJsonFormat;
use Psr\Log\LogLevel;

// Setup Redis connection
$redis = new Redis();
$redis->connect('localhost', 6380); // Docker: port 6380

// Create logger with Redis Pub/Sub handler
$logger = (new Logger('OrderService'))
    ->addRedisMq($redis, 'app-logs', 'redis_pubsub');

// Add enrichers for better context
$logger->getHandler('redis_pubsub')
    ->setFormat(new LogJsonFormat())
    ->logData()
    ->addField('timestamp', new LogDateTime())
    ->addField('request_id', new LogUuid())
    ->addExtra('service', fn() => 'order-service')
    ->addExtra('environment', fn() => 'production');

echo "=== Redis Pub/Sub Logging ===\n\n";

// Simulate order processing events
$logger->info('New order received', [
    'order_id' => 'ORD-12345',
    'customer_id' => 'CUST-789',
    'total_amount' => 299.99,
    'items_count' => 3
]);

$logger->info('Payment processing initiated', [
    'order_id' => 'ORD-12345',
    'payment_method' => 'credit_card',
    'gateway' => 'stripe'
]);

$logger->info('Inventory reserved', [
    'order_id' => 'ORD-12345',
    'warehouse' => 'DE-01',
    'items' => ['SKU-001', 'SKU-002', 'SKU-003']
]);

$logger->warning('Low stock detected', [
    'order_id' => 'ORD-12345',
    'product_sku' => 'SKU-001',
    'remaining_stock' => 5
]);

$logger->info('Order confirmed', [
    'order_id' => 'ORD-12345',
    'status' => 'confirmed',
    'estimated_delivery' => '2024-01-20'
]);

echo "✓ 5 log messages published to Redis channel 'app-logs'\n\n";

echo "To consume these logs in real-time, run in another terminal:\n";
echo "docker exec -it jardis-logger-redis-1 redis-cli -p 6379 SUBSCRIBE app-logs\n\n";

echo "Use cases:\n";
echo "- Real-time log monitoring dashboard\n";
echo "- Alert systems (subscribe to error channels)\n";
echo "- Analytics pipeline (multiple consumers)\n";
echo "- Distributed tracing across microservices\n";
