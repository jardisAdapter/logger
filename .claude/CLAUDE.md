# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## SkillSet Reference

For comprehensive usage patterns, architectural placement, and best practices, refer to:
**[LOGGER_COMPONENT_SKILL](/Users/Rolf/.claude/skills/jardis/core/LOGGER_COMPONENT_SKILL.md)**

## Project Overview

JardisCore Logger is a PSR-3 compliant logging library for PHP 8.2+ designed around Domain-Driven Design principles. It provides flexible, context-based logging with multiple handlers, formatters, and enrichers.

## Development Environment

This project uses Docker Compose for development. The docker-compose configuration is located in `support/docker-compose.yml`.

**Note**: All `make` commands automatically use `support/docker-compose.yml`. When running `docker compose` commands directly, you must specify `-f support/docker-compose.yml` or run from the `support/` directory.

Services include:
- `phpcli`: PHP 8.3 CLI container with Xdebug (port 9003)
  - Xdebug is enabled by default (`XDEBUG_MODE=debug`)
  - For coverage runs, Xdebug is set to coverage mode (`XDEBUG_MODE=coverage`)
  - All PHP extensions required by the library are pre-installed (ext-redis, ext-amqp, ext-rdkafka, ext-pdo, ext-json)
- `mailhog`: SMTP server for testing email handlers (ports 1025 SMTP, 8025 Web UI)
- `redis`: Redis 6.2 for testing Redis handlers (port 6380)
- `rabbitmq`: RabbitMQ 3.12 for testing AMQP handlers (port 5672, management UI 15672)
- `kafka`: Apache Kafka 3.8.1 for testing message queue handlers (port 9092)
- `wiremock`: WireMock 3.13 for testing HTTP webhook handlers (port 8081)

### Common Commands

**Start services (redis, mailhog, rabbitmq, kafka, wiremock):**
```bash
make start
```

**Run tests:**
```bash
make phpunit
```

**Run tests with coverage:**
```bash
make phpunit-coverage
```

**Run tests with HTML coverage report:**
```bash
make phpunit-coverage-html
```

**Run static analysis (PHPStan Level 8):**
```bash
make phpstan
```

**Run code style checks (PSR-12):**
```bash
make phpcs
```

**Run unit tests only:**
```bash
make phpunit-unit
```

**Run integration tests only (requires: make start):**
```bash
make phpunit-integration
```

**Run a single test file:**
```bash
docker compose -f support/docker-compose.yml run --rm phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php tests/unit/path/to/TestFile.php
```

**Install dependencies:**
```bash
make install
```

**Update dependencies:**
```bash
make update
```

**Access PHP CLI shell:**
```bash
make shell
```

**Stop all services:**
```bash
make stop
```

## Architecture

### Core Components

1. **Logger** (`src/Logger.php`)
   - Main entry point implementing PSR-3 `LoggerInterface`
   - Manages multiple log handlers via `addHandler()`
   - Each logger has a **context** (e.g., "OrderService", "PaymentContext") used for domain separation
   - Handlers are invoked as callables: `$logCommand($level, $message, $context)`
   - **Supports multiple instances of the same handler class** (e.g., app.log + error.log)
   - Each handler has a unique ID automatically assigned
   - Handlers can optionally have names via `setHandlerName()` for easy retrieval
   - Includes error handling: if a handler fails, other handlers continue processing
   - Optional custom error handler via `setErrorHandler(callable)`
   - Handler management methods:
     - `getHandler(string $name)`: Retrieve handler by name
     - `removeHandler(string $nameOrId)`: Remove handler by name or ID
     - `getHandlers()`: Get all registered handlers
     - `getHandlersByClass(string $className)`: Get all handlers of a specific class

2. **LogCommand** (`src/handler/LogCommand.php`)
   - Base class for all handlers
   - Implements `StreamableLogCommandInterface`
   - Each handler instance has a unique auto-generated ID (`getHandlerId()`)
   - Optional handler name can be set via `setHandlerName(?string $name)`
   - Handles log level filtering via `isResponsible()`
   - Manages stream resources with proper cleanup
   - Contains references to `LogData` (for building log records) and `LogFormatInterface` (for formatting)

