# jardisadapter/logger

PSR-3 logging pipeline with 20+ handlers (Stream/Network/Queue/Storage/Browser/Smart), 7 formatters, and 6 enrichers. Fluent `LoggerBuilder` → immutable `Logger` via `getLogger()`.

## Usage essentials

- **Two-phase API:** `LoggerBuilder` configures (`addConsole()`, `addFile()`, `addRedis()`, `addFingersCrossed()`, …), `getLogger()` returns the immutable `Logger` object. No mutation after `getLogger()` — handler access is read-only only (`getHandler(name)`, `getHandlersByClass()`).
- **Connection Injection required:** `addRedis($redis, …)`, `addDatabase($pdo, …)`, `addRabbitMq($amqpConnection, …)`, `addKafkaMq($rdkafkaProducer, …)`. The package never creates its own connections; auth, database selection, and keepalive are the caller's responsibility.
- **Enrichers are plain callables** (`__invoke`): `logData()->addField('key', $enricher)` lands at root level (DB-column-capable), `->addExtra('key', $enricher)` in the `data` field (business context). Any Closure or callable works — no interface required.
- **Smart handlers as wrappers:** `LogFingersCrossed` buffers until activation level is reached (see DEBUG context on error), `LogSampling` reduces volume (Rate/Percentage/Smart/Fingerprint), `LogConditional` routes via callable condition. Ideal for high-traffic without log flooding.
- **Shared `HttpTransport`** serves `LogWebhook`, `LogSlack`, `LogTeams`, `LogLoki` — unified retry/timeout semantics; extensions `ext-redis`/`ext-amqp`/`ext-rdkafka` are in `suggest`, not `require`.
- **DDD Layer rule:** Application injects `LoggerInterface`, Infrastructure configures handlers via `LoggerBuilder`, Domain never imports logger classes. `Logger` swallows handler exceptions (optional `setErrorHandler(callable)`), so a broken handler does not block others.

## Full reference

https://docs.jardis.io/en/adapter/logger
