---
name: adapter-logger
description: PSR-3 Logger with fluent builder, multi-handler, enrichers, smart handlers.
user-invocable: false
zone: post-active
persona: C
prerequisites: [rules-architecture, rules-patterns]
next: []
---

# LOGGER_COMPONENT_SKILL
> `jardisadapter/logger` | NS: `JardisAdapter\Logger` | PSR-3 v3.0 | PHP 8.2+

## ARCHITECTURE
- `LoggerBuilder` → configures handlers → `getLogger()` → **immutable** `Logger` (PSR-3)
- `LogData` (`Data\LogData`) — builds log records; `addField()` = root level, `addExtra()` = inside `data`
- Enrichers = plain callables with `__invoke()` — no interface required

## API / SIGNATURES

### LoggerBuilder (configuration phase)
```php
new LoggerBuilder(string $context)

// Stream
->addConsole(string $level, ?string $name = null, ?LogFormatInterface $format = null)
->addFile(string $level, string $path, ?string $name = null, ?LogFormatInterface $format = null)
->addErrorLog(string $level, ?string $name = null)
->addSyslog(string $level, ?string $name = null)

// Network
->addSlack(string $level, string $webhookUrl, ?string $name = null, int $timeout = 10, int $retryAttempts = 3)
->addTeams(string $level, string $webhookUrl, ?string $name = null, int $timeout = 10, int $retryAttempts = 3)
->addLoki(string $level, string $url, array $labels = [], ?string $name = null, int $timeout = 10, int $retryAttempts = 3)
->addWebhook(string $level, string $url, ?string $name = null, string $method = 'POST', array $headers = [], int $timeout = 10, int $retryAttempts = 3, int $retryDelay = 1, ?callable $bodyFormatter = null)
->addEmail(string $level, string $to, string $from, string $subject = 'Application Log', string $smtp = 'localhost', int $port = 1025, string $user = '', string $pass = '', string $fromName = 'Logger', bool $useHtml = false, bool $useTls = false, int $rateLimitSeconds = 60, ?string $name = null)
->addStash(string $level, string $host, int $port, ?array $bindTo = null, ?string $name = null)

// Storage (injected connections)
->addRedis(string $level, Redis $redis, ?string $name = null, int $ttl = 3600)
->addDatabase(string $level, PDO $pdo, ?string $table = null, ?string $name = null)

// Queue (injected connections)
->addRedisMq(Redis $redis, string $channel, ?string $name = null)
->addRabbitMq(AMQPConnection $connection, string $exchange, ?string $name = null)
->addKafkaMq(Producer $producer, string $topic, ?string $name = null)

// Browser
->addBrowserConsole(string $level, ?string $name = null)

// Smart (see SMART HANDLERS)
->addFingersCrossed(StreamableLogCommandInterface $handler, string $activationLevel = LogLevel::ERROR, int $bufferSize = 100, bool $stopBufferingAfterActivation = true, ?string $name = null)
->addSampling(StreamableLogCommandInterface $handler, string $strategy = 'smart', array $config = [], ?string $name = null)
->addConditional(array $conditions, ?StreamableLogCommandInterface $default = null, ?string $name = null)

// Null
->addNull(string $level, ?string $name = null)

// Generic
->addHandler(LogCommandInterface $instance): self
->setErrorHandler(callable $handler): self  // fn(Exception, string $handlerId, string $level, string $msg, array $ctx)
->getLogger(): Logger
```

### Logger (immutable, read-only after construction)
```php
$logger->emergency|alert|critical|error|warning|notice|info|debug(string $msg, array $ctx = [])
$logger->log(string $level, string $msg, array $ctx = [])
// Interpolation: {placeholder} syntax

$logger->getHandler(string $name): ?LogCommandInterface
$logger->getHandlers(): array<string, LogCommandInterface>
$logger->getHandlersByClass(string $className): array<string, LogCommandInterface>
```

