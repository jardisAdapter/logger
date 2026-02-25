# Jardis Logger

![Build Status](https://github.com/jardisAdapter/logger/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm NC](https://img.shields.io/badge/License-PolyForm%20NC-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-success.svg)](phpstan.neon)
[![PSR-3](https://img.shields.io/badge/PSR--3-v3.0-blue.svg)](https://www.php-fig.org/psr/psr-3/)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-orange.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/coverage-85.66%25-brightgreen.svg)](phpunit.xml)

> Part of the **[Jardis Ecosystem](https://jardis.io)** - A modular DDD framework for PHP

A powerful PSR-3 compliant logging library for PHP 8.2+. Unlike traditional loggers, Jardis Logger provides a **fluent interface** with 20+ handlers, smart handlers for production (FingersCrossed, Sampling, Conditional), and built-in support for **Domain-Driven Design** with context-based logging per bounded context.

---

## Features

- **20+ Handlers** - File, Console, Slack, Teams, Kafka, RabbitMQ, Redis, Loki, Database, Email, Webhook and more
- **Smart Handlers** - FingersCrossed (buffering), Sampling (volume reduction), Conditional (routing)
- **Fluent Interface** - Chain handlers with IDE autocomplete, no config files needed
- **Auto-Enrichment** - Timestamps, UUIDs, Memory, IPs automatically added to every log
- **Multiple Formats** - JSON, Human-Readable, Loki, Slack, Teams, ChromeLogger
- **Named Handlers** - Dynamic handler management at runtime
- **Error Resilience** - One handler fails? Others continue processing
- **DDD Ready** - One logger per bounded context for clean separation

---

## Installation

```bash
composer require jardisadapter/logger
```

## Quick Start

```php
use JardisAdapter\Logger\Logger;
use Psr\Log\LogLevel;

// One line. Done.
$logger = (new Logger('MyApp'))->addConsole(LogLevel::INFO);
$logger->info('Hello World');

// Chain multiple handlers
$logger = (new Logger('OrderService'))
    ->addConsole(LogLevel::DEBUG)
    ->addFile(LogLevel::INFO, '/var/log/app.log')
    ->addSlack(LogLevel::ERROR, 'https://hooks.slack.com/...');

$logger->info('Order created', ['order_id' => 12345]);
```

## Documentation

Full documentation, examples and API reference:

**-> [jardis.io/docs/adapter/logger](https://jardis.io/docs/adapter/logger)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem - a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category    | Packages                                                                             |
|-------------|--------------------------------------------------------------------------------------|
| **Core**    | Domain, Kernel, Data, Workflow                                                       |
| **Adapter** | Cache, Logger, Messaging, DbConnection |
| **Support** | DotEnv, DbQuery, Validation, Factory, ClassVersion |
| **Tools**   | Builder, DbSchema                                                                            |

**-> [Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)
