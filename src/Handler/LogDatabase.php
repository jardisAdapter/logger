<?php

declare(strict_types=1);

namespace JardisAdapter\Logger\Handler;

use PDO;

/**
 * LogDatabase is responsible for persisting log entries in a specific database table.
 * It extends LogCommand to provide context and log level functionality, while using a
 * repository to handle database interactions.
 */
class LogDatabase extends LogCommand
{
    private PDO $pdo;
    private string $logTable;
    private ?string $identifierQuote = null;

    /**
     * Constructor for initializing the logging system.
     *
     * @param string $logLevel The log level to set for this instance.
     * @param PDO $pdo A PDO instance for database connections.
     * @param string|null $logTable The name of the log table, defaults to 'logContextData' if not provided.
     * @return void
     */
    public function __construct(string $logLevel, PDO $pdo, ?string $logTable = null)
    {
        $this->pdo = $pdo;
        $this->logTable = $logTable ?? 'logContextData';

        parent::__construct($logLevel);
    }

    /**
     * Lazy initialization of identifier quote character.
     */
    private function getIdentifierQuote(): string
    {
        return $this->identifierQuote ??= $this->detectIdentifierQuote();
    }

    protected function log(array $logData): bool
    {
        $logData['data'] = json_encode($logData['data'] ?? []);

        $statement = $this->pdo->prepare(
            $this->buildQuery(
                array_keys($logData)
            )
        );

        return $statement->execute(array_values($logData));
    }

    /** @param array<int, string> $fields  */
    protected function buildQuery(array $fields): string
    {
        $placeholders = array_map(fn($column) => "?", $fields);
        $escapedFields = array_map(fn($field) => $this->escapeIdentifier($field), $fields);

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->escapeIdentifier($this->logTable),
            implode(', ', $escapedFields),
            implode(', ', $placeholders)
        );
    }

    /**
     * Detects the appropriate identifier quote character based on the PDO driver.
     * Cached once during construction for performance.
     *
     * @return string The quote character for the database driver.
     */
    private function detectIdentifierQuote(): string
    {
        return match ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => '`',
            'pgsql', 'sqlite' => '"',
            default => '"'  // ANSI SQL Standard fallback
        };
    }

    /**
     * Escapes database identifiers (table/column names) to prevent SQL injection.
     * Supports MySQL, MariaDB, PostgreSQL, and SQLite.
     *
     * @param string $identifier The identifier to escape.
     * @return string The escaped identifier.
     */
    protected function escapeIdentifier(string $identifier): string
    {
        $quote = $this->getIdentifierQuote();
        return $quote . str_replace($quote, $quote . $quote, $identifier) . $quote;
    }
}
