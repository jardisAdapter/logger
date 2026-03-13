<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Formatter;

use InvalidArgumentException;
use JardisAdapter\Logger\Formatter\LogHumanFormat;
use PHPUnit\Framework\TestCase;

class LogHumanFormatTest extends TestCase
{
    public function testInvokeFormatsLogDataCorrectly(): void
    {
        $logData = [
            'datetime' => new \DateTime('2023-10-19 10:30:00'),
            'context' => 'test_context',
            'level' => 'info',
            'message' => 'Test message',
            'data' => ['key1' => 'value1', 'key2' => 2]
        ];

        $transformer = new LogHumanFormat();
        $result = $transformer($logData);

        $expected = 'Logging information : 
DATETIME: 2023-10-19 10:30:00
CONTEXT : test_context
LEVEL : info
MESSAGE : Test message
DATA: {"key1":"value1","key2":2}
';

        $this->assertSame($expected, $result);
    }

    public function testInvokeHandlesEmptyDataArray(): void
    {
        $logData = [
            'datetime' => new \DateTime('2023-10-19 10:30:00'),
            'context' => 'app',
            'level' => 'warning',
            'message' => 'Empty data test',
            'data' => []
        ];

        $transformer = new LogHumanFormat();
        $result = $transformer($logData);

        $expected = 'Logging information : 
DATETIME: 2023-10-19 10:30:00
CONTEXT : app
LEVEL : warning
MESSAGE : Empty data test
DATA: []
';

        $this->assertSame($expected, $result);
    }

    public function testInvokeHandlesSpecialCharactersInMessage(): void
    {
        $logData = [
            'datetime' => new \DateTime('2023-10-19 10:30:00'),
            'context' => 'special',
            'level' => 'debug',
            'message' => "Special characters: \" \\ /",
            'data' => ['data_key' => 'data_value']
        ];

        $transformer = new LogHumanFormat();
        $result = $transformer($logData);

        $expected = 'Logging information : 
DATETIME: 2023-10-19 10:30:00
CONTEXT : special
LEVEL : debug
MESSAGE : Special characters: " \ /
DATA: {"data_key":"data_value"}
';

        $this->assertSame($expected, $result);
    }

    public function testInvokeThrowsExceptionForMisbehavingCallable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log data provided.');

        // logData enthält ein callable, das eine Exception wirft
        $logData = [
            'datetime' => new \DateTime('2023-10-19 10:30:00'),
            'context' => 'test_context',
            'level' => 'error',
            'message' => function () {
                throw new \RuntimeException('Test callable failure'); // Callable wirft eine Exception
            },
        ];

        $formatter = new LogHumanFormat();
        $formatter($logData);
    }
}
