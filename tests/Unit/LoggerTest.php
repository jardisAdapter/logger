<?php

namespace JardisAdapter\Logger\Tests\Unit;

use JardisAdapter\Logger\LoggerBuilder;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogConsole;
use JardisAdapter\Logger\Logger;
use JardisAdapter\Logger\Data\LogLevel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel as PsrLogLevel;

class LoggerTest extends TestCase
{
    public function testNoActiveLoggers(): void
    {
        $logger = new Logger();
        $logger->info('TestContext');
        $this->assertEmpty($logger->getHandlers());
    }

    public function testAddHandlerViaBuilder(): void
    {
        $mockHandler = $this->createMock(LogCommandInterface::class);

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($mockHandler)
            ->getLogger();

        $this->assertNotEmpty($logger->getHandlers());
        $this->assertContains($mockHandler, $logger->getHandlers());
    }

    public function testDebugMethod(): void
    {
        $consoleLogger = new LogConsole(PsrLogLevel::DEBUG);
        if ($mockStream = fopen('php://memory', 'r+')) {
            $consoleLogger->setStream($mockStream);
        }

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($consoleLogger)
            ->getLogger();

        $logger->debug('Test debug message', ['key' => 'value']);

        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertContains($consoleLogger, $handlers);
    }

    public function testLogLevelMethods(): void
    {
        $mockStream = fopen('php://memory', 'r+');

        $handler = new LogConsole(PsrLogLevel::DEBUG);
        if ($mockStream) {
            $handler->setStream($mockStream);
        }

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($handler)
            ->getLogger();

        foreach (LogLevel::COLLECTION as $level => $index) {
            $logger->{strtolower($level)}('Test message', ['key' => 'value']);
        }

        $handlers = $logger->getHandlers();
        $this->assertCount(1, $handlers);
        $this->assertContains($handler, $handlers);
    }

    public function testSetErrorHandler(): void
    {
        $handlerCalled = false;
        $capturedException = null;
        $capturedClass = null;
        $capturedLevel = null;
        $capturedMessage = null;
        $capturedContext = null;

        $errorHandler = function ($e, $class, $level, $message, $context) use (
            &$handlerCalled,
            &$capturedException,
            &$capturedClass,
            &$capturedLevel,
            &$capturedMessage,
            &$capturedContext
        ) {
            $handlerCalled = true;
            $capturedException = $e;
            $capturedClass = $class;
            $capturedLevel = $level;
            $capturedMessage = $message;
            $capturedContext = $context;
        };

        $mockHandler = $this->createMock(LogCommandInterface::class);
        $mockHandler->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('Test exception'));

        $logger = (new LoggerBuilder('TestContext'))
            ->setErrorHandler($errorHandler)
            ->addHandler($mockHandler)
            ->getLogger();

        $logger->info('Test message', ['test' => 'data']);

