<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Tests\Unit\Enricher;

use JardisAdapter\Logger\Enricher\LogWebRequest;
use PHPUnit\Framework\TestCase;

class LogWebRequestTest extends TestCase
{
    /**
     * Testet, ob die __invoke-Methode die korrekten Request-Daten zurückgibt.
     */
    public function testInvokeReturnsRequestData(): void
    {
        $_SERVER = [
            'REQUEST_URI'    => '/test-url',
            'HTTP_USER_AGENT' => 'UnitTestUserAgent',
            'REQUEST_METHOD' => 'GET'
        ];

        $_GET = ['param1' => 'value1'];
        $_POST = [];

        $logWebRequest = new LogWebRequest();

        $expected = [
            'client_ip'      => 'unknown',
            'request_url'    => $_SERVER['REQUEST_URI'],
            'user_agent'     => $_SERVER['HTTP_USER_AGENT'],
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'method_data'    => $_GET,
        ];

        $result = $logWebRequest();
        $this->assertEquals($expected, $result);
    }

    /**
     * Testet, ob bei fehlenden Serverdaten Standardwerte zurückgegeben werden.
     */
    public function testInvokeHandlesMissingServerData(): void
    {
        $_SERVER = [];
        $_GET = [];
        $_POST = [];

        $logWebRequest = new LogWebRequest();

        $expected = [
            'client_ip'      => 'unknown',
            'request_url'    => 'unknown',
            'user_agent'     => 'unknown',
            'request_method' => 'unknown',
            'method_data'    => [],
        ];

        $result = $logWebRequest();
        $this->assertEquals($expected, $result);
    }

    public function testInvokeHandlesAdditionalLogData(): void
    {
        $_SERVER = [
            'REQUEST_URI'    => '/test-url',
            'HTTP_USER_AGENT' => 'UnitTestUserAgent',
            'REQUEST_METHOD' => 'GET'
        ];

        $_GET = ['param1' => 'value1'];
        $_POST = [];

        $logWebRequest = new LogWebRequest();

        $expected = [
            'client_ip'      => 'unknown',
            'request_url'    => $_SERVER['REQUEST_URI'],
            'user_agent'     => $_SERVER['HTTP_USER_AGENT'],
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'method_data'    => $_GET,
        ];

        $result = $logWebRequest();
        $this->assertEquals($expected, $result);

    }
}
