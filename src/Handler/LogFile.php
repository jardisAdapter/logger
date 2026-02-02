<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use InvalidArgumentException;

/**
 * Returns log entries to common file
 * Uses lazy file opening - file is only opened when first log is written
 */
class LogFile extends LogCommand
{
    private string $filePath;
    private bool $isOpened = false;

    /**
     * Constructor for initializing the logger with a specified log level and output file.
     *
     * @param string $logLevel The logging level, e.g., 'info', 'debug', etc.
     * @param string $file The file path where logs should be written.
     * @return void
     * @throws InvalidArgumentException If the directory for the provided file does not exist.
     */
    public function __construct(string $logLevel, string $file)
    {
        $directory = dirname($file);
        if (!is_dir($directory) || $directory === '.') {
            throw new InvalidArgumentException("Directory not found : " . $directory);
        }

        $this->filePath = $file;

        parent::__construct($logLevel);
    }

    protected function log(array $logData): bool
    {
        // Lazy open: only open file when first log is written
        if (!$this->isOpened && !$this->stream()) {
            $this->openFile();
        }

        return parent::log($logData);
    }

    private function openFile(): void
    {
        $stream = @fopen($this->filePath, 'a');

        if ($stream === false) {
            throw new \RuntimeException("Failed to open log file: {$this->filePath}");
        }

        $this->setStream($stream, true); // We own this stream
        $this->isOpened = true;
    }
}
