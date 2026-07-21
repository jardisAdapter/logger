<?php

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogNull;
use Psr\Log\LogLevel;
use PHPUnit\Framework\TestCase;

class LogNullTest extends TestCase
{
    public function testWriteToStreamSuccess(): void
    {
        $logger = new LogNull(LogLevel::INFO);

        $result = $logger(LogLevel::INFO, 'Test message', ['key' => 'value']);

        $this->assertEquals(null, $result);
    }
}
