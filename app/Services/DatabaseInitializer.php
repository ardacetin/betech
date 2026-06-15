<?php

declare(strict_types=1);

namespace App\Services;

use PDOException;
use RuntimeException;

class DatabaseInitializer
{
    public function __construct(
        private readonly DatabaseService $databaseService,
        private readonly string $schemaPath,
        private readonly string $seedsPath
    ) {
    }

    public function initialize(): DatabaseInitializationResult
    {
        $credentialError = $this->validateCredentials($this->databaseService->getConfig());

        if ($credentialError !== null) {
            return new DatabaseInitializationResult(false, $credentialError);
        }

        if (!is_readable($this->schemaPath)) {
            return new DatabaseInitializationResult(
                false,
                sprintf('Database schema file is missing or unreadable: %s', $this->schemaPath)
            );
        }

        try {
            $connection = $this->databaseService->getConnection();
            $warnings = [];

            if (!$this->assetsTableExists($connection)) {
                $this->applySqlFile($connection, $this->schemaPath);
            } else {
                if (!$this->categoriesFieldsColumnExists($connection)) {
                    $connection->query(
                        'ALTER TABLE categories ADD COLUMN fields JSON NULL AFTER slug'
                    );
                    $warnings[] = 'Applied migration: added categories.fields JSON column.';
                }

                if (!$this->usersTableExists($connection)) {
                    $this->applySqlFile($connection, $this->getUsersTableMigrationPath());
                    $warnings[] = 'Applied migration: created users table.';
                }

                if (!$this->assetHistoriesTableExists($connection)) {
                    $this->applySqlFile($connection, $this->getAssetHistoriesTableMigrationPath());
                    $warnings[] = 'Applied migration: created asset_histories table.';
                }

                if (!$this->settingsTableExists($connection)) {
                    $this->applySqlFile($connection, $this->getSettingsTableMigrationPath());
                    $warnings[] = 'Applied migration: created settings table.';
                }
            }

            if (is_readable($this->seedsPath)) {
                $this->applySqlFile($connection, $this->seedsPath);
            } else {
                $warnings[] = 'Seed file is missing. Default categories were not loaded.';
            }

            return new DatabaseInitializationResult(true, null, $warnings);
        } catch (PDOException $exception) {
            return new DatabaseInitializationResult(
                false,
                'Database connection failed: ' . $exception->getMessage()
            );
        } catch (RuntimeException $exception) {
            return new DatabaseInitializationResult(false, $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateCredentials(array $config): ?string
    {
        $host = trim((string) ($config['host'] ?? ''));
        $database = trim((string) ($config['database'] ?? ''));
        $username = trim((string) ($config['username'] ?? ''));

        if ($host === '' || $database === '' || $username === '') {
            return 'Database credentials are incomplete. Set DB_HOST, DB_DATABASE, and DB_USERNAME in .env.';
        }

        return null;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function assetsTableExists(object $connection): bool
    {
        $statement = $connection->query("SHOW TABLES LIKE 'assets'");

        return $statement !== false && $statement->rowCount() > 0;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function usersTableExists(object $connection): bool
    {
        $statement = $connection->query("SHOW TABLES LIKE 'users'");

        return $statement !== false && $statement->rowCount() > 0;
    }

    private function getUsersTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/001_create_users_table.sql';
    }

    private function getAssetHistoriesTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/002_create_asset_histories_table.sql';
    }

    private function getSettingsTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/003_create_settings_table.sql';
    }

    /**
     * @param object $connection Medoo instance
     */
    private function settingsTableExists(object $connection): bool
    {
        $statement = $connection->query("SHOW TABLES LIKE 'settings'");

        return $statement !== false && $statement->rowCount() > 0;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function assetHistoriesTableExists(object $connection): bool
    {
        $statement = $connection->query("SHOW TABLES LIKE 'asset_histories'");

        return $statement !== false && $statement->rowCount() > 0;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function categoriesFieldsColumnExists(object $connection): bool
    {
        $statement = $connection->query("SHOW COLUMNS FROM categories LIKE 'fields'");

        return $statement !== false && $statement->rowCount() > 0;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function applySqlFile(object $connection, string $sqlPath): void
    {
        $sql = file_get_contents($sqlPath);

        if ($sql === false) {
            throw new RuntimeException(sprintf('Unable to read SQL file: %s', $sqlPath));
        }

        foreach ($this->parseSqlStatements($sql) as $statement) {
            $connection->query($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function parseSqlStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $filteredLines = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || str_starts_with($trimmedLine, '--')) {
                continue;
            }

            $filteredLines[] = $line;
        }

        $executableSql = trim(implode("\n", $filteredLines));
        $statements = array_filter(
            array_map('trim', explode(';', $executableSql)),
            static fn (string $statement): bool => $statement !== ''
        );

        return array_values($statements);
    }
}
