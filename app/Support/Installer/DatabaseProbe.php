<?php

declare(strict_types=1);

namespace App\Support\Installer;

use App\Enums\DatabaseProblem;
use InvalidArgumentException;
use PDO;
use PDOException;
use SensitiveParameter;

/**
 * Talks to MySQL with credentials that are not in the application's config
 * yet. The installer has to know whether they work before it writes them
 * anywhere, so this uses PDO directly instead of Laravel's connection.
 */
final class DatabaseProbe
{
    private ?PDOException $lastError = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        #[SensitiveParameter] private readonly string $password,
    ) {}

    /**
     * Can we sign in to the server at all? Says nothing about a database.
     */
    public function canConnect(): bool
    {
        return $this->connect() instanceof PDO;
    }

    public function databaseExists(string $database): bool
    {
        $pdo = $this->connect();

        if (! $pdo instanceof PDO) {
            return false;
        }

        $statement = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$database]);

        return $statement->fetchColumn() !== false;
    }

    public function createDatabase(string $database): bool
    {
        $pdo = $this->connect();

        if (! $pdo instanceof PDO) {
            return false;
        }

        try {
            $pdo->exec('CREATE DATABASE IF NOT EXISTS '.$this->identifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            return true;
        } catch (PDOException $exception) {
            $this->lastError = $exception;

            return false;
        }
    }

    /**
     * Can we use this particular database with these credentials?
     */
    public function canUse(string $database): bool
    {
        return $this->connect($database) instanceof PDO;
    }

    public function serverVersion(): ?string
    {
        $version = $this->connect()?->query('SELECT VERSION()')->fetchColumn();

        return is_string($version) ? $version : null;
    }

    public function problem(): ?DatabaseProblem
    {
        if (! $this->lastError instanceof PDOException) {
            return null;
        }

        return DatabaseProblem::fromErrorCode((int) ($this->lastError->errorInfo[1] ?? $this->lastError->getCode()));
    }

    public function errorMessage(): ?string
    {
        return $this->lastError?->getMessage();
    }

    private function connect(?string $database = null): ?PDO
    {
        $dsn = "mysql:host={$this->host};port={$this->port};charset=utf8mb4";

        if ($database !== null) {
            $dsn .= ";dbname={$database}";
        }

        try {
            $pdo = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                // A wrong host should fail in seconds, not hang the installer.
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $this->lastError = null;

            return $pdo;
        } catch (PDOException $exception) {
            $this->lastError = $exception;

            return null;
        }
    }

    /**
     * Database names cannot be bound as parameters, so the name is checked
     * against a strict pattern before it goes into a statement.
     */
    private function identifier(string $database): string
    {
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) !== 1) {
            throw new InvalidArgumentException("[{$database}] is not a usable database name. Use letters, digits and underscores only.");
        }

        return '`'.$database.'`';
    }
}
