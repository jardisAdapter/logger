<?php

declare(strict_types=1);

/**
 * Example 13: Apache Kafka - High-Throughput Log Streaming
 *
 * Stream logs to Apache Kafka for massive-scale, distributed log processing. Perfect for:
 * - High-throughput log ingestion (millions of messages/second)
 * - Long-term log retention with partitioning
 * - Real-time stream processing (Kafka Streams, Flink, Spark)
 * - Multi-consumer groups with offset management
 * - Big Data pipelines and data lakes
 * - Event sourcing architectures
 *
 * Kafka provides:
 * - Horizontal scalability (add more brokers/partitions)
 * - Fault tolerance (replication across brokers)
 * - Replay capability (consumers control offsets)
 * - Persistent, ordered message log
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use JardisCore\Logger\Enricher\{LogDateTime, LogUuid, LogMemoryUsage};
use JardisCore\Logger\Formatter\LogJsonFormat;
use Psr\Log\LogLevel;
use RdKafka\Producer;
use RdKafka\Conf;

// Setup Kafka producer with optimal configuration
$conf = new Conf();
$conf->set('metadata.broker.list', 'localhost:9092');
$conf->set('compression.type', 'snappy'); // Compress logs for efficiency
$conf->set('batch.num.messages', '100');   // Batch for performance
$conf->set('queue.buffering.max.ms', '100'); // Max latency

$producer = new Producer($conf);

// Create logger with Kafka handler
$logger = (new Logger('AnalyticsService'))
    ->addKafkaMq($producer, 'application-logs', 'kafka_logs');

// Add enrichers for comprehensive logging
$logger->getHandler('kafka_logs')
    ->setFormat(new LogJsonFormat())
    ->logData()
    ->addField('timestamp', new LogDateTime())
    ->addField('trace_id', new LogUuid())
    ->addExtra('service', fn() => 'analytics-service')
    ->addExtra('environment', fn() => 'production')
    ->addExtra('cluster', fn() => 'eu-west-1a')
    ->addExtra('memory_mb', new LogMemoryUsage());

echo "=== Apache Kafka Log Streaming ===\n\n";

// Simulate high-volume analytics events
$logger->info('User session started', [
    'session_id' => 'SESS-' . uniqid(),
    'user_id' => 'USER-12345',
    'device' => 'mobile',
    'platform' => 'iOS 17.2',
    'source' => 'app'
]);

$logger->info('Page view tracked', [
    'session_id' => 'SESS-' . uniqid(),
    'page_url' => '/products/premium-widget',
    'referrer' => 'https://google.com',
    'load_time_ms' => 342,
    'viewport_width' => 375
]);

$logger->info('Product viewed', [
    'session_id' => 'SESS-' . uniqid(),
    'product_id' => 'PROD-789',
    'product_name' => 'Premium Widget',
    'category' => 'Electronics',
    'price' => 299.99,
    'in_stock' => true
]);

$logger->info('Add to cart event', [
    'session_id' => 'SESS-' . uniqid(),
    'product_id' => 'PROD-789',
    'quantity' => 2,
    'cart_total' => 599.98
]);

$logger->warning('Slow query detected', [
    'query' => 'SELECT * FROM orders WHERE...',
    'execution_time_ms' => 2500,
    'threshold_ms' => 1000,
    'database' => 'analytics_db',
    'optimization_needed' => true
]);

// Simulate batch processing
echo "Processing 100 high-frequency events...\n";
for ($i = 0; $i < 100; $i++) {
    $logger->debug("High-frequency event #{$i}", [
        'event_type' => 'metric_update',
        'metric_name' => 'active_users',
        'value' => rand(1000, 5000),
        'timestamp_ms' => (int)(microtime(true) * 1000)
    ]);
}

// Important: Flush to ensure all messages are sent
$producer->flush(10000); // Wait max 10 seconds for all messages to be delivered

echo "\n✓ 105+ log messages streamed to Kafka topic 'application-logs'\n\n";

echo "Kafka advantages:\n";
echo "- Batching: 100 events sent efficiently in batches\n";
echo "- Compression: Snappy reduces network bandwidth\n";
echo "- Partitioning: Logs distributed across partitions for parallelism\n";
echo "- Retention: Logs retained for days/weeks (configurable)\n\n";

echo "Consumer examples:\n";
echo "1. Real-time analytics: Kafka Streams → Aggregate metrics\n";
echo "2. Data lake: Kafka → S3/HDFS for long-term storage\n";
echo "3. Elasticsearch: Kafka → Logstash → Elasticsearch\n";
echo "4. Alerting: Kafka → Custom consumer → PagerDuty/Slack\n";
echo "5. ML pipeline: Kafka → Feature extraction → Model training\n\n";

echo "Monitor with:\n";
echo "docker exec -it jardis-logger-kafka-1 kafka-console-consumer.sh \\\n";
echo "  --bootstrap-server localhost:9092 \\\n";
echo "  --topic application-logs \\\n";
echo "  --from-beginning\n";
