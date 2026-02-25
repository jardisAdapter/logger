<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use InvalidArgumentException;

/**
 * Generic HTTP webhook handler for sending logs to any HTTP endpoint
 * Supports custom headers, retry mechanism, and flexible body templates
 */
class LogWebhook extends LogCommand
{
    private string $url;
    private string $method;
    /** @var array<string, string> */
    private array $headers;
    private int $timeout;
    private int $retryAttempts;
    private int $retryDelay;
    /** @var callable|null */
    private $bodyFormatter;

    /**
     * Constructor to initialize webhook logging.
     *
     * @param string $logLevel The logging level
     * @param string $url The webhook URL endpoint
     * @param string $method HTTP method (default: POST)
     * @param array<string, string> $headers Custom HTTP headers
     * @param int $timeout Request timeout in seconds (default: 10)
     * @param int $retryAttempts Number of retry attempts on failure (default: 3)
     * @param int $retryDelay Delay between retries in seconds (default: 1)
     * @param callable|null $bodyFormatter Custom body formatter callback(string $message, array $data): string
     * @throws InvalidArgumentException If URL is invalid
     */
    public function __construct(
        string $logLevel,
        string $url,
        string $method = 'POST',
        array $headers = [],
        int $timeout = 10,
        int $retryAttempts = 3,
        int $retryDelay = 1,
        ?callable $bodyFormatter = null
    ) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid webhook URL: {$url}");
        }

        if ($timeout < 1 || $timeout > 300) {
            throw new InvalidArgumentException("Timeout must be between 1 and 300 seconds");
        }

        if ($retryAttempts < 0 || $retryAttempts > 10) {
            throw new InvalidArgumentException("Retry attempts must be between 0 and 10");
        }

        $this->url = $url;
        $this->method = strtoupper($method);
        $this->headers = $headers;
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;
        $this->bodyFormatter = $bodyFormatter;

        // Set default Content-Type if not provided
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'application/json';
        }

        parent::__construct($logLevel);
    }

    protected function log(array $logData): bool
    {
        $message = $logData['message'] ?? '';

        // Use stream if set (for testing)
        if ($this->stream()) {
            $body = $this->formatBody($message, $logData);
            fwrite($this->stream(), $body . "\n");
            return true;
        }

        return $this->sendWithRetry($message, $logData);
    }

    /**
     * @param array<int|string, mixed> $logData
     */
    private function sendWithRetry(string $logMessage, array $logData): bool
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts <= $this->retryAttempts) {
            try {
                if ($this->sendRequest($logMessage, $logData)) {
                    return true;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
            }

            $attempts++;

            // Don't sleep after the last failed attempt
            if ($attempts <= $this->retryAttempts) {
                sleep($this->retryDelay);
            }
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $logData
     */
    private function sendRequest(string $logMessage, array $logData): bool
    {
        $body = $this->formatBody($logMessage, $logData);
        $headers = $this->buildHeaders($body);

        $context = stream_context_create([
            'http' => [
                'method' => $this->method,
                'header' => $headers,
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($this->url, false, $context);

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

    /**
     * @param array<int|string, mixed> $logData
     */
    private function formatBody(string $logMessage, array $logData): string
    {
        if ($this->bodyFormatter !== null) {
            return ($this->bodyFormatter)($logMessage, $logData);
        }

        // Default JSON format
        $encoded = json_encode([
            'message' => $logMessage,
            'data' => $logData,
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded !== false ? $encoded : '{}';
    }

    private function buildHeaders(string $body): string
    {
        $headerLines = [];

        foreach ($this->headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        // Add Content-Length
        $headerLines[] = 'Content-Length: ' . strlen($body);

        return implode("\r\n", $headerLines);
    }

    /**
     * Set a custom body formatter
     *
     * @param callable $formatter Callback with signature: function(string $message, array $data): string
     * @return self
     */
    public function setBodyFormatter(callable $formatter): self
    {
        $this->bodyFormatter = $formatter;
        return $this;
    }

    /**
     * Add or update a header
     *
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
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
     * Get webhook URL
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get HTTP method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get timeout
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
