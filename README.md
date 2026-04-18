# Jardis Logger

![Build Status](https://github.com/jardisAdapter/logger/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![PSR-3](https://img.shields.io/badge/Logger-PSR--3-brightgreen.svg)](https://www.php-fig.org/psr/psr-3/)
[![Coverage](https://img.shields.io/badge/Coverage-85.24%25-green.svg)](https://github.com/jardisAdapter/logger)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

PSR-3 logging pipeline with 20+ handlers, 7 formatters, and 6 enrichers. Configure everything through a fluent `LoggerBuilder` — the resulting `Logger` is immutable after construction. Smart handlers for production: `LogFingersCrossed` buffers until an error occurs, `LogSampling` reduces noise at high volume, `LogConditional` routes by content. One `LoggerBuilder` context per bounded context keeps logs cleanly separated.

---

## Features

- **20+ Handlers** — File, Console, Slack, Teams, Redis, Kafka, RabbitMQ, Loki, Database, Email, Webhook, Syslog, and more
- **Smart Handlers** — `LogFingersCrossed` (buffer-on-error), `LogSampling` (volume reduction), `LogConditional` (rule-based routing)
- **Fluent Builder** — `LoggerBuilder` chains handler registration; `getLogger()` returns an immutable `Logger`
- **Auto-Enrichment** — `LogDateTime`, `LogUuid`, `LogMemoryUsage`, `LogMemoryPeak`, `LogClientIp`, `LogWebRequest` added per handler
- **7 Formatters** — `LogJsonFormat`, `LogLineFormat`, `LogHumanFormat`, `LogSlackFormat`, `LogTeamsFormat`, `LogLokiFormat`, `LogBrowserConsoleFormat`
- **Named Handlers** — Retrieve any handler at runtime via `$logger->getHandler('name')`
- **Error Resilience** — One failing handler never stops the others; optional error callback via `setErrorHandler()`
- **Context Separation** — Each `LoggerBuilder` instance scopes its handlers to a named bounded context

---

## Installation

```bash
composer require jardisadapter/logger
```

## Quick Start

```php
use JardisAdapter\Logger\LoggerBuilder;
use Psr\Log\LogLevel;

// Console + file in two lines
$logger = (new LoggerBuilder('OrderService'))
    ->addConsole(LogLevel::DEBUG)
    ->addFile(LogLevel::INFO, '/var/log/orders.log')
    ->getLogger();

$logger->info('Order created', ['order_id' => 4711]);
$logger->error('Payment failed', ['order_id' => 4711, 'reason' => 'Card declined']);
```

## Advanced Usage

```php
use JardisAdapter\Logger\LoggerBuilder;
use JardisAdapter\Logger\Handler\LogFile;
use Psr\Log\LogLevel;

// Production setup: file baseline + Slack alerts + FingersCrossed buffer + Sampling
$fileHandler = new LogFile(LogLevel::DEBUG, '/var/log/app.log');

$logger = (new LoggerBuilder('PaymentService'))
    // Always write DEBUG and above to file
    ->addHandler($fileHandler)

    // Alert on Slack for CRITICAL and above
    ->addSlack(
        logLevel: LogLevel::CRITICAL,
        webhookUrl: 'https://hooks.slack.com/services/...',
        name: 'slack-alerts'
    )

    // Buffer all messages; flush everything to file only when ERROR is triggered
    ->addFingersCrossed(
        wrappedHandler: $fileHandler,
        activationLevel: LogLevel::ERROR,
        bufferSize: 200,
        name: 'fingers-crossed'
    )

    // Reduce INFO noise to 10 % under load
    ->addSampling(
        wrappedHandler: $fileHandler,
        strategy: 'rate',
        config: ['rate' => 10],
        name: 'sampler'
    )

    ->getLogger();

$logger->info('Checkout started', ['session' => 'abc123']);
$logger->error('Stripe timeout', ['attempt' => 3]);

// Retrieve a named handler at runtime
$slackHandler = $logger->getHandler('slack-alerts');
```

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/adapter/logger](https://docs.jardis.io/en/adapter/logger)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## KI-gestützte Entwicklung

Dieses Package liefert einen Skill für Claude Code, Cursor, Continue und Aider mit. Installation im Konsumentenprojekt:

```bash
composer require --dev jardis/dev-skills
```

Mehr Details: <https://docs.jardis.io/skills>
<!-- END jardis/dev-skills README block -->
