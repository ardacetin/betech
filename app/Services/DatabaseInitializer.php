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

                if (!$this->usersStatusColumnExists($connection)) {
                    $this->applySqlFile($connection, $this->getUsersStatusColumnMigrationPath());
                    $warnings[] = 'Applied migration: added users.status column.';
                }
            }

            if ($this->usersTableExists($connection)) {
                foreach ($this->patchUsersTableColumns($connection) as $warning) {
                    $warnings[] = $warning;
                }
            }

            if ($this->assetsTableExists($connection)) {
                foreach ($this->patchLocationTracking($connection) as $warning) {
                    $warnings[] = $warning;
                }

                foreach ($this->patchSoftwareLicenseManagement($connection) as $warning) {
                    $warnings[] = $warning;
                }
            }

            foreach ($this->patchPersonnelSeparation($connection) as $warning) {
                $warnings[] = $warning;
            }

            if (is_readable($this->seedsPath)) {
                $this->applySqlFile($connection, $this->seedsPath);
            } else {
                $warnings[] = 'Seed file is missing. Default categories were not loaded.';
            }

            if ($this->usersTableExists($connection)
                && $this->columnExists($connection, 'users', 'password_hash')) {
                foreach ($this->patchDefaultAdminPassword($connection) as $warning) {
                    $warnings[] = $warning;
                }
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

    private function getUsersStatusColumnMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/004_add_users_status_column.sql';
    }

    /**
     * Self-heal legacy users tables by adding auth and RBAC columns when missing.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchUsersTableColumns(object $connection): array
    {
        $warnings = [];

        if (!$this->columnExists($connection, 'users', 'password_hash')) {
            $connection->query(
                'ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email'
            );
            $warnings[] = 'Self-healed users table: added password_hash column.';
        }

        if (!$this->columnExists($connection, 'users', 'auth_provider')) {
            $passwordAnchor = $this->columnExists($connection, 'users', 'password_hash')
                ? 'password_hash'
                : 'email';
            $connection->query(
                sprintf(
                    "ALTER TABLE users ADD COLUMN auth_provider VARCHAR(32) NOT NULL DEFAULT 'local' AFTER %s",
                    $passwordAnchor
                )
            );
            $warnings[] = 'Self-healed users table: added auth_provider column.';
        }

        if (!$this->columnExists($connection, 'users', 'provider_subject')) {
            $providerAnchor = $this->columnExists($connection, 'users', 'auth_provider')
                ? 'auth_provider'
                : ($this->columnExists($connection, 'users', 'password_hash') ? 'password_hash' : 'email');
            $connection->query(
                sprintf(
                    'ALTER TABLE users ADD COLUMN provider_subject VARCHAR(255) NULL AFTER %s',
                    $providerAnchor
                )
            );
            $warnings[] = 'Self-healed users table: added provider_subject column.';
        }

        if (!$this->columnExists($connection, 'users', 'last_login_at')) {
            $afterColumn = $this->columnExists($connection, 'users', 'status') ? 'status' : 'department';
            $connection->query(
                sprintf(
                    'ALTER TABLE users ADD COLUMN last_login_at DATETIME NULL AFTER %s',
                    $afterColumn
                )
            );
            $warnings[] = 'Self-healed users table: added last_login_at column.';
        }

        if (!$this->columnExists($connection, 'users', 'role')) {
            $afterColumn = $this->columnExists($connection, 'users', 'status') ? 'status' : 'department';
            $connection->query(
                sprintf(
                    "ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'end_user' AFTER %s",
                    $afterColumn
                )
            );
            $warnings[] = 'Self-healed users table: added role column.';
        }

        if ($this->columnExists($connection, 'users', 'role')
            && !$this->indexExists($connection, 'users', 'idx_users_role')) {
            $connection->query('ALTER TABLE users ADD KEY idx_users_role (role)');
            $warnings[] = 'Self-healed users table: added idx_users_role index.';
        }

        if ($this->columnExists($connection, 'users', 'auth_provider')
            && !$this->indexExists($connection, 'users', 'idx_users_auth_provider')) {
            $connection->query('ALTER TABLE users ADD KEY idx_users_auth_provider (auth_provider)');
            $warnings[] = 'Self-healed users table: added idx_users_auth_provider index.';
        }

        if ($this->columnExists($connection, 'users', 'provider_subject')
            && !$this->indexExists($connection, 'users', 'idx_users_provider_subject')) {
            $connection->query('ALTER TABLE users ADD KEY idx_users_provider_subject (provider_subject)');
            $warnings[] = 'Self-healed users table: added idx_users_provider_subject index.';
        }

        if ($this->columnExists($connection, 'users', 'role')) {
            $connection->query(
                "UPDATE users SET role = 'super_admin' WHERE email = 'admin@betech.local' AND role = 'end_user'"
            );
        }

        return $warnings;
    }

    /**
     * Migrate legacy unified users table into personnel + slim auth users, and rename FK columns.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchPersonnelSeparation(object $connection): array
    {
        $warnings = [];

        if (!$this->tableExists($connection, 'personnel')) {
            $this->applySqlFile($connection, $this->getPersonnelTableMigrationPath());
            $warnings[] = 'Self-healed database: created personnel table.';
        }

        if ($this->usersTableExists($connection)
            && $this->tableExists($connection, 'personnel')
            && $this->columnExists($connection, 'users', 'role')) {
            $connection->query(
                "INSERT INTO personnel (id, name, email, department, title, external_id, provider, status, created_at)
                SELECT u.id, u.name, u.email, u.department, NULL, u.external_id,
                    COALESCE(u.auth_provider, 'local'), COALESCE(u.status, 'active'), u.created_at
                FROM users u
                WHERE u.role = 'end_user'
                ON DUPLICATE KEY UPDATE
                    personnel.name = VALUES(name),
                    personnel.email = VALUES(email),
                    personnel.department = VALUES(department),
                    personnel.external_id = COALESCE(personnel.external_id, VALUES(external_id)),
                    personnel.provider = VALUES(provider),
                    personnel.status = VALUES(status)"
            );
            $warnings[] = 'Self-healed database: migrated personnel records from users table.';
        }

        if ($this->assetsTableExists($connection)
            && $this->columnExists($connection, 'assets', 'user_id')
            && !$this->columnExists($connection, 'assets', 'personnel_id')) {
            $this->dropForeignKeyIfExists($connection, 'assets', 'fk_assets_user_id');
            $connection->query(
                'ALTER TABLE assets CHANGE user_id personnel_id BIGINT UNSIGNED DEFAULT NULL'
            );
            $warnings[] = 'Self-healed assets table: renamed user_id to personnel_id.';
        }

        if ($this->assetsTableExists($connection)
            && $this->columnExists($connection, 'assets', 'personnel_id')
            && !$this->indexExists($connection, 'assets', 'idx_assets_personnel_id')) {
            if ($this->indexExists($connection, 'assets', 'idx_assets_user_id')) {
                $connection->query('ALTER TABLE assets DROP INDEX idx_assets_user_id');
            }

            $connection->query('ALTER TABLE assets ADD KEY idx_assets_personnel_id (personnel_id)');
            $warnings[] = 'Self-healed assets table: added idx_assets_personnel_id index.';
        }

        if ($this->assetsTableExists($connection)
            && $this->tableExists($connection, 'personnel')
            && $this->columnExists($connection, 'assets', 'personnel_id')
            && !$this->foreignKeyExists($connection, 'assets', 'fk_assets_personnel_id')) {
            $connection->query(
                'ALTER TABLE assets ADD CONSTRAINT fk_assets_personnel_id FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
            $warnings[] = 'Self-healed assets table: added fk_assets_personnel_id foreign key.';
        }

        if ($this->tableExists($connection, 'license_assignments')
            && $this->columnExists($connection, 'license_assignments', 'user_id')
            && !$this->columnExists($connection, 'license_assignments', 'personnel_id')) {
            $this->dropForeignKeyIfExists($connection, 'license_assignments', 'fk_license_assignments_user_id');
            $connection->query(
                'ALTER TABLE license_assignments CHANGE user_id personnel_id BIGINT UNSIGNED DEFAULT NULL'
            );
            $warnings[] = 'Self-healed license_assignments table: renamed user_id to personnel_id.';
        }

        if ($this->tableExists($connection, 'license_assignments')
            && $this->columnExists($connection, 'license_assignments', 'personnel_id')
            && !$this->indexExists($connection, 'license_assignments', 'idx_license_assignments_personnel_id')) {
            if ($this->indexExists($connection, 'license_assignments', 'idx_license_assignments_user_id')) {
                $connection->query('ALTER TABLE license_assignments DROP INDEX idx_license_assignments_user_id');
            }

            $connection->query('ALTER TABLE license_assignments ADD KEY idx_license_assignments_personnel_id (personnel_id)');
            $warnings[] = 'Self-healed license_assignments table: added idx_license_assignments_personnel_id index.';
        }

        if ($this->tableExists($connection, 'license_assignments')
            && $this->tableExists($connection, 'personnel')
            && $this->columnExists($connection, 'license_assignments', 'personnel_id')
            && !$this->foreignKeyExists($connection, 'license_assignments', 'fk_license_assignments_personnel_id')) {
            $connection->query(
                'ALTER TABLE license_assignments ADD CONSTRAINT fk_license_assignments_personnel_id FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE ON UPDATE CASCADE'
            );
            $warnings[] = 'Self-healed license_assignments table: added fk_license_assignments_personnel_id foreign key.';
        }

        if ($this->tableExists($connection, 'asset_histories')
            && $this->columnExists($connection, 'asset_histories', 'target_user_id')
            && !$this->columnExists($connection, 'asset_histories', 'target_personnel_id')) {
            $this->dropForeignKeyIfExists($connection, 'asset_histories', 'fk_asset_histories_target_user_id');
            $connection->query(
                'ALTER TABLE asset_histories CHANGE target_user_id target_personnel_id BIGINT UNSIGNED DEFAULT NULL'
            );
            $warnings[] = 'Self-healed asset_histories table: renamed target_user_id to target_personnel_id.';
        }

        if ($this->tableExists($connection, 'asset_histories')
            && $this->tableExists($connection, 'personnel')
            && $this->columnExists($connection, 'asset_histories', 'target_personnel_id')
            && !$this->foreignKeyExists($connection, 'asset_histories', 'fk_asset_histories_target_personnel_id')) {
            $connection->query(
                'ALTER TABLE asset_histories ADD CONSTRAINT fk_asset_histories_target_personnel_id FOREIGN KEY (target_personnel_id) REFERENCES personnel (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
            $warnings[] = 'Self-healed asset_histories table: added fk_asset_histories_target_personnel_id foreign key.';
        }

        if ($this->usersTableExists($connection) && $this->columnExists($connection, 'users', 'role')) {
            $connection->query(
                "DELETE FROM users WHERE role = 'end_user' OR role = '' OR role IS NULL"
            );
            $warnings[] = 'Self-healed users table: removed personnel rows (end_user).';
        }

        foreach ([
            'external_id' => 'uq_users_external_id',
            'department' => 'idx_users_department',
            'status' => 'idx_users_status',
            'auth_provider' => 'idx_users_auth_provider',
            'provider_subject' => 'idx_users_provider_subject',
            'last_login_at' => null,
            'created_at' => null,
        ] as $column => $indexName) {
            if (!$this->columnExists($connection, 'users', $column)) {
                continue;
            }

            if ($indexName !== null && $this->indexExists($connection, 'users', $indexName)) {
                $connection->query(sprintf('ALTER TABLE users DROP INDEX %s', $indexName));
            }

            $connection->query(sprintf('ALTER TABLE users DROP COLUMN %s', $column));
            $warnings[] = sprintf('Self-healed users table: dropped legacy column %s.', $column);
        }

        return $warnings;
    }

    private function getPersonnelTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/009_separate_personnel_table.sql';
    }

    /**
     * @param object $connection Medoo instance
     */
    private function dropForeignKeyIfExists(object $connection, string $table, string $constraintName): void
    {
        if (!$this->foreignKeyExists($connection, $table, $constraintName)) {
            return;
        }

        $connection->query(
            sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY %s',
                $this->escapeIdentifier($table),
                $constraintName
            )
        );
    }

    /**
     * Migrate legacy plain-text default admin passwords to Bcrypt on startup.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchDefaultAdminPassword(object $connection): array
    {
        $warnings = [];
        $defaultAdminEmail = 'admin@betech.local';

        $row = $connection->get('users', ['password_hash'], [
            'email' => $defaultAdminEmail,
        ]);

        if ($row === null) {
            return $warnings;
        }

        $passwordHash = (string) ($row['password_hash'] ?? '');

        if (str_starts_with($passwordHash, '$2y$')) {
            return $warnings;
        }

        $updatePayload = [
            'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
        ];

        if ($this->columnExists($connection, 'users', 'auth_provider')) {
            $updatePayload['auth_provider'] = 'local';
        }

        $connection->update('users', $updatePayload, [
            'email' => $defaultAdminEmail,
        ]);

        $warnings[] = 'Self-healed default admin password: migrated to Bcrypt hash.';

        return $warnings;
    }

    /**
     * Self-heal location tracking tables and asset.location_id column.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchLocationTracking(object $connection): array
    {
        $warnings = [];

        if (!$this->tableExists($connection, 'locations')) {
            $connection->query(
                'CREATE TABLE IF NOT EXISTS locations (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL,
                    building VARCHAR(255) DEFAULT NULL,
                    description TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_locations_building (building),
                    KEY idx_locations_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $warnings[] = 'Self-healed database: created locations table.';
        }

        if (!$this->columnExists($connection, 'assets', 'location_id')) {
            $connection->query(
                'ALTER TABLE assets ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER user_id'
            );
            $warnings[] = 'Self-healed assets table: added location_id column.';
        }

        if ($this->columnExists($connection, 'assets', 'location_id')
            && !$this->indexExists($connection, 'assets', 'idx_assets_location_id')) {
            $connection->query('ALTER TABLE assets ADD KEY idx_assets_location_id (location_id)');
            $warnings[] = 'Self-healed assets table: added idx_assets_location_id index.';
        }

        if ($this->columnExists($connection, 'assets', 'location_id')
            && $this->tableExists($connection, 'locations')
            && !$this->foreignKeyExists($connection, 'assets', 'fk_assets_location_id')) {
            $connection->query(
                'ALTER TABLE assets ADD CONSTRAINT fk_assets_location_id FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
            $warnings[] = 'Self-healed assets table: added fk_assets_location_id foreign key.';
        }

        return $warnings;
    }

    /**
     * Self-heal software license management (SAM) tables.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchSoftwareLicenseManagement(object $connection): array
    {
        $warnings = [];

        if (!$this->tableExists($connection, 'licenses')) {
            $this->applySqlFile($connection, $this->getLicensesTableMigrationPath());
            $warnings[] = 'Self-healed database: created licenses and license_assignments tables.';
        } elseif (!$this->tableExists($connection, 'license_assignments')) {
            $this->applySqlFile($connection, $this->getLicensesTableMigrationPath());
            $warnings[] = 'Self-healed database: created license_assignments table.';
        }

        return $warnings;
    }

    private function getLicensesTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/008_create_licenses_tables.sql';
    }

    /**
     * @param object $connection Medoo instance
     */
    private function tableExists(object $connection, string $table): bool
    {
        $statement = $connection->query(
            sprintf("SHOW TABLES LIKE '%s'", $this->escapeIdentifier($table))
        );

        return $statement !== false && $statement->rowCount() > 0;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function foreignKeyExists(object $connection, string $table, string $constraintName): bool
    {
        $statement = $connection->query(
            sprintf(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s' AND CONSTRAINT_NAME = '%s' AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
                $this->escapeIdentifier($table),
                $constraintName
            )
        );

        return $statement !== false && $statement->rowCount() > 0;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function columnExists(object $connection, string $table, string $column): bool
    {
        $statement = $connection->query(
            sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $this->escapeIdentifier($table), $column)
        );

        return $statement !== false && $statement->rowCount() > 0;
    }

    /**
     * @param object $connection Medoo instance
     */
    private function indexExists(object $connection, string $table, string $indexName): bool
    {
        $statement = $connection->query(
            sprintf("SHOW INDEX FROM `%s` WHERE Key_name = '%s'", $this->escapeIdentifier($table), $indexName)
        );

        return $statement !== false && $statement->rowCount() > 0;
    }

    private function escapeIdentifier(string $identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    /**
     * @param object $connection Medoo instance
     */
    private function usersStatusColumnExists(object $connection): bool
    {
        $statement = $connection->query("SHOW COLUMNS FROM users LIKE 'status'");

        return $statement !== false && $statement->rowCount() > 0;
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
