<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Formatter;

use InvalidArgumentException;
use JardisAdapter\Logger\Formatter\LogLineFormat;
use PHPUnit\Framework\TestCase;

class LogLineFormatTest extends TestCase
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

        $transformer = new LogLineFormat();
        $result = $transformer($logData);

        $expected = "{ \"datetime\": \"2023-10-19 10:30:00\", \"context\": \"test_context\", \"level\": \"info\", \"message\": \"Test message\", \"data\": \"{\"key1\":\"value1\",\"key2\":2}\" }\n";

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

        $transformer = new LogLineFormat();
        $result = $transformer($logData);

        $expected = "{ \"datetime\": \"2023-10-19 10:30:00\", \"context\": \"app\", \"level\": \"warning\", \"message\": \"Empty data test\", \"data\": \"[]\" }\n";

        $this->assertSame($expected, $result);
    }

    public function testInvokeHandlesSpecialCharactersInMessage(): void
    {
        $logData = [
            'datetime' => new \DateTime('2023-10-19 10:30:00'),
            'context' => 'special',
            'level' => 'debug',
            'message' => "Special characters: \" \\ /",
            'callable' => fn () => 'test',
            'object' => new \stdClass(),
            'data' => ['data_key' => 'data_value']
        ];

        $transformer = new LogLineFormat();
        $result = $transformer($logData);

        $expected = '{ "datetime": "2023-10-19 10:30:00", "context": "special", "level": "debug", "message": "Special characters: " \ /", "callable": "test", "object": "O:8:"stdClass":0:{}", "data": "{"data_key":"data_value"}" }' . "\n";

        $this->assertSame($expected, $result);
    }

    public function testInvokeHandlesInvalidLogData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log data provided.');

        $invalidData = [
            'datetime' => new \DateTime('2023-10-19 10:30:00'),
            'context' => 'error_context',
            'level' => 'error',
            'message' => 'Resource test',
            'data' => fopen(__FILE__, 'r')
        ];

        $transformer = new LogLineFormat();
        $transformer($invalidData);
    }
}
