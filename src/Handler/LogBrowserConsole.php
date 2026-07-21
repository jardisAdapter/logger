<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use JardisAdapter\Logger\Formatter\LogBrowserConsoleFormat;

/**
 * Sends log entries to the browser console via ChromeLogger protocol
 * Uses X-ChromeLogger-Data HTTP header to transmit logs to browser DevTools
 *
 * Requires browser extension or native DevTools support:
 * - Chrome: ChromeLogger extension
 * - Firefox: Native support in DevTools
 * - Edge: ChromeLogger extension
 *
 * Reference: https://craig.is/writing/chrome-logger
 */
class LogBrowserConsole extends LogCommand
{
    private const HEADER_NAME = 'X-ChromeLogger-Data';
    private const MAX_HEADER_SIZE = 240000; // Chrome header size limit ~256KB with safety margin

    private ?LogBrowserConsoleFormat $formatter = null;
    private bool $headerSent = false;
    private int $currentSize = 0;

    /**
     * Constructor to initialize browser console logging.
     *
     * @param string $logLevel The logging level (debug, info, warning, error, etc.)
     */
    public function __construct(string $logLevel)
    {
        parent::__construct($logLevel);

        // Register shutdown function to send headers at end of request
        register_shutdown_function(function (): void {
            $this->sendHeader();
        });
    }

    private function getFormatter(): LogBrowserConsoleFormat
    {
        return $this->formatter ??= new LogBrowserConsoleFormat();
    }

    protected function log(array $logData): bool
    {
        // Use stream if set (for testing)
        if ($this->stream()) {
            $output = $this->getFormatter()->__invoke($logData);
            fwrite($this->stream(), $output . "\n");
            return true;
        }

        // Check if headers are already sent
        if (headers_sent()) {
            return false;
        }

        // Accumulate row in formatter
        $this->getFormatter()->__invoke($logData);

        // Check size limits
        $rows = $this->getFormatter()->getRows();
        $rowSize = strlen(json_encode(end($rows)) ?: '');

        // Check size limits
        if ($this->currentSize + $rowSize > self::MAX_HEADER_SIZE) {
            // Send current batch (includes the new row), then reset for next batch
            $this->sendHeader();
            $this->getFormatter()->reset();
            $this->currentSize = 0;
            $this->headerSent = false;
        } else {
            $this->currentSize += $rowSize;
        }

        return true;
    }

    /**
     * Send the ChromeLogger header with all collected logs
     */
    private function sendHeader(): void
    {
        if ($this->headerSent) {
            return;
        }

        // Don't send if headers already sent
        if (headers_sent()) {
            return;
        }

        $json = $this->getFormatter()->__invoke([]);
        if ($json === '{}') {
            return;
        }

        $header = base64_encode($json);

        // Send header
        header(self::HEADER_NAME . ': ' . $header);
        $this->headerSent = true;
    }

    /**
     * Get collected rows (for testing)
     *
     * @return array<int, array<int, mixed>>
     */
    public function getRows(): array
    {
        return $this->getFormatter()->getRows();
    }

    /**
     * Manually trigger header send (for testing or early flush)
     */
    public function flush(): void
    {
        $this->sendHeader();
    }
}
