<?php

declare(strict_types=1);

/**
 * Example 12: RabbitMQ (AMQP) - Enterprise Message Queue Logging
 *
 * Stream logs to RabbitMQ for reliable, persistent message queue processing. Perfect for:
 * - Enterprise log aggregation with guaranteed delivery
 * - Complex routing patterns (fanout, topic, direct)
 * - Persistent log storage (survives broker restart)
 * - Load balancing across multiple consumers
 * - ELK stack integration, Splunk, or custom consumers
 *
 * Unlike Redis Pub/Sub (fire-and-forget), RabbitMQ ensures messages are delivered
 * and can be consumed by multiple workers in a round-robin fashion.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Enricher\{LogDateTime, LogUuid};
use JardisCore\Logger\Formatter\LogJsonFormat;
use Psr\Log\LogLevel;

// Setup RabbitMQ connection
$connection = new AMQPConnection([
    'host' => 'localhost',
    'port' => 5672,
    'vhost' => '/',
    'login' => 'guest',
    'password' => 'guest'
]);

$connection->connect();

// Create logger with RabbitMQ handler
$logger = (new Logger('PaymentService'))
    ->addRabbitMq($connection, 'logs-exchange', 'rabbitmq_logs');

// Add enrichers for structured logging
$logger->getHandler('rabbitmq_logs')
    ->setFormat(new LogJsonFormat())
    ->logData()
    ->addField('timestamp', new LogDateTime())
    ->addField('transaction_id', new LogUuid())
    ->addExtra('service', fn() => 'payment-service')
    ->addExtra('environment', fn() => 'production')
    ->addExtra('region', fn() => 'eu-west-1');

echo "=== RabbitMQ (AMQP) Logging ===\n\n";

// Simulate payment processing with comprehensive logging
$logger->info('Payment authorization started', [
    'payment_id' => 'PAY-98765',
    'order_id' => 'ORD-12345',
    'amount' => 299.99,
    'currency' => 'EUR',
    'gateway' => 'stripe'
]);

$logger->debug('Gateway API request sent', [
    'payment_id' => 'PAY-98765',
    'endpoint' => 'https://api.stripe.com/v1/charges',
    'method' => 'POST',
    'request_time' => microtime(true)
]);

$logger->info('Payment authorized successfully', [
    'payment_id' => 'PAY-98765',
    'gateway_transaction_id' => 'ch_3NxhQnLkdIwHu7ix',
    'authorization_code' => 'AUTH-123456',
    'processing_time_ms' => 245
]);

$logger->warning('Fraud check triggered', [
    'payment_id' => 'PAY-98765',
    'reason' => 'unusual_location',
    'risk_score' => 65,
    'action' => 'manual_review_required'
]);

$logger->error('Payment capture failed - retry scheduled', [
    'payment_id' => 'PAY-98765',
    'error_code' => 'insufficient_funds',
    'retry_attempt' => 1,
    'next_retry_at' => '2024-01-15 10:35:00'
]);

$logger->critical('Payment gateway unreachable', [
    'payment_id' => 'PAY-98765',
    'gateway' => 'stripe',
    'error' => 'Connection timeout after 30s',
    'fallback_gateway' => 'paypal',
    'incident_id' => 'INC-2024-001'
]);

echo "✓ 6 log messages published to RabbitMQ exchange 'logs-exchange'\n\n";

echo "RabbitMQ Management UI: http://localhost:15672 (guest/guest)\n";
echo "View exchanges, queues, and message flow\n\n";

echo "To consume these logs:\n";
echo "1. Create a queue and bind it to 'logs-exchange'\n";
echo "2. Multiple consumers can process logs in parallel (load balancing)\n";
echo "3. Messages persist even if RabbitMQ restarts (durable queues)\n\n";

echo "Use cases:\n";
echo "- ELK Stack: RabbitMQ → Logstash → Elasticsearch → Kibana\n";
echo "- Splunk ingestion with guaranteed delivery\n";
echo "- Custom log processors with retry logic\n";
echo "- Audit log archive with compliance requirements\n";
echo "- Multi-tenant log routing (topic exchanges)\n";

$connection->disconnect();
