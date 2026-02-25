<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Formatter;

use JardisAdapter\Logger\Formatter\LogJsonFormat;
use PHPUnit\Framework\TestCase;

class LogJsonFormatTest extends TestCase
{
    public function testInvokeFormatsLogDataCorrectly(): void
    {
        $logData = [
            'datetime' => new \DateTime('2023-10-19 15:45:00'),
            'context' => 'test_context',
            'level' => 'info',
            'message' => 'This is a test message',
            'data' => ['key' => 'value']
        ];

        $transformer = new LogJsonFormat();
        $result = $transformer($logData);

        $expected = json_encode($logData, JSON_THROW_ON_ERROR);

        $this->assertSame($expected, $result);
    }

    public function testInvokeHandlesEmptyDataArray(): void
    {
        $logData = [
            'datetime' => new \DateTime('2023-10-19 15:45:00'),
            'context' => 'empty_context',
            'level' => 'debug',
            'message' => 'Empty data test',
            'data' => []
        ];

        $transformer = new LogJsonFormat();
        $result = $transformer($logData);

        $expected = json_encode($logData, JSON_THROW_ON_ERROR);

        $this->assertSame($expected, $result);
    }

    public function testInvokeHandlesSpecialCharacters(): void
    {
        $logData = [
            'datetime' => new \DateTime('2023-10-19T15:45:00'),
            'context' => 'special_context',
            'level' => 'error',
            'message' => 'Special characters: " \ /',
            'data' => ['key' => 'value']
        ];

        $transformer = new LogJsonFormat();
        $result = $transformer($logData);

        $expected = json_encode($logData, JSON_THROW_ON_ERROR);

        $this->assertSame($expected, $result);
    }

    public function testInvokeThrowsJsonExceptionOnInvalidData(): void
    {
        $this->expectException(\JsonException::class);

        $logData = [
            'datetime' => new \DateTime('2023-10-19 15:45:00'),
            'context' => 'invalid_context',
            'level' => 'critical',
            'message' => 'This will fail due to invalid data',
            'data' => ['key' => fopen('php://memory', 'rb')] // Invalid data
        ];

        $transformer = new LogJsonFormat();
        $transformer($logData);
    }
}
