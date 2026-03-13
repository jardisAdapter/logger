<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogConditional;
use JardisAdapter\Logger\Handler\LogFile;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogConditionalTest extends TestCase
{
    private string $tempFile1;
    private string $tempFile2;
    private string $tempFile3;

    protected function setUp(): void
    {
        $this->tempFile1 = sys_get_temp_dir() . '/test_cond1_' . uniqid() . '.log';
        $this->tempFile2 = sys_get_temp_dir() . '/test_cond2_' . uniqid() . '.log';
        $this->tempFile3 = sys_get_temp_dir() . '/test_cond3_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        foreach ([$this->tempFile1, $this->tempFile2, $this->tempFile3] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function countLinesInFile(string $file, string $pattern = null): int
    {
        if (!file_exists($file)) {
            return 0;
        }

        $content = file_get_contents($file);
        if ($pattern === null) {
            return substr_count($content, "\n");
        }

        return substr_count($content, $pattern);
    }

    public function testRoutesBasedOnLogLevel(): void
    {
        $errorHandler = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $infoHandler = new LogFile(LogLevel::DEBUG, $this->tempFile2);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $errorHandler],
            [fn($level) => $level === LogLevel::INFO, $infoHandler],
        ]);

        $conditional(LogLevel::ERROR, 'Error message');
        $conditional(LogLevel::INFO, 'Info message');

        $this->assertEquals(1, $this->countLinesInFile($this->tempFile1, 'Error message'));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile2, 'Info message'));
    }

    public function testRoutesBasedOnContext(): void
    {
        $adminHandler = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $userHandler = new LogFile(LogLevel::DEBUG, $this->tempFile2);

        $conditional = new LogConditional([
            [fn($level, $msg, $ctx) => ($ctx['user_id'] ?? '') === 'admin', $adminHandler],
        ], $userHandler);

        $conditional(LogLevel::INFO, 'Admin action', ['user_id' => 'admin']);
        $conditional(LogLevel::INFO, 'User action', ['user_id' => 'user123']);

        $this->assertEquals(1, $this->countLinesInFile($this->tempFile1, 'Admin action'));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile2, 'User action'));
    }

    public function testFallbackHandlerWhenNoConditionMatches(): void
    {
        $errorHandler = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $fallbackHandler = new LogFile(LogLevel::DEBUG, $this->tempFile2);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $errorHandler],
        ], $fallbackHandler);

        $conditional(LogLevel::INFO, 'Info message');

        // Should go to fallback
        $this->assertEquals(0, $this->countLinesInFile($this->tempFile1));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile2, 'Info message'));
    }

    public function testReturnsNullWhenNoConditionMatchesAndNoFallback(): void
    {
        $errorHandler = new LogFile(LogLevel::DEBUG, $this->tempFile1);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $errorHandler],
        ]); // No fallback

        $result = $conditional(LogLevel::INFO, 'Info message');

        $this->assertNull($result, 'Should return null when no condition matches and no fallback');
        $this->assertEquals(0, $this->countLinesInFile($this->tempFile1));
    }

    public function testMultipleConditionsEvaluatedInOrder(): void
    {
        $handler1 = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $handler2 = new LogFile(LogLevel::DEBUG, $this->tempFile2);
        $handler3 = new LogFile(LogLevel::DEBUG, $this->tempFile3);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $handler1],   // First
            [fn($level) => $level === LogLevel::INFO, $handler2],    // Second
            [fn() => true, $handler3],                                // Catch-all
        ]);

        $conditional(LogLevel::ERROR, 'Error');
        $conditional(LogLevel::INFO, 'Info');
        $conditional(LogLevel::DEBUG, 'Debug');

        $this->assertEquals(1, $this->countLinesInFile($this->tempFile1, 'Error'));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile2, 'Info'));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile3, 'Debug'));
    }

    public function testFirstMatchingConditionWins(): void
    {
        $handler1 = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $handler2 = new LogFile(LogLevel::DEBUG, $this->tempFile2);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $handler1],  // More specific
            [fn() => true, $handler2],                               // Catch-all
        ]);

        $conditional(LogLevel::ERROR, 'Error message');

        // Should only go to first handler
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile1, 'Error message'));
        $this->assertEquals(0, $this->countLinesInFile($this->tempFile2));
    }

    public function testComplexContextBasedRouting(): void
    {
        $paymentHandler = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $apiHandler = new LogFile(LogLevel::DEBUG, $this->tempFile2);
        $defaultHandler = new LogFile(LogLevel::DEBUG, $this->tempFile3);

        $conditional = new LogConditional([
            [fn($level, $msg, $ctx) => isset($ctx['payment_id']), $paymentHandler],
            [fn($level, $msg, $ctx) => isset($ctx['api_request']), $apiHandler],
        ], $defaultHandler);

        $conditional(LogLevel::INFO, 'Payment', ['payment_id' => 123]);
        $conditional(LogLevel::INFO, 'API call', ['api_request' => true]);
        $conditional(LogLevel::INFO, 'Normal log');

        $this->assertEquals(1, $this->countLinesInFile($this->tempFile1, 'Payment'));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile2, 'API call'));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile3, 'Normal log'));
    }

    public function testSetContextPropagatesToAllHandlers(): void
    {
        $handler1 = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $handler2 = new LogFile(LogLevel::DEBUG, $this->tempFile2);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $handler1],
        ], $handler2);

        $conditional->setContext('TestContext');
        $conditional(LogLevel::ERROR, 'Error');
        $conditional(LogLevel::INFO, 'Info');

        $content1 = file_get_contents($this->tempFile1);
        $content2 = file_get_contents($this->tempFile2);

        $this->assertStringContainsString('TestContext', $content1);
        $this->assertStringContainsString('TestContext', $content2);
    }

    public function testGetStatistics(): void
    {
        $handler1 = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $handler2 = new LogFile(LogLevel::DEBUG, $this->tempFile2);
        $fallback = new LogFile(LogLevel::DEBUG, $this->tempFile3);

        $conditional = new LogConditional([
            [fn() => true, $handler1],
            [fn() => true, $handler2],
        ], $fallback);

        $stats = $conditional->getStatistics();

        $this->assertEquals(2, $stats['condition_count']);
        $this->assertTrue($stats['has_fallback']);
    }

    public function testGetStatisticsWithoutFallback(): void
    {
        $handler1 = new LogFile(LogLevel::DEBUG, $this->tempFile1);

        $conditional = new LogConditional([
            [fn() => true, $handler1],
        ]);

        $stats = $conditional->getStatistics();

        $this->assertEquals(1, $stats['condition_count']);
        $this->assertFalse($stats['has_fallback']);
    }

    public function testMessageContentCanInfluenceRouting(): void
    {
        $urgentHandler = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $normalHandler = new LogFile(LogLevel::DEBUG, $this->tempFile2);

        $conditional = new LogConditional([
            [fn($level, $msg) => str_contains($msg, '[URGENT]'), $urgentHandler],
        ], $normalHandler);

        $conditional(LogLevel::INFO, '[URGENT] Critical issue');
        $conditional(LogLevel::INFO, 'Normal message');

        $this->assertEquals(1, $this->countLinesInFile($this->tempFile1, 'URGENT'));
        $this->assertEquals(1, $this->countLinesInFile($this->tempFile2, 'Normal message'));
    }

    public function testEmptyConditionsArrayWithFallback(): void
    {
        $fallbackHandler = new LogFile(LogLevel::DEBUG, $this->tempFile1);

        $conditional = new LogConditional([], $fallbackHandler);

        $conditional(LogLevel::INFO, 'Message');

        $this->assertEquals(1, $this->countLinesInFile($this->tempFile1, 'Message'));
    }

    public function testSetFormatPropagatesToAllHandlers(): void
    {
        $handler1 = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $handler2 = new LogFile(LogLevel::DEBUG, $this->tempFile2);
        $fallback = new LogFile(LogLevel::DEBUG, $this->tempFile3);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $handler1],
            [fn($level) => $level === LogLevel::INFO, $handler2],
        ], $fallback);

        $jsonFormat = new \JardisAdapter\Logger\Formatter\LogJsonFormat();
        $conditional->setFormat($jsonFormat);

        $conditional(LogLevel::ERROR, 'Error message');
        $conditional(LogLevel::INFO, 'Info message');
        $conditional(LogLevel::DEBUG, 'Debug message');

        // All files should contain JSON format
        $content1 = file_get_contents($this->tempFile1);
        $content2 = file_get_contents($this->tempFile2);
        $content3 = file_get_contents($this->tempFile3);

        $this->assertStringContainsString('{', $content1);
        $this->assertStringContainsString('"message":', $content1);
        $this->assertStringContainsString('{', $content2);
        $this->assertStringContainsString('"message":', $content2);
        $this->assertStringContainsString('{', $content3);
        $this->assertStringContainsString('"message":', $content3);
    }

    public function testSetStreamPropagatesToAllHandlers(): void
    {
        $handler1 = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $handler2 = new LogFile(LogLevel::DEBUG, $this->tempFile2);
        $fallback = new LogFile(LogLevel::DEBUG, $this->tempFile3);

        $conditional = new LogConditional([
            [fn($level) => $level === LogLevel::ERROR, $handler1],
        ], $fallback);

        // Create a shared stream
        $sharedStream = fopen($this->tempFile1, 'a');
        $conditional->setStream($sharedStream);

        $this->assertIsResource($sharedStream);
        fclose($sharedStream);
    }

    public function testGetHandlerId(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $conditional = new LogConditional([
            [fn() => true, $handler],
        ]);

        $handlerId = $conditional->getHandlerId();

        $this->assertIsString($handlerId);
        $this->assertStringStartsWith('handler_', $handlerId);
    }

    public function testSetAndGetHandlerName(): void
    {
        $handler = new LogFile(LogLevel::DEBUG, $this->tempFile1);
        $conditional = new LogConditional([
            [fn() => true, $handler],
        ]);

        $this->assertNull($conditional->getHandlerName());

        $conditional->setHandlerName('conditional_router');
        $this->assertEquals('conditional_router', $conditional->getHandlerName());

        $conditional->setHandlerName(null);
        $this->assertNull($conditional->getHandlerName());
    }
}
