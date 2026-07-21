<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Enricher;

use JardisAdapter\Logger\Enricher\LogClientIp;
use PHPUnit\Framework\TestCase;

class LogClientIpTest extends TestCase
{
    private array $originalServer;

    protected function setUp(): void
    {
        // Backup original $_SERVER values
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        // Restore original $_SERVER values
        $_SERVER = $this->originalServer;
    }

    public function testReturnsIpFromHttpClientIp(): void
    {
        $_SERVER['HTTP_CLIENT_IP'] = '192.168.1.1';

        $this->assertEquals('192.168.1.1', (new LogClientIp())());
    }

    public function testReturnsIpFromHttpXForwardedFor(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.1';

        $this->assertEquals('203.0.113.1', (new LogClientIp())());
    }

    public function testReturnsIpFromRemoteAddr(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '198.51.100.1';

        $this->assertEquals('198.51.100.1', (new LogClientIp())());
    }

    public function testReturnsUnknownWhenNoServerVariablesAreSet(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);

        $this->assertEquals('unknown', (new LogClientIp())());
    }

    public function testReturnsUnknownWhenHttpXForwardedForIsEmpty(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';

        $this->assertEquals('unknown', (new LogClientIp())());
    }
}
