<?php

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogStash;
use Psr\Log\LogLevel;
use PHPUnit\Framework\TestCase;
use Exception;

class LogStashTest extends TestCase
{
    /** @throws Exception */
    public function testWriteToStreamNoMock(): void
    {
        $tempServer = stream_socket_server("tcp://127.0.0.1:9999");

        $logger = new LogStash(LogLevel::INFO,'127.0.0.1', 9999);

        $result = $logger(LogLevel::INFO, 'TestMessage', ['key' => 'value']);

        $this->assertStringContainsString(
            '"context":"","level":"info","message":"TestMessage","data":{"key":"value"}',
            $result
        );
    }
}