        $this->assertTrue($handlerCalled);
        $this->assertInstanceOf(\Exception::class, $capturedException);
        $this->assertEquals('Test exception', $capturedException->getMessage());
        $this->assertIsString($capturedClass); // Now it's a handler ID
        $this->assertEquals(PsrLogLevel::INFO, $capturedLevel);
        $this->assertEquals('Test message', $capturedMessage);
        $this->assertEquals(['test' => 'data'], $capturedContext);
    }

    public function testLogContinuesAfterHandlerException(): void
    {
        $callTracker = new class {
            public int $secondHandlerCalls = 0;
        };

        $failingHandler = new class implements LogCommandInterface {
            private string $handlerId;

            public function __construct()
            {
                $this->handlerId = uniqid('handler_', true);
            }

            public function __invoke(string $level, string $message, ?array $data = [])
            {
                throw new \Exception('Handler 1 failed');
            }

            public function setContext(string $context): self
            {
                return $this;
            }

            public function setFormat(\JardisAdapter\Logger\Contract\LogFormatInterface $logFormat): self
            {
                return $this;
            }

            public function getHandlerId(): string
            {
                return $this->handlerId;
            }

            public function setHandlerName(?string $name): self
            {
                return $this;
            }

            public function getHandlerName(): ?string
            {
                return null;
            }
        };

        $successHandler = new class($callTracker) implements LogCommandInterface {
            private $tracker;
            private string $handlerId;

            public function __construct($tracker)
            {
                $this->tracker = $tracker;
                $this->handlerId = uniqid('handler_', true);
            }

            public function __invoke(string $level, string $message, ?array $data = [])
            {
                $this->tracker->secondHandlerCalls++;
            }

            public function setContext(string $context): self
            {
                return $this;
            }

            public function setFormat(\JardisAdapter\Logger\Contract\LogFormatInterface $logFormat): self
            {
                return $this;
            }

            public function getHandlerId(): string
            {
                return $this->handlerId;
            }

            public function setHandlerName(?string $name): self
            {
                return $this;
            }

            public function getHandlerName(): ?string
            {
                return null;
            }
        };

        $logger = (new LoggerBuilder('TestContext'))
            ->setErrorHandler(function () {
                // Suppress errors
            })
            ->addHandler($failingHandler)
            ->addHandler($successHandler)
            ->getLogger();

        $logger->info('Test message');

        $this->assertEquals(1, $callTracker->secondHandlerCalls);
    }

    public function testLogWithoutErrorHandlerSuppressesException(): void
    {
        $mockHandler = $this->createMock(LogCommandInterface::class);
        $mockHandler->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('Test exception'));

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($mockHandler)
            ->getLogger();

        // Should not throw exception even though handler throws
        $logger->info('Test message');
        $this->assertTrue(true);
    }

    public function testMultipleHandlersOfSameClass(): void
    {
        $mockStream1 = fopen('php://memory', 'r+');
        $mockStream2 = fopen('php://memory', 'r+');

        $handler1 = new LogConsole(PsrLogLevel::DEBUG);
        $handler2 = new LogConsole(PsrLogLevel::ERROR);

        if ($mockStream1 && $mockStream2) {
            $handler1->setStream($mockStream1);
            $handler2->setStream($mockStream2);
        }

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($handler1)
            ->addHandler($handler2)
            ->getLogger();

        $handlers = $logger->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertContains($handler1, $handlers);
        $this->assertContains($handler2, $handlers);
    }

    public function testNamedHandlerRegistration(): void
    {
        $handler1 = new LogConsole(PsrLogLevel::DEBUG);
        $handler1->setHandlerName('app_log');

        $handler2 = new LogConsole(PsrLogLevel::ERROR);
        $handler2->setHandlerName('error_log');

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($handler1)
            ->addHandler($handler2)
            ->getLogger();

        $this->assertSame($handler1, $logger->getHandler('app_log'));
        $this->assertSame($handler2, $logger->getHandler('error_log'));
    }

    public function testGetHandlerByName(): void
    {
        $handler = new LogConsole(PsrLogLevel::INFO);
        $handler->setHandlerName('my_handler');

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($handler)
            ->getLogger();

        $retrieved = $logger->getHandler('my_handler');
        $this->assertSame($handler, $retrieved);

        $notFound = $logger->getHandler('non_existent');
        $this->assertNull($notFound);
    }

    public function testGetHandlers(): void
    {
        $handler1 = new LogConsole(PsrLogLevel::DEBUG);
        $handler2 = new LogConsole(PsrLogLevel::ERROR);

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($handler1)
            ->addHandler($handler2)
            ->getLogger();

        $handlers = $logger->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertContains($handler1, $handlers);
        $this->assertContains($handler2, $handlers);
    }

    public function testGetHandlersByClass(): void
    {
        $consoleHandler1 = new LogConsole(PsrLogLevel::DEBUG);
        $consoleHandler2 = new LogConsole(PsrLogLevel::ERROR);

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($consoleHandler1)
            ->addHandler($consoleHandler2)
            ->getLogger();

        $consoleHandlers = $logger->getHandlersByClass(LogConsole::class);
        $this->assertCount(2, $consoleHandlers);
        $this->assertContains($consoleHandler1, $consoleHandlers);
        $this->assertContains($consoleHandler2, $consoleHandlers);
    }
}
