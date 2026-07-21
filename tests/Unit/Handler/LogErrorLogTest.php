<?php

namespace JardisAdapter\Logger\Tests\Unit\Handler;

use JardisAdapter\Logger\Handler\LogErrorLog;
use JardisAdapter\Logger\Tests\Helpers\TempFileHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogErrorLogTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = TempFileHelper::create('error_');
    }

    protected function tearDown(): void
    {
        TempFileHelper::cleanup($this->filePath);
    }

    public function testWritesToStream(): void
    {
        $logger = new LogErrorLog(LogLevel::INFO);

        $stream = fopen($this->filePath, 'a');
        if ($stream) {
            $logger->setStream($stream);

            $result = $logger(LogLevel::INFO, 'Test message', ['key' => 'value']);

            $this->assertStringContainsString(
                '"context": "", "level": "info", "message": "Test message", "data": "{\"key\":\"value\"}"',
                $result
            );
        }
    }
}