### LogData enrichers
```php
$handler->logData()
    ->addField('timestamp', new LogDateTime())      // root-level column
    ->addField('hostname', fn() => gethostname())
    ->addExtra('request_id', new LogUuid())         // inside 'data'
    ->addExtra('user_id', fn() => $_SESSION['user_id'])
    ->addExtra('memory_mb', new LogMemoryUsage())
    ->addExtra('memory_peak', new LogMemoryPeak())
    ->addExtra('http_request', new LogWebRequest()) // array: client_ip, request_url, user_agent, request_method, method_data
```
**Built-in enrichers:** `LogDateTime`, `LogClientIp`, `LogUuid`, `LogMemoryUsage`, `LogMemoryPeak`, `LogWebRequest`

## HANDLERS

| Category | Classes |
|----------|---------|
| Stream | `LogFile`, `LogConsole`, `LogSyslog`, `LogErrorLog` |
| Network | `LogWebhook`, `LogSlack`, `LogTeams`, `LogLoki`, `LogStash`, `LogEmail` |
| Queue | `LogRedisMq` (Redis), `LogRabbitMq` (AMQPConnection), `LogKafkaMq` (Producer) |
| Storage | `LogDatabase` (PDO), `LogRedis` (Redis) |
| Browser | `LogBrowserConsole` |
| Smart | `LogFingersCrossed`, `LogSampling`, `LogConditional`, `LogNull` |

External connections always injected (DIP) — never created internally.

## SMART HANDLERS
```php
// FingersCrossed: buffer DEBUG, flush all on ERROR
->addFingersCrossed(new LogFile(LogLevel::DEBUG, '/var/log/app.log'), LogLevel::ERROR, bufferSize: 100, stopBufferingAfterActivation: true)
$h->flush();          // manually write buffer
$h->reset();          // reset state
$h->getStatistics();  // buffer_size, buffer_capacity, is_activated, activation_level, stop_buffering_after_activation

// Sampling
->addSampling($handler, LogSampling::STRATEGY_RATE,        ['rate' => 100])
->addSampling($handler, LogSampling::STRATEGY_PERCENTAGE,  ['percentage' => 10])
->addSampling($handler, LogSampling::STRATEGY_SMART,       ['alwaysLogLevels' => ['error'], 'samplePercentage' => 10])
->addSampling($handler, LogSampling::STRATEGY_FINGERPRINT, ['window' => 60])
$h->getStatistics();  // strategy, config, fingerprints_tracked, current_second_count

// Conditional routing
->addConditional([[fn($level, $msg, $ctx) => $level === LogLevel::CRITICAL, $slackHandler]], $defaultHandler)
$h->getStatistics();  // condition_count, has_fallback
```

## FORMATTERS
`LogLineFormat` (default), `LogJsonFormat`, `LogHumanFormat`, `LogLokiFormat`, `LogSlackFormat`, `LogTeamsFormat`, `LogBrowserConsoleFormat`

All implement `LogFormatInterface`: `__invoke(array $logData): string`

## CONTRACTS
| Interface | Key methods |
|-----------|-------------|
| `LogCommandInterface` | `__invoke`, `setContext`, `setFormat`, handler ID/name |
| `StreamableLogCommandInterface` | extends above + `setStream()` |
| `LogFormatInterface` | `__invoke(array): string` |
| `LogDataInterface` | `__invoke`, `addField`, `addExtra` |

## LOGCOMMAND BASE CLASS
- `__invoke()` — filters by level, formats, writes
- `isResponsible(string $level): bool`
- `setContext()`, `setLogData(LogDataInterface)`, `setFormat()`, `setStream()`
- `logData(): LogDataInterface` — lazy-initialized
- Handler ID: auto-generated via `uniqid()`, optionally named via builder `$name` param
- Closes own streams on `__destruct()` (except STDOUT/STDERR)

## LAYER
- Application: inject `LoggerInterface`
- Infrastructure: configure handlers via `LoggerBuilder`
- Domain: NEVER imports logger
