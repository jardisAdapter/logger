<?php

namespace JardisAdapter\Logger\Tests\Unit;

use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogConsole;
use JardisAdapter\Logger\Logger;
use JardisAdapter\Logger\Data\LogLevel;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel as PsrLogLevel;
use ReflectionClass;
use ReflectionException;

class LoggerTest extends TestCase
{
    public function testNoActiveLoggers(): void
    {
        $logger = new Logger('');
        $logger->info('TestContext');
        $this->assertEmpty($this->getPrivateProperty($logger, 'logCommand'));
    }

    /** @throws ReflectionException */
    public function testAddHandler(): void
    {
        $logger = new Logger('TestContext');

        $mockHandler = $this->createMock(LogCommandInterface::class);
        $logger->addHandler($mockHandler);

        $this->assertNotEmpty($this->getPrivateProperty($logger, 'logCommand'));
        $this->assertContains($mockHandler, $this->getPrivateProperty($logger, 'logCommand'));
    }


    public function testDebugMethod(): void
    {
        $logger = new Logger('TestContext');
        $consoleLogger = new LogConsole(PsrLogLevel::DEBUG);
        if ($mockStream = fopen('php://memory', 'r+')) {
            $consoleLogger->setStream($mockStream);
        }

        $logger->addHandler($consoleLogger);
        $logger->debug('Test debug message', ['key' => 'value']);

        $logCommands = $this->getPrivateProperty($logger, 'logCommand');
        $this->assertCount(1, $logCommands);
        $this->assertContains($consoleLogger, $logCommands);
    }

    public function testLogLevelMethods(): void
    {
        $logger = new Logger('TestContext');
        $mockStream = fopen('php://memory', 'r+');

        // Add a single handler with DEBUG level (handles all levels)
        $handler = new LogConsole(PsrLogLevel::DEBUG);
        if ($mockStream) {
            $handler->setStream($mockStream);
        }
        $logger->addHandler($handler);

        // Test all log level methods
        foreach (LogLevel::COLLECTION as $level => $index) {
            $logger->{strtolower($level)}('Test message', ['key' => 'value']);
        }

        $logCommands = $this->getPrivateProperty($logger, 'logCommand');
        $this->assertCount(1, $logCommands);
        $this->assertContains($handler, $logCommands);
    }

    public function testSetErrorHandler(): void
    {
        $logger = new Logger('TestContext');
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

        $logger->setErrorHandler($errorHandler);

        // Create a mock handler that throws an exception
        $mockHandler = $this->createMock(LogCommandInterface::class);
        $mockHandler->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('Test exception'));

        $logger->addHandler($mockHandler);
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
        $logger = new Logger('TestContext');
        $callTracker = new class {
            public int $secondHandlerCalls = 0;
        };

        $logger->setErrorHandler(function () {
            // Suppress errors
        });

        // Create real handler instances to ensure proper invocation
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

        $logger->addHandler($failingHandler);
        $logger->addHandler($successHandler);
        $logger->info('Test message');

        $this->assertEquals(1, $callTracker->secondHandlerCalls);
    }

    public function testLogWithoutErrorHandlerSuppressesException(): void
    {
        $logger = new Logger('TestContext');

        $mockHandler = $this->createMock(LogCommandInterface::class);
        $mockHandler->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('Test exception'));

        $logger->addHandler($mockHandler);

        // Should not throw exception even though handler throws
        $logger->info('Test message');
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testAddHandlerSetsContext(): void
    {
        $logger = new Logger('MyContext');

        $mockHandler = $this->createMock(LogCommandInterface::class);
        $mockHandler->expects($this->once())
            ->method('setContext')
            ->with('MyContext');

        $logger->addHandler($mockHandler);
    }

    public function testMultipleHandlersOfSameClass(): void
    {
        $logger = new Logger('TestContext');

        $mockStream1 = fopen('php://memory', 'r+');
        $mockStream2 = fopen('php://memory', 'r+');

        $handler1 = new LogConsole(PsrLogLevel::DEBUG);
        $handler2 = new LogConsole(PsrLogLevel::ERROR);

        if ($mockStream1 && $mockStream2) {
            $handler1->setStream($mockStream1);
            $handler2->setStream($mockStream2);
        }

        $logger->addHandler($handler1);
        $logger->addHandler($handler2); // Should now be allowed

        $logCommands = $this->getPrivateProperty($logger, 'logCommand');
        $this->assertCount(2, $logCommands);
        $this->assertContains($handler1, $logCommands);
        $this->assertContains($handler2, $logCommands);
    }

    public function testNamedHandlerRegistration(): void
    {
        $logger = new Logger('TestContext');

        $handler1 = new LogConsole(PsrLogLevel::DEBUG);
        $handler1->setHandlerName('app_log');

        $handler2 = new LogConsole(PsrLogLevel::ERROR);
        $handler2->setHandlerName('error_log');

        $logger->addHandler($handler1);
        $logger->addHandler($handler2);

        $retrievedHandler1 = $logger->getHandler('app_log');
        $retrievedHandler2 = $logger->getHandler('error_log');

        $this->assertSame($handler1, $retrievedHandler1);
        $this->assertSame($handler2, $retrievedHandler2);
    }

    public function testGetHandlerByName(): void
    {
        $logger = new Logger('TestContext');

        $handler = new LogConsole(PsrLogLevel::INFO);
        $handler->setHandlerName('my_handler');

        $logger->addHandler($handler);

        $retrieved = $logger->getHandler('my_handler');
        $this->assertSame($handler, $retrieved);

        $notFound = $logger->getHandler('non_existent');
        $this->assertNull($notFound);
    }

    public function testRemoveHandlerByName(): void
    {
        $logger = new Logger('TestContext');

        $handler = new LogConsole(PsrLogLevel::INFO);
        $handler->setHandlerName('removable');

        $logger->addHandler($handler);
        $this->assertNotNull($logger->getHandler('removable'));

        $result = $logger->removeHandler('removable');
        $this->assertTrue($result);
        $this->assertNull($logger->getHandler('removable'));
    }

    public function testRemoveHandlerById(): void
    {
        $logger = new Logger('TestContext');

        $handler = new LogConsole(PsrLogLevel::INFO);
        $logger->addHandler($handler);

        $handlerId = $handler->getHandlerId();
        $result = $logger->removeHandler($handlerId);

        $this->assertTrue($result);
        $this->assertEmpty($logger->getHandlers());
    }

    public function testGetHandlers(): void
    {
        $logger = new Logger('TestContext');

        $handler1 = new LogConsole(PsrLogLevel::DEBUG);
        $handler2 = new LogConsole(PsrLogLevel::ERROR);

        $logger->addHandler($handler1);
        $logger->addHandler($handler2);

        $handlers = $logger->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertContains($handler1, $handlers);
        $this->assertContains($handler2, $handlers);
    }

    public function testGetHandlersByClass(): void
    {
        $logger = new Logger('TestContext');

        $consoleHandler1 = new LogConsole(PsrLogLevel::DEBUG);
        $consoleHandler2 = new LogConsole(PsrLogLevel::ERROR);

        $logger->addHandler($consoleHandler1);
        $logger->addHandler($consoleHandler2);

        $consoleHandlers = $logger->getHandlersByClass(LogConsole::class);
        $this->assertCount(2, $consoleHandlers);
        $this->assertContains($consoleHandler1, $consoleHandlers);
        $this->assertContains($consoleHandler2, $consoleHandlers);
    }

    /**
     * @return mixed
     * @throws ReflectionException
     */
    private function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
