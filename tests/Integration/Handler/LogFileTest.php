<?php

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use Exception;
use JardisAdapter\Logger\Handler\LogFile;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogFileTest extends TestCase
{
    private string $filePath;

    protected function setUp(): void
    {
        $this->filePath = sys_get_temp_dir() . '/file_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
    }

    /** @throws Exception */
    public function testWritesToStream(): void
    {
        $logger = new LogFile(LogLevel::INFO, $this->filePath);

        $file = fopen($this->filePath, 'a');
        if ($file) {
            $logger->setStream($file);

            $logger(LogLevel::INFO, 'Message', ['key' => 'value']);

            $this->assertStringContainsString(
                '"context": "", "level": "info", "message": "Message", "data": "{\"key\":\"value\"}"',
                file_get_contents($this->filePath)
            );
        }
    }

    /** @throws Exception */
    public function testDirectoryNotExistsException(): void
    {
        $this->expectException(Exception::class);
        $logger = new LogFile(LogLevel::INFO, './no/path');
    }
}
