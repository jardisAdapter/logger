<?php

namespace JardisAdapter\Logger\Tests\Integration\Handler;

use JardisAdapter\Logger\Handler\LogDatabase;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel as PsrLogLevel;

class LogDatabaseTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function createTable(string $tableName, array $columns): void
    {
        $columnDefinitions = implode(', ', $columns);
        $sql = "CREATE TABLE {$tableName} ({$columnDefinitions})";
        $this->pdo->exec($sql);
    }

    private function dropTableIfExists(string $tableName): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS {$tableName}");
    }

    public function testLogSuccess(): void
    {
        $this->createTable('logContextData', [
            'id INTEGER PRIMARY KEY AUTOINCREMENT',
            'context TEXT NOT NULL',
            'level TEXT NOT NULL',
            'message TEXT NOT NULL',
            'data TEXT NOT NULL',
            'createdAt DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]);

        $this->pdo->exec('CREATE INDEX idx_context ON logContextData(context)');

        $logger = new LogDatabase(PsrLogLevel::INFO, $this->pdo);

        $result = $logger(PsrLogLevel::INFO, 'Test message {key}', ['key' => 'value']);

        $this->assertStringContainsString(
            '"context": "", "level": "info", "message": "Test message value", "data": "{"key":"value"}"',
            $result
        );
    }

    public function testLogAdditionalRecordDataFieldsSuccess(): void
    {
        $this->createTable('logContextData', [
            'id INTEGER PRIMARY KEY AUTOINCREMENT',
            'context TEXT NOT NULL',
            'level TEXT NOT NULL',
            'message TEXT NOT NULL',
            'data TEXT NOT NULL',
            'myOwnContent TEXT NOT NULL',
            'createdAt DATETIME DEFAULT CURRENT_TIMESTAMP'
        ]);

        $this->pdo->exec('CREATE INDEX idx_contect ON logContextData(context)');

        $logger = new LogDatabase(PsrLogLevel::INFO, $this->pdo);
        $logger->logData()->addField('myOwnContent', fn() => 'that is myOwnContent');

        $result = $logger(PsrLogLevel::INFO, 'Test message {myOwnContent}', ['key' => 'value']);

        $this->assertStringContainsString(
            '{ "context": "", "level": "info", "message": "Test message that is myOwnContent", "myOwnContent": "that is myOwnContent", "data": "{"key":"value"}" }
',
            $result
        );
    }
}