3. **LogData** (`src/builder/LogData.php`)
   - Builds log record arrays with context, level, message, and additional data
   - Supports message interpolation: `{placeholder}` syntax
   - Two extension points:
     - `addField()`: Adds new columns to the root level of the log record (for DB columns, indexing)
     - `addExtra()`: Adds data to the `data` field dynamically (for business context)
   - Both methods accept callables (enrichers) that are invoked on each log call

### Handler Architecture

All handlers extend `LogCommand` and implement the Chain of Responsibility pattern:

- **Stream-based handlers**: LogFile, LogConsole, LogErrorLog, LogSyslog
- **Network handlers**: LogRedis (injected Redis), LogSlack, LogWebhook, LogStash, LogEmail, LogLoki, LogTeams
- **Message Queue handlers**: LogRedisMq (injected Redis), LogRabbitMq (injected AMQPConnection), LogKafkaMq (injected Producer)
- **Storage handlers**: LogDatabase
- **Browser handlers**: LogBrowserConsole
- **Advanced handlers**: LogFingersCrossed (buffering), LogSampling (volume reduction), LogConditional (dynamic routing)
- **Null handler**: LogNull (for testing)

Each handler:
- Accepts a log level (minimum severity to handle)
- Can have custom `LogData` and `LogFormatInterface`
- Determines responsibility via `isResponsible()` method
- Writes to its destination via the protected `log()` method

**Notable handler features:**
- `LogEmail`: SMTP email with rate limiting to prevent flooding
- `LogWebhook`: HTTP endpoint delivery with retry mechanism (tested with WireMock)
- `LogSlack`: Rich Slack notifications built on LogWebhook
- `LogTeams`: Microsoft Teams notifications via Incoming Webhooks using MessageCard format
- `LogLoki`: Grafana Loki integration via Push API with label-based indexing
- `LogBrowserConsole`: Browser DevTools integration via ChromeLogger protocol (X-ChromeLogger-Data header)
- `LogRedisMq`: Publish to Redis Pub/Sub channels
- `LogRabbitMq`: Publish to RabbitMQ exchanges (AMQP)
- `LogKafkaMq`: Publish to Apache Kafka topics with flush support
- `LogFingersCrossed`: Buffers logs, flushes only when activation level reached (for seeing DEBUG context on errors)
- `LogSampling`: Reduces log volume with strategies (rate limiting, percentage, smart, fingerprint)
- `LogConditional`: Routes logs to different handlers based on runtime conditions

### Formatter Pattern

Formatters implement `LogFormatInterface` with a single `__invoke(array $logData): string` method:

- `LogLineFormat`: Single-line text (default)
- `LogJsonFormat`: JSON structured format
- `LogHumanFormat`: Multi-line human-readable format
- `LogLokiFormat`: Grafana Loki JSON format with streams and labels
- `LogTeamsFormat`: Microsoft Teams MessageCard JSON format
- `LogSlackFormat`: Slack Block Kit JSON format
- `LogBrowserConsoleFormat`: ChromeLogger protocol format (base64-encoded JSON)

### Transport Pattern

The library includes a transport layer abstraction (`LogTransportInterface`) for delivering formatted payloads:

- `HttpTransport`: HTTP delivery with configurable method, headers, timeout, and retry mechanism
  - Supports GET, POST, PUT, PATCH, DELETE methods
  - Configurable timeout (1-300 seconds)
  - Automatic retry with configurable attempts (0-10) and delay
  - Used by LogWebhook, LogSlack, LogTeams, and LogLoki handlers

### Enricher Pattern

Enrichers implement `LogEnricherInterface` with `__invoke()` returning string or array:

- Used via `addField()` or `addExtra()` methods on LogData
- Examples: `LogDateTime`, `LogClientIp`, `LogUuid`, `LogMemoryUsage`, `LogMemoryPeak`, `LogWebRequest`
- Can be callables, closures, or objects implementing the interface

## Code Standards

- **PHP Version**: 8.2+
- **Strict Types**: All files must have `declare(strict_types=1);`
- **Coding Standard**: PSR-12 (enforced via phpcs.xml)
- **Static Analysis**: PHPStan Level 8 (phpstan.neon)
- **Line Length**: 120 characters (soft limit), 150 characters (hard limit)
- **Test Coverage**: Target is 80%+ method coverage (currently 91% line coverage, 80% method coverage)
- **Test Suite**: 254 tests with 625 assertions

## Testing

