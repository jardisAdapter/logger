<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Transport;

use InvalidArgumentException;
use JardisAdapter\Logger\Transport\HttpTransport;
use PHPUnit\Framework\TestCase;

class HttpTransportTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $transport = new HttpTransport();

        $this->assertEquals('POST', $transport->getMethod());
        $this->assertEquals(['Content-Type' => 'application/json'], $transport->getHeaders());
        $this->assertEquals(10, $transport->getTimeout());
        $this->assertEquals(3, $transport->getRetryAttempts());
    }

    public function testConstructorWithCustomValues(): void
    {
        $transport = new HttpTransport(
            'PUT',
            ['Authorization' => 'Bearer token'],
            30,
            5,
            2
        );

        $this->assertEquals('PUT', $transport->getMethod());
        $this->assertEquals(['Authorization' => 'Bearer token', 'Content-Type' => 'application/json'], $transport->getHeaders());
        $this->assertEquals(30, $transport->getTimeout());
        $this->assertEquals(5, $transport->getRetryAttempts());
    }

    public function testConstructorNormalizesMethodToUppercase(): void
    {
        $transport = new HttpTransport('post');
        $this->assertEquals('POST', $transport->getMethod());
    }

    public function testConstructorThrowsExceptionForUnsupportedMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported HTTP method: INVALID');

        new HttpTransport('INVALID');
    }

    public function testConstructorThrowsExceptionForInvalidTimeout(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be between 1 and 300 seconds');

        new HttpTransport('POST', [], 0);
    }

    public function testConstructorThrowsExceptionForTimeoutTooLarge(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout must be between 1 and 300 seconds');

        new HttpTransport('POST', [], 301);
    }

    public function testConstructorThrowsExceptionForNegativeRetryAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry attempts must be between 0 and 10');

        new HttpTransport('POST', [], 10, -1);
    }

    public function testConstructorThrowsExceptionForTooManyRetryAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry attempts must be between 0 and 10');

        new HttpTransport('POST', [], 10, 11);
    }

    public function testSendReturnsFalseForInvalidUrl(): void
    {
        $transport = new HttpTransport();
        $result = $transport->send('not-a-valid-url', 'payload');

        $this->assertFalse($result);
    }

    public function testSendAddsContentTypeHeaderIfNotProvided(): void
    {
        $transport = new HttpTransport('POST', ['X-Custom' => 'value']);
        $headers = $transport->getHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertArrayHasKey('X-Custom', $headers);
    }

    public function testSendPreservesCustomContentType(): void
    {
        $transport = new HttpTransport('POST', ['Content-Type' => 'text/plain']);
        $headers = $transport->getHeaders();

        $this->assertEquals('text/plain', $headers['Content-Type']);
    }

    public function testAllSupportedHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            $transport = new HttpTransport($method);
            $this->assertEquals($method, $transport->getMethod());
        }
    }
}
