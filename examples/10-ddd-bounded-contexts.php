<?php

declare(strict_types=1);

/**
 * Example 10: DDD Bounded Contexts - Enterprise Architecture
 *
 * Domain-Driven Design with separate loggers per bounded context.
 * Each context (Order, Payment, Shipping, Inventory) has its own logger instance
 * with specialized handlers, enrichers, and routing. Perfect for microservices,
 * modular monoliths, and complex enterprise applications.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Handler\{LogFile, LogConditional};
use JardisCore\Logger\Enricher\{LogDateTime, LogUuid};
use JardisCore\Logger\Formatter\{LogJsonFormat, LogHumanFormat};
use Psr\Log\LogLevel;

// ============================================================================
// ORDER CONTEXT - High-volume transactional logs
// ============================================================================
$orderLogger = (new Logger('OrderContext'))
    ->addConsole(LogLevel::INFO, 'order_console', new LogHumanFormat())
    ->addFile(LogLevel::INFO, '/tmp/orders.log', 'order_log', new LogJsonFormat())
    ->addSlack(LogLevel::ERROR, 'https://hooks.slack.com/orders', 'order_alerts');

$orderLogger->getHandler('order_log')
    ->logData()
    ->addField('timestamp', new LogDateTime())
    ->addField('order_id', new LogUuid())
    ->addExtra('context', fn() => 'ORDER');

// ============================================================================
// PAYMENT CONTEXT - Critical financial operations with strict audit trail
// ============================================================================
$paymentLogger = (new Logger('PaymentContext'))
    ->addFile(LogLevel::DEBUG, '/tmp/payment_audit.log', 'payment_audit', new LogJsonFormat())
    ->addSlack(LogLevel::CRITICAL, 'https://hooks.slack.com/payments', 'payment_critical')
    ->addTeams(LogLevel::ERROR, 'https://outlook.office.com/webhook/payments');

$paymentLogger->getHandler('payment_audit')
    ->logData()
    ->addField('timestamp', new LogDateTime())
    ->addField('transaction_id', new LogUuid())
    ->addExtra('context', fn() => 'PAYMENT')
    ->addExtra('compliance', fn() => 'PCI-DSS');

// ============================================================================
// SHIPPING CONTEXT - Integration with external carriers
// ============================================================================
$shippingLogger = (new Logger('ShippingContext'))
    ->addFile(LogLevel::INFO, '/tmp/shipping.log', 'shipping_log', new LogJsonFormat())
    ->addLoki(
        LogLevel::INFO,
        'http://loki:3100/loki/api/v1/push',
        ['service' => 'shipping', 'env' => 'production'],
        'shipping_loki'
    );

$shippingLogger->getHandler('shipping_log')
    ->logData()
    ->addField('timestamp', new LogDateTime())
    ->addExtra('context', fn() => 'SHIPPING');

// ============================================================================
// INVENTORY CONTEXT - Conditional routing based on stock levels
// ============================================================================
$lowStockHandler = new LogFile(LogLevel::WARNING, '/tmp/low_stock.log');
$outOfStockHandler = new LogFile(LogLevel::ERROR, '/tmp/out_of_stock.log');
$inventoryDefaultHandler = new LogFile(LogLevel::INFO, '/tmp/inventory.log');

$inventoryLogger = (new Logger('InventoryContext'))
    ->addConditional([
        [fn($level, $msg, $ctx) => isset($ctx['stock_level']) && $ctx['stock_level'] === 0, $outOfStockHandler],
        [fn($level, $msg, $ctx) => isset($ctx['stock_level']) && $ctx['stock_level'] < 10, $lowStockHandler],
    ], $inventoryDefaultHandler);

// ============================================================================
// SIMULATE DOMAIN EVENTS
// ============================================================================

echo "=== DDD BOUNDED CONTEXTS IN ACTION ===\n\n";

// Order Context: New order received
$orderLogger->info('Order received from customer', [
    'customer_id' => 12345,
    'total_amount' => 299.99,
    'items_count' => 3
]);

// Payment Context: Critical payment operation
$paymentLogger->info('Payment authorized', [
    'amount' => 299.99,
    'currency' => 'EUR',
    'gateway' => 'stripe',
    'card_last4' => '4242'
]);

// Payment Context: Critical failure triggers multiple alerts
$paymentLogger->critical('Payment gateway unreachable', [
    'gateway' => 'stripe',
    'timeout_seconds' => 30,
    'retry_attempts' => 3
]);

// Shipping Context: Carrier integration
$shippingLogger->info('Shipment created with carrier', [
    'carrier' => 'DHL',
    'tracking_number' => 'DHL123456789',
    'destination' => 'DE'
]);

// Inventory Context: Low stock warning
$inventoryLogger->warning('Low stock detected', [
    'product_id' => 'PROD-001',
    'product_name' => 'Premium Widget',
    'stock_level' => 5,
    'reorder_threshold' => 10
]);

// Inventory Context: Out of stock triggers critical handler
$inventoryLogger->error('Product out of stock', [
    'product_id' => 'PROD-002',
    'product_name' => 'Super Widget',
    'stock_level' => 0
]);

echo "\n✓ Order logs → /tmp/orders.log + Console\n";
echo "✓ Payment logs → /tmp/payment_audit.log + Slack + Teams\n";
echo "✓ Shipping logs → /tmp/shipping.log + Loki (Grafana)\n";
echo "✓ Inventory logs → Conditionally routed based on stock levels\n";
echo "\nEach bounded context has independent logging configuration!\n";
echo "Perfect for microservices, modular monoliths, and DDD architectures.\n";
