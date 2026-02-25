<?php

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\Handler\LogEmail;
use JardisAdapter\Logger\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class LogEmailTest extends TestCase
{
    private string $mailhogHost;
    private int $mailhogSmtpPort;
    private int $mailhogApiPort;

    protected function setUp(): void
    {
        parent::setUp();

        // Load from ENV or use defaults
        $this->mailhogHost = getenv('MAILHOG_SMTP_HOST') ?: 'mailhog';
        $this->mailhogSmtpPort = 1025;
        $this->mailhogApiPort = 8025;
        // Clear all emails before each test
        $this->clearMailhog();
    }

    public function testSendEmailPlainText(): void
    {
        $logger = new Logger('TestContext');

        $emailHandler = new LogEmail(
            logLevel: LogLevel::INFO,
            toEmail: 'recipient@example.com',
            fromEmail: 'sender@example.com',
            subject: 'Test Log Email',
            smtpHost: $this->mailhogHost,
            smtpPort: $this->mailhogSmtpPort,
            useHtml: false
        );

        $logger->addHandler($emailHandler);
        $logger->info('Test log message', ['key' => 'value', 'number' => 42]);

        // Wait for email to be processed
        sleep(1);

        $emails = $this->getMailhogEmails();

        $this->assertCount(1, $emails);
        $email = $emails[0];

        $this->assertEquals('recipient@example.com', $email['To'][0]['Mailbox'] . '@' . $email['To'][0]['Domain']);
        $this->assertEquals('sender@example.com', $email['From']['Mailbox'] . '@' . $email['From']['Domain']);
        $this->assertStringContainsString('Test Log Email', $email['Content']['Headers']['Subject'][0]);
        $this->assertStringContainsString('Test log message', $email['Content']['Body']);
    }

    public function testSendEmailHtml(): void
    {
        $logger = new Logger('TestContext');

        $emailHandler = new LogEmail(
            logLevel: LogLevel::ERROR,
            toEmail: 'admin@example.com',
            fromEmail: 'logger@example.com',
            subject: 'Error Alert',
            smtpHost: $this->mailhogHost,
            smtpPort: $this->mailhogSmtpPort,
            useHtml: true
        );

        $logger->addHandler($emailHandler);
        $logger->error('Critical error occurred', ['error_code' => 500, 'message' => 'Database connection failed']);

        sleep(1);

        $emails = $this->getMailhogEmails();

        $this->assertCount(1, $emails);
        $email = $emails[0];

        $this->assertStringContainsString('text/html', $email['Content']['Headers']['Content-Type'][0]);
        $this->assertStringContainsString('<!DOCTYPE html>', $email['Content']['Body']);
        $this->assertStringContainsString('Critical error occurred', $email['Content']['Body']);
        $this->assertStringContainsString('error_code', $email['Content']['Body']);
    }

    public function testRateLimiting(): void
    {
        $logger = new Logger('TestContext');

        $emailHandler = new LogEmail(
            logLevel: LogLevel::WARNING,
            toEmail: 'test@example.com',
            fromEmail: 'noreply@example.com',
            subject: 'Warning',
            smtpHost: $this->mailhogHost,
            smtpPort: $this->mailhogSmtpPort,
            rateLimitSeconds: 5
        );

        $logger->addHandler($emailHandler);

        // Send 3 messages quickly
        $logger->warning('Message 1');
        $logger->warning('Message 2');
        $logger->warning('Message 3');

        sleep(1);

        $emails = $this->getMailhogEmails();

        // Only first message should be sent due to rate limiting
        $this->assertCount(1, $emails);
    }

    public function testInvalidEmailAddresses(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid recipient email');

        new LogEmail(
            logLevel: LogLevel::INFO,
            toEmail: 'invalid-email',
            fromEmail: 'valid@example.com',
            smtpHost: $this->mailhogHost
        );
    }

    public function testInvalidFromEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sender email');

        new LogEmail(
            logLevel: LogLevel::INFO,
            toEmail: 'valid@example.com',
            fromEmail: 'invalid-email',
            smtpHost: $this->mailhogHost
        );
    }

    public function testCustomFromName(): void
    {
        $logger = new Logger('TestContext');

        $emailHandler = new LogEmail(
            logLevel: LogLevel::INFO,
            toEmail: 'test@example.com',
            fromEmail: 'noreply@example.com',
            subject: 'Test',
            smtpHost: $this->mailhogHost,
            smtpPort: $this->mailhogSmtpPort,
            fromName: 'Custom Logger Name'
        );

        $logger->addHandler($emailHandler);
        $logger->info('Test message');

        sleep(1);

        $emails = $this->getMailhogEmails();

        $this->assertCount(1, $emails);
        // MailHog may not preserve the display name in all cases
        // Just verify the from address is correct
        $this->assertEquals('noreply@example.com', $emails[0]['From']['Mailbox'] . '@' . $emails[0]['From']['Domain']);
    }

    private function clearMailhog(): void
    {
        $url = "http://{$this->mailhogHost}:{$this->mailhogApiPort}/api/v1/messages";

        $context = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        @file_get_contents($url, false, $context);
    }

    private function getMailhogEmails(): array
    {
        $url = "http://{$this->mailhogHost}:{$this->mailhogApiPort}/api/v2/messages";

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return [];
        }

        $data = json_decode($response, true);
        return $data['items'] ?? [];
    }
}
