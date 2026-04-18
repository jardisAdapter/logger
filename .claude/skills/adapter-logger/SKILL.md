---
name: adapter-logger
description: PSR-3 Logger with fluent builder, multi-handler, enrichers, smart handlers.
user-invocable: false
---

# LOGGER_COMPONENT_SKILL
> `jardisadapter/logger` | NS: `JardisAdapter\Logger` | PSR-3 v3.0 | PHP 8.2+

## ARCHITECTURE
- `LoggerBuilder` → configures handlers → `getLogger()` → **immutable** `Logger` (PSR-3)
- `LogCommand` — base class for all handlers (implements `StreamableLogCommandInterface`)
- `LogData` (`Data\LogData`) — builds log records; `addField()` = root level, `addExtra()` = inside `data`
- `HttpTransport` — shared HTTP delivery for `LogWebhook`, `LogSlack`, `LogTeams`, `LogLoki`
- Enrichers = plain callables with `__invoke()` — no interface required

## API / SIGNATURES

### LoggerBuilder (configuration phase)
```php
new LoggerBuilder(string $context)

// Stream
->addConsole(string $level, string $name = '')
->addFile(string $level, string $path, string $name = '', ?LogFormatInterface $format = null)
->addErrorLog(string $level, string $name = '')
->addSyslog(string $level, string $name = '')

// Network
->addSlack(string $level, string $webhookUrl, string $name = '')
->addTeams(string $level, string $webhookUrl, string $name = '')
->addLoki(string $level, string $url, array $labels = [], string $name = '')
->addWebhook(string $level, string $url, string $name = '')
->addEmail(string $level, string $to, string $from, string $subject, string $smtp, int $port, string $user, string $pass)
->addStash(string $level, string $host, int $port, string $name = '')

// Storage (injected connections)
->addRedis(string $level, Redis $redis, int $ttl = 0, string $name = '')
->addDatabase(string $level, PDO $pdo, string $table, string $name = '')

// Queue (injected connections)
->addRedisMq(Redis $redis, string $channel, string $name = '')
->addRabbitMq(AMQPConnection $connection, string $exchange, string $name = '')
->addKafkaMq(Producer $producer, string $topic, string $name = '')

// Browser
->addBrowserConsole(string $level, string $name = '')

// Smart (see SMART HANDLERS)
->addFingersCrossed(LogCommand $handler, string $activationLevel, int $bufferSize = 0, bool $stopBuffering = false, string $name = '')
->addSampling(LogCommand $handler, string $strategy, array $config = [], string $name = '')
->addConditional(array $conditions, ?LogCommand $default = null, string $name = '')

// Null
->addNull(string $level, string $name = '')

// Generic
->addHandler(LogCommand $instance): self
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
->addFingersCrossed(new LogFile(LogLevel::DEBUG, '/var/log/app.log'), LogLevel::ERROR, bufferSize: 100, stopBuffering: true)
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
