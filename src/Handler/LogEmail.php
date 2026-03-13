<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use InvalidArgumentException;

/**
 * Sends log entries via email using SMTP
 */
class LogEmail extends LogCommand
{
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $fromEmail;
    private string $fromName;
    private string $toEmail;
    private string $subject;
    private bool $useHtml;
    private bool $useTls;
    private int $rateLimitSeconds;
    private ?int $lastSentTime = null;

    /**
     * Constructor to initialize email logging.
     *
     * @param string $logLevel The logging level
     * @param string $toEmail Recipient email address
     * @param string $fromEmail Sender email address
     * @param string $subject Email subject line
     * @param string $smtpHost SMTP server hostname
     * @param int $smtpPort SMTP server port (default: 1025 for MailHog, 587 for production)
     * @param string $smtpUsername SMTP username (optional)
     * @param string $smtpPassword SMTP password (optional)
     * @param string $fromName Sender name (default: 'Logger')
     * @param bool $useHtml Send as HTML email (default: false)
     * @param bool $useTls Use TLS encryption (default: false)
     * @param int $rateLimitSeconds Minimum seconds between emails (default: 60)
     * @throws InvalidArgumentException If email addresses are invalid
     */
    public function __construct(
        string $logLevel,
        string $toEmail,
        string $fromEmail,
        string $subject = 'Application Log',
        string $smtpHost = 'localhost',
        int $smtpPort = 1025,
        string $smtpUsername = '',
        string $smtpPassword = '',
        string $fromName = 'Logger',
        bool $useHtml = false,
        bool $useTls = false,
        int $rateLimitSeconds = 60
    ) {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid recipient email: {$toEmail}");
        }

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid sender email: {$fromEmail}");
        }

        $this->toEmail = $toEmail;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->subject = $subject;
        $this->smtpHost = $smtpHost;
        $this->smtpPort = $smtpPort;
        $this->smtpUsername = $smtpUsername;
        $this->smtpPassword = $smtpPassword;
        $this->useHtml = $useHtml;
        $this->useTls = $useTls;
        $this->rateLimitSeconds = $rateLimitSeconds;

        parent::__construct($logLevel);
    }

    protected function log(array $logData): bool
    {
        // Rate limiting
        if ($this->isRateLimited()) {
            return false;
        }

        $this->lastSentTime = time();

        // Use stream if set (for testing)
        if ($this->stream()) {
            return parent::log($logData);
        }

        $logMessage = $logData['message'] ?? '';
        return $this->sendEmail($logMessage, $logData);
    }

    private function isRateLimited(): bool
    {
        if ($this->lastSentTime === null) {
            return false;
        }

        return (time() - $this->lastSentTime) < $this->rateLimitSeconds;
    }

    /**
     * @param array<int|string, mixed> $logData
     */
    private function sendEmail(string $logMessage, array $logData): bool
    {
        try {
            $socket = $this->connectToSmtp();
            if (!$socket) {
                return false;
            }

            $this->smtpCommand($socket, null, 220); // Read greeting
            $this->smtpCommand($socket, "EHLO {$this->smtpHost}\r\n", 250);

            if ($this->useTls) {
                $this->smtpCommand($socket, "STARTTLS\r\n", 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    return false;
                }
                $this->smtpCommand($socket, "EHLO {$this->smtpHost}\r\n", 250);
            }

            if ($this->smtpUsername !== '') {
                $this->smtpCommand($socket, "AUTH LOGIN\r\n", 334);
                $this->smtpCommand($socket, base64_encode($this->smtpUsername) . "\r\n", 334);
                $this->smtpCommand($socket, base64_encode($this->smtpPassword) . "\r\n", 235);
            }

            $this->smtpCommand($socket, "MAIL FROM:<{$this->fromEmail}>\r\n", 250);
            $this->smtpCommand($socket, "RCPT TO:<{$this->toEmail}>\r\n", 250);
            $this->smtpCommand($socket, "DATA\r\n", 354);

            $emailContent = $this->buildEmailContent($logMessage, $logData);
            $this->smtpCommand($socket, $emailContent . "\r\n.\r\n", 250);
            $this->smtpCommand($socket, "QUIT\r\n", 221);

            fclose($socket);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return resource|false
     */
    private function connectToSmtp()
    {
        $socket = @fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 10);
        return $socket ?: false;
    }

    /**
     * @param resource $socket
     */
    private function smtpCommand($socket, ?string $command, int $expectedCode): void
    {
        if ($command !== null) {
            fwrite($socket, $command);
        }

        // Read all lines of response (handle multiline responses)
        $response = '';
        do {
            $line = fgets($socket, 515);
            $response .= $line;
            // Multiline responses have a dash after the code (e.g., "250-" vs "250 ")
            $isLastLine = isset($line[3]) && $line[3] === ' ';
        } while (!$isLastLine && !feof($socket));

        $responseCode = (int) substr($response, 0, 3);

        if ($responseCode !== $expectedCode) {
            throw new \RuntimeException("SMTP Error: Expected {$expectedCode}, got {$responseCode}: {$response}");
        }
    }

    /**
     * @param array<int|string, mixed> $logData
     */
    private function buildEmailContent(string $logMessage, array $logData): string
    {
        $contentType = $this->useHtml ? 'text/html' : 'text/plain';

        $headers = [
            "From: {$this->fromName} <{$this->fromEmail}>",
            "To: <{$this->toEmail}>",
            "Subject: {$this->subject}",
            "MIME-Version: 1.0",
            "Content-Type: {$contentType}; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "Date: " . date('r'),
            "Message-ID: <" . md5(uniqid((string) time())) . "@{$this->smtpHost}>",
        ];

        $body = $this->formatEmailBody($logMessage, $logData);

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    /**
     * @param array<int|string, mixed> $logData
     */
    private function formatEmailBody(string $logMessage, array $logData): string
    {
        if ($this->useHtml) {
            return $this->formatHtmlBody($logMessage, $logData);
        }

        return $this->formatPlainTextBody($logMessage, $logData);
    }

    /**
     * @param array<int|string, mixed> $logData
     */
    private function formatPlainTextBody(string $logMessage, array $logData): string
    {
        $body = "Log Message:\n";
        $body .= str_repeat('=', 60) . "\n\n";
        $body .= $logMessage . "\n\n";

        if (!empty($logData)) {
            $body .= "Additional Data:\n";
            $body .= str_repeat('-', 60) . "\n";
            foreach ($logData as $key => $value) {
                $body .= "{$key}: " . $this->formatValue($value) . "\n";
            }
        }

        $body .= "\n" . str_repeat('=', 60) . "\n";
        $body .= "Timestamp: " . date('Y-m-d H:i:s');

        return $body;
    }

    /**
     * @param array<int|string, mixed> $logData
     */
    private function formatHtmlBody(string $logMessage, array $logData): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>body{font-family:Arial,sans-serif;margin:20px;}';
        $html .= '.log-message{background:#f5f5f5;padding:15px;border-left:4px solid #007bff;margin:20px 0;}';
        $html .= '.log-data{margin:20px 0;}.log-data table{border-collapse:collapse;width:100%;}';
        $html .= '.log-data th,.log-data td{border:1px solid #ddd;padding:8px;text-align:left;}';
        $html .= '.log-data th{background-color:#007bff;color:white;}';
        $html .= '.timestamp{color:#666;font-size:0.9em;}</style></head><body>';

        $html .= '<h2>Log Message</h2>';
        $html .= '<div class="log-message">' . htmlspecialchars($logMessage) . '</div>';

        if (!empty($logData)) {
            $html .= '<h3>Additional Data</h3><div class="log-data"><table>';
            $html .= '<tr><th>Key</th><th>Value</th></tr>';
            foreach ($logData as $key => $value) {
                $html .= '<tr><td>' . htmlspecialchars((string) $key) . '</td>';
                $html .= '<td>' . htmlspecialchars($this->formatValue($value) ?: '') . '</td></tr>';
            }
            $html .= '</table></div>';
        }

        $html .= '<p class="timestamp">Timestamp: ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '</body></html>';

        return $html;
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_PRETTY_PRINT);
            return $encoded !== false ? $encoded : '[]';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }
}