Tests are divided into:
- `tests/unit/`: Unit tests for individual components
  - Can run without Docker services
  - Test handlers: LogConditional, LogConsole, LogErrorLog, LogNull, LogSampling, LogSlack, LogSyslog, LogWebhook, etc.
  - Test enrichers: LogDateTime, LogClientIp, LogUuid, LogMemoryUsage, etc.
  - Test formatters: LogLineFormat, LogJsonFormat, LogHumanFormat
  - Test core components: Logger, LogData
- `tests/integration/`: Integration tests requiring Docker services
  - `LogWebhookTest`: Tests HTTP webhook functionality using WireMock
  - `LogEmailTest`: Tests SMTP functionality using MailHog
  - `LogDatabaseTest`, `LogRedisTest`, `LogRedisMqTest`: Require Redis service
  - `LogRabbitMqTest`: Requires RabbitMQ service
  - `LogKafkaMqTest`: Requires Kafka service
  - `LogFileTest`: Tests file-based logging
  - `MultipleHandlersTest`: Tests multiple handler instances (app.log + error.log scenario)
- `tests/bootstrap.php`: Test initialization and autoloading

**Important**: Integration tests require all Docker services to be running. Use `make start` before running `make phpunit-integration`.

Test environment variables can be configured in `.env` (used by Docker Compose).

## Connection Injection Pattern

All handlers that need external connections follow the **Dependency Inversion Principle**: connections are injected from the outside, never created internally.

- **LogRedis**: Accepts `Redis` instance (caller manages connection, auth, database selection)
- **LogRedisMq**: Accepts `Redis` instance for Pub/Sub
- **LogRabbitMq**: Accepts `AMQPConnection` instance
- **LogKafkaMq**: Accepts `RdKafka\Producer` instance
- **LogDatabase**: Accepts `PDO` instance

Transport extensions (`ext-redis`, `ext-amqp`, `ext-rdkafka`) are in `suggest`, not `require`.

## Pre-commit Hook

The repository uses a Git pre-commit hook (`support/pre-commit-hook.sh`) that:
1. Validates branch naming convention: `(feature|fix|hotfix)/{1-7 digits}_{description}`
2. Validates git username format (no special characters)

## Key Implementation Patterns

### Adding a Handler to Logger

```php
$logger = new Logger('ContextName');
$handler = new LogFile(LogLevel::INFO, '/path/to/file.log');
$logger->addHandler($handler);
```

### Multiple Handlers of Same Class (Enterprise Pattern)

```php
$logger = new Logger('OrderService');

// Handler 1: All logs to app.log
$appHandler = new LogFile(LogLevel::DEBUG, '/var/log/app.log');
$appHandler->setHandlerName('app_log');
$logger->addHandler($appHandler);

// Handler 2: Only errors to error.log
$errorHandler = new LogFile(LogLevel::ERROR, '/var/log/error.log');
$errorHandler->setHandlerName('error_log');
$logger->addHandler($errorHandler);

// Retrieve handler by name
$retrieved = $logger->getHandler('app_log');

// Remove handler
$logger->removeHandler('error_log');

// Get all handlers of a specific type
$fileHandlers = $logger->getHandlersByClass(LogFile::class);
```

### Extending LogData with Enrichers

```php
$handler->logData()
    ->addField('timestamp', new LogDateTime())    // Adds column to root level
    ->addExtra('request_id', new LogUuid());      // Adds to 'data' field
```

### Creating Custom Handlers

Extend `LogCommand` and override the `log()` method:
- Call parent constructor with log level
- Implement `log(string $logMessage, array $logData): bool`
- Use `$this->format()` to get the formatter
- Use `$this->logData()` to get the log data builder

### Error Handling in Logger

The Logger catches exceptions from handlers and:
1. Invokes error handler if set: `($this->errorHandler)($e, $handlerId, $level, $message, $context)`
2. Continues processing remaining handlers
3. Never throws exceptions to caller

**Note**: The error handler receives the handler ID (not class name) as the second parameter.

## Important Files

- `composer.json`: Dependencies, autoload config, post-install git hook setup
- `phpcs.xml`: Code style rules (PSR-12 + custom rules)
- `phpstan.neon`: Static analysis configuration (Level 8)
- `phpunit.xml`: Test suite configuration
- `Makefile`: Development commands (uses Docker Compose)
- `.env`: Environment variables for Docker Compose
- `support/docker-compose.yml`: Docker service definition for PHP CLI
- `support/pre-commit-hook.sh`: Git pre-commit validation