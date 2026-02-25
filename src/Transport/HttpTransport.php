<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Transport;

use InvalidArgumentException;
use JardisAdapter\Logger\Contract\LogTransportInterface;

/**
 * HTTP transport layer for delivering payloads to HTTP endpoints
 * Supports custom headers, methods, timeout, and retry mechanism
 */
class HttpTransport implements LogTransportInterface
{
    private string $method;
    /** @var array<string, string> */
    private array $headers;
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;

    /**
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param array<string, string> $headers Custom HTTP headers
     * @param int $timeout Request timeout in seconds (1-300)
     * @param int $retryAttempts Number of retry attempts on failure (0-10)
     * @param int $retryDelay Delay between retries in seconds
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(
        string $method = 'POST',
        array $headers = [],
        int $timeout = 10,
        int $retryAttempts = 3,
        int $retryDelay = 1
    ) {
        $this->method = strtoupper($method);

        if (!in_array($this->method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException("Unsupported HTTP method: {$method}");
        }

        if ($timeout < 1 || $timeout > 300) {
            throw new InvalidArgumentException("Timeout must be between 1 and 300 seconds");
        }

        if ($retryAttempts < 0 || $retryAttempts > 10) {
            throw new InvalidArgumentException("Retry attempts must be between 0 and 10");
        }

        $this->headers = $headers;
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;

        // Set default Content-Type if not provided
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'application/json';
        }
    }

    /**
     * Send a payload to the destination URL
     *
     * @param string $destination Target URL
     * @param string $payload Formatted payload ready for delivery
     * @param array<string, mixed> $options Additional options (currently unused)
     * @return bool Success status
     */
    public function send(string $destination, string $payload, array $options = []): bool
    {
        if (!filter_var($destination, FILTER_VALIDATE_URL)) {
            return false;
        }

        return $this->sendWithRetry($destination, $payload);
    }

    private function sendWithRetry(string $url, string $payload): bool
    {
        $attempts = 0;

        while ($attempts <= $this->retryAttempts) {
            try {
                if ($this->sendRequest($url, $payload)) {
                    return true;
                }
            } catch (\Exception $e) {
                // Continue to retry
            }

            $attempts++;

            // Don't sleep after the last failed attempt
            if ($attempts <= $this->retryAttempts) {
                sleep($this->retryDelay);
            }
        }

        return false;
    }

    private function sendRequest(string $url, string $payload): bool
    {
        $headers = $this->buildHeaders($payload);

        $context = stream_context_create([
            'http' => [
                'method' => $this->method,
                'header' => $headers,
                'content' => $payload,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        // Check HTTP response code from magic variable set by file_get_contents
        $statusLine = $http_response_header[0] ?? '';
        if ($statusLine !== '') {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches);
            $statusCode = (int) ($matches[1] ?? 0);

            // Consider 2xx and 3xx as success
            return $statusCode >= 200 && $statusCode < 400;
        }

        return $response !== false;
    }

    private function buildHeaders(string $payload): string
    {
        $headerLines = [];

        foreach ($this->headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        // Add Content-Length
        $headerLines[] = 'Content-Length: ' . strlen($payload);

        return implode("\r\n", $headerLines);
    }

    /**
     * Get current HTTP method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get current headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get timeout in seconds
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get retry attempts
     */
    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }
}
