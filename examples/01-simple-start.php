<?php

declare(strict_types=1);

/**
 * Example 1: Quick Start - One Line Logger
 *
 * The simplest way to start logging. Just create a logger with a context name
 * and add a handler. The fluent interface makes it incredibly easy.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use JardisCore\Logger\Logger;
use Psr\Log\LogLevel;

// One line to create a console logger
$logger = (new Logger('MyApp'))->addConsole(LogLevel::INFO);

// Start logging immediately
$logger->info('Application started');
$logger->warning('Low disk space detected');
$logger->error('Database connection failed');

// Output:
// [INFO] Application started
// [WARNING] Low disk space detected
// [ERROR] Database connection failed
