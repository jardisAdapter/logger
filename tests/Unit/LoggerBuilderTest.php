<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit;

use JardisAdapter\Logger\LoggerBuilder;
use JardisAdapter\Logger\Contract\LogCommandInterface;
use JardisAdapter\Logger\Handler\LogConsole;
use JardisAdapter\Logger\Handler\LogNull;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LoggerBuilderTest extends TestCase
{
    public function testBuildReturnsLoggerInstance(): void
    {
        $builder = new LoggerBuilder('TestContext');
        $logger = $builder->getLogger();

        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testEmptyBuilderCreatesNoOpLogger(): void
    {
        $logger = (new LoggerBuilder('TestContext'))->getLogger();

        // Should not throw — just a no-op
        $logger->info('This goes nowhere');
        $this->assertEmpty($logger->getHandlers());
    }

    public function testAddHandlerReturnsSelf(): void
    {
        $builder = new LoggerBuilder('TestContext');
        $handler = new LogNull(LogLevel::DEBUG);

        $result = $builder->addHandler($handler);

        $this->assertSame($builder, $result);
    }

    public function testAddHandlerSetsContextOnHandler(): void
    {
        $builder = new LoggerBuilder('MyContext');

        $mockHandler = $this->createMock(LogCommandInterface::class);
        $mockHandler->expects($this->once())
            ->method('setContext')
            ->with('MyContext');

        $builder->addHandler($mockHandler);
    }

    public function testBuildIncludesAddedHandlers(): void
    {
        $handler1 = new LogNull(LogLevel::DEBUG);
        $handler2 = new LogConsole(LogLevel::ERROR);

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($handler1)
            ->addHandler($handler2)
            ->getLogger();

        $handlers = $logger->getHandlers();
        $this->assertCount(2, $handlers);
        $this->assertContains($handler1, $handlers);
        $this->assertContains($handler2, $handlers);
    }

    public function testNamedHandlersAreRetrievable(): void
    {
        $handler = new LogNull(LogLevel::DEBUG);
        $handler->setHandlerName('my_null');

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($handler)
            ->getLogger();

        $this->assertSame($handler, $logger->getHandler('my_null'));
    }

    public function testSetErrorHandlerReturnsSelf(): void
    {
        $builder = new LoggerBuilder('TestContext');
        $result = $builder->setErrorHandler(function () {
        });

        $this->assertSame($builder, $result);
    }

    public function testErrorHandlerIsPassedToLogger(): void
    {
        $errorCalled = false;

        $mockHandler = $this->createMock(LogCommandInterface::class);
        $mockHandler->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('fail'));

        $logger = (new LoggerBuilder('TestContext'))
            ->setErrorHandler(function () use (&$errorCalled) {
                $errorCalled = true;
            })
            ->addHandler($mockHandler)
            ->getLogger();

        $logger->info('trigger error');

        $this->assertTrue($errorCalled);
    }

    public function testBuilderCanCreateMultipleLoggers(): void
    {
        $builder = new LoggerBuilder('TestContext');
        $builder->addHandler(new LogNull(LogLevel::DEBUG));

        $logger1 = $builder->getLogger();
        $logger2 = $builder->getLogger();

        $this->assertInstanceOf(Logger::class, $logger1);
        $this->assertInstanceOf(Logger::class, $logger2);
        $this->assertNotSame($logger1, $logger2);
    }

    public function testGetHandlersByClassWorksAfterBuild(): void
    {
        $null1 = new LogNull(LogLevel::DEBUG);
        $null2 = new LogNull(LogLevel::ERROR);
        $console = new LogConsole(LogLevel::INFO);

        $logger = (new LoggerBuilder('TestContext'))
            ->addHandler($null1)
            ->addHandler($null2)
            ->addHandler($console)
            ->getLogger();

        $nullHandlers = $logger->getHandlersByClass(LogNull::class);
        $this->assertCount(2, $nullHandlers);

        $consoleHandlers = $logger->getHandlersByClass(LogConsole::class);
        $this->assertCount(1, $consoleHandlers);
    }
}
