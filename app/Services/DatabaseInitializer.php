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

            if ($this->usersTableExists($connection)) {
                foreach ($this->patchRebuildUsersTable($connection) as $warning) {
                    $warnings[] = $warning;
                }

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

                foreach ($this->patchConsumables($connection) as $warning) {
                    $warnings[] = $warning;
                }

                foreach ($this->patchTickets($connection) as $warning) {
                    $warnings[] = $warning;
                }

                foreach ($this->patchAuditLogs($connection) as $warning) {
                    $warnings[] = $warning;
                }

                foreach ($this->patchIpam($connection) as $warning) {
                    $warnings[] = $warning;
                }
            }

            foreach ($this->patchPersonnelSeparation($connection) as $warning) {
                $warnings[] = $warning;
            }

            if ($this->tableExists($connection, 'personnel')) {
                foreach ($this->patchPersonnelTableColumns($connection) as $warning) {
                    $warnings[] = $warning;
                }

                foreach ($this->patchPersonnelAccessRoles($connection) as $warning) {
                    $warnings[] = $warning;
                }
            }

            if (is_readable($this->seedsPath)) {
                $this->applySqlFile($connection, $this->seedsPath);
            } else {
                $warnings[] = 'Seed file is missing. Default categories were not loaded.';
            }

            if ($this->usersTableExists($connection)) {
                foreach ($this->patchRemoveUsersPasswordHash($connection) as $warning) {
                    $warnings[] = $warning;
                }
            }

            foreach ($this->patchLoginAttemptsTable($connection) as $warning) {
                $warnings[] = $warning;
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

    private function getRebuildUsersTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/011_rebuild_users_table.sql';
    }

    /**
     * Rebuild users table to the canonical auth-only schema when legacy layout is detected.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchRebuildUsersTable(object $connection): array
    {
        if (!$this->usersTableNeedsRebuild($connection)) {
            return [];
        }

        $migrationPath = $this->getRebuildUsersTableMigrationPath();

        if (!is_readable($migrationPath)) {
            return ['Users table rebuild migration is missing or unreadable.'];
        }

        if ($this->tableExists($connection, 'asset_histories')) {
            $this->dropForeignKeyIfExists($connection, 'asset_histories', 'fk_asset_histories_user_id');
        }

        $this->applySqlFile($connection, $migrationPath);

        return ['Applied migration: rebuilt users table with fresh system-user schema.'];
    }

    /**
     * @param object $connection Medoo instance
     */
    private function usersTableNeedsRebuild(object $connection): bool
    {
        if (!$this->columnExists($connection, 'users', 'created_at')) {
            return true;
        }

        $idType = $this->columnBaseType($connection, 'users', 'id');

        return $idType === null || !str_starts_with($idType, 'bigint');
    }

    /**
     * @param object $connection Medoo instance
     */
    private function columnBaseType(object $connection, string $table, string $column): ?string
    {
        $statement = $connection->query(
            sprintf("SHOW COLUMNS FROM `%s` LIKE '%s'", $this->escapeIdentifier($table), $column)
        );

        if ($statement === false) {
            return null;
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row) || !isset($row['Type'])) {
            return null;
        }

        return strtolower((string) $row['Type']);
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
     * Self-heal auth-only users columns: id, name, email, password_hash, role.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchUsersTableColumns(object $connection): array
    {
        $warnings = [];

        if (!$this->columnExists($connection, 'users', 'role')) {
            $afterColumn = $this->resolveUsersAlterAfterColumn($connection, 'email');
            $defaultRole = $this->usersTableHasLegacyPersonnelColumns($connection) ? 'end_user' : 'super_admin';
            $connection->query(
                sprintf(
                    "ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT '%s' AFTER %s",
                    $defaultRole,
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

        if ($this->columnExists($connection, 'users', 'role')) {
            $connection->query(
                "UPDATE users SET role = 'super_admin' WHERE email = 'admin@betech.local' AND role = 'end_user'"
            );
        }

        return $warnings;
    }

    /**
     * Self-heal personnel directory columns on the personnel table.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchPersonnelTableColumns(object $connection): array
    {
        $warnings = [];

        $columnDefinitions = [
            'department' => 'VARCHAR(120) DEFAULT NULL',
            'title' => 'VARCHAR(255) DEFAULT NULL',
            'external_id' => 'VARCHAR(128) DEFAULT NULL',
            'provider' => "VARCHAR(32) NOT NULL DEFAULT 'local'",
            'status' => "VARCHAR(32) NOT NULL DEFAULT 'active'",
            'role' => "VARCHAR(32) NOT NULL DEFAULT 'user'",
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        ];

        foreach ($columnDefinitions as $column => $definition) {
            if ($this->columnExists($connection, 'personnel', $column)) {
                continue;
            }

            $afterColumn = $this->resolvePersonnelAlterAfterColumn($connection, $column);
            $connection->query(
                sprintf(
                    'ALTER TABLE personnel ADD COLUMN %s %s AFTER %s',
                    $column,
                    $definition,
                    $afterColumn
                )
            );
            $warnings[] = sprintf('Self-healed personnel table: added %s column.', $column);
        }

        $indexes = [
            'idx_personnel_department' => 'department',
            'idx_personnel_external_id' => 'external_id',
            'idx_personnel_provider' => 'provider',
            'idx_personnel_status' => 'status',
            'idx_personnel_role' => 'role',
        ];

        foreach ($indexes as $indexName => $column) {
            if (!$this->columnExists($connection, 'personnel', $column)) {
                continue;
            }

            if ($this->indexExists($connection, 'personnel', $indexName)) {
                continue;
            }

            $connection->query(
                sprintf('ALTER TABLE personnel ADD KEY %s (%s)', $indexName, $column)
            );
            $warnings[] = sprintf('Self-healed personnel table: added %s index.', $indexName);
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
            && $this->tableExists($connection, 'personnel')) {
            $needsPersonnelMigration = $this->usersTableHasLegacyPersonnelColumns($connection);

            if (!$needsPersonnelMigration && $this->columnExists($connection, 'users', 'role')) {
                $needsPersonnelMigration = (int) $connection->count('users', ['role' => 'end_user']) > 0;
            }

            if ($needsPersonnelMigration) {
                $this->migrateLegacyPersonnelFromUsers($connection);
                $warnings[] = 'Self-healed database: migrated personnel records from users table.';
            }
        }

        if ($this->assetsTableExists($connection)
            && $this->columnExists($connection, 'assets', 'user_id')
            && !$this->columnExists($connection, 'assets', 'personnel_id')) {
            $this->dropForeignKeyIfExists($connection, 'assets', 'fk_assets_user_id');
            $this->safeQuery(
                $connection,
                'ALTER TABLE assets CHANGE user_id personnel_id BIGINT UNSIGNED DEFAULT NULL'
            );
            $warnings[] = 'Self-healed assets table: renamed user_id to personnel_id.';
        }

        if ($this->assetsTableExists($connection)
            && $this->columnExists($connection, 'assets', 'personnel_id')
            && !$this->indexExists($connection, 'assets', 'idx_assets_personnel_id')) {
            if ($this->indexExists($connection, 'assets', 'idx_assets_user_id')) {
                $this->safeQuery($connection, 'ALTER TABLE assets DROP INDEX idx_assets_user_id');
            }

            $this->safeQuery($connection, 'ALTER TABLE assets ADD KEY idx_assets_personnel_id (personnel_id)');
            $warnings[] = 'Self-healed assets table: added idx_assets_personnel_id index.';
        }

        if ($this->assetsTableExists($connection)
            && $this->tableExists($connection, 'personnel')
            && $this->columnExists($connection, 'assets', 'personnel_id')
            && !$this->foreignKeyExists($connection, 'assets', 'fk_assets_personnel_id')) {
            $this->safeQuery(
                $connection,
                'ALTER TABLE assets ADD CONSTRAINT fk_assets_personnel_id FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
            $warnings[] = 'Self-healed assets table: added fk_assets_personnel_id foreign key.';
        }

        if ($this->tableExists($connection, 'license_assignments')
            && $this->columnExists($connection, 'license_assignments', 'user_id')
            && !$this->columnExists($connection, 'license_assignments', 'personnel_id')) {
            $this->dropForeignKeyIfExists($connection, 'license_assignments', 'fk_license_assignments_user_id');
            $this->safeQuery(
                $connection,
                'ALTER TABLE license_assignments CHANGE user_id personnel_id BIGINT UNSIGNED DEFAULT NULL'
            );
            $warnings[] = 'Self-healed license_assignments table: renamed user_id to personnel_id.';
        }

        if ($this->tableExists($connection, 'license_assignments')
            && $this->columnExists($connection, 'license_assignments', 'personnel_id')
            && !$this->indexExists($connection, 'license_assignments', 'idx_license_assignments_personnel_id')) {
            if ($this->indexExists($connection, 'license_assignments', 'idx_license_assignments_user_id')) {
                $this->safeQuery($connection, 'ALTER TABLE license_assignments DROP INDEX idx_license_assignments_user_id');
            }

            $this->safeQuery($connection, 'ALTER TABLE license_assignments ADD KEY idx_license_assignments_personnel_id (personnel_id)');
            $warnings[] = 'Self-healed license_assignments table: added idx_license_assignments_personnel_id index.';
        }

        if ($this->tableExists($connection, 'license_assignments')
            && $this->tableExists($connection, 'personnel')
            && $this->columnExists($connection, 'license_assignments', 'personnel_id')
            && !$this->foreignKeyExists($connection, 'license_assignments', 'fk_license_assignments_personnel_id')) {
            $this->safeQuery(
                $connection,
                'ALTER TABLE license_assignments ADD CONSTRAINT fk_license_assignments_personnel_id FOREIGN KEY (personnel_id) REFERENCES personnel (id) ON DELETE CASCADE ON UPDATE CASCADE'
            );
            $warnings[] = 'Self-healed license_assignments table: added fk_license_assignments_personnel_id foreign key.';
        }

        if ($this->tableExists($connection, 'asset_histories')
            && $this->columnExists($connection, 'asset_histories', 'target_user_id')
            && !$this->columnExists($connection, 'asset_histories', 'target_personnel_id')) {
            $this->dropForeignKeyIfExists($connection, 'asset_histories', 'fk_asset_histories_target_user_id');
            $this->safeQuery(
                $connection,
                'ALTER TABLE asset_histories CHANGE target_user_id target_personnel_id BIGINT UNSIGNED DEFAULT NULL'
            );
            $warnings[] = 'Self-healed asset_histories table: renamed target_user_id to target_personnel_id.';
        }

        if ($this->tableExists($connection, 'asset_histories')
            && $this->tableExists($connection, 'personnel')
            && $this->columnExists($connection, 'asset_histories', 'target_personnel_id')
            && !$this->foreignKeyExists($connection, 'asset_histories', 'fk_asset_histories_target_personnel_id')) {
            $this->safeQuery(
                $connection,
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
            'title' => null,
            'status' => 'idx_users_status',
            'auth_provider' => 'idx_users_auth_provider',
            'provider_subject' => 'idx_users_provider_subject',
            'provider' => null,
            'last_login_at' => null,
        ] as $column => $indexName) {
            if (!$this->columnExists($connection, 'users', $column)) {
                continue;
            }

            if ($indexName !== null && $this->indexExists($connection, 'users', $indexName)) {
                $this->safeQuery($connection, sprintf('ALTER TABLE users DROP INDEX %s', $indexName));
            }

            if ($this->safeQuery($connection, sprintf('ALTER TABLE users DROP COLUMN %s', $column))) {
                $warnings[] = sprintf('Self-healed users table: dropped legacy column %s.', $column);
            }
        }

        return $warnings;
    }

    private function getPersonnelTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/009_separate_personnel_table.sql';
    }

    /**
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchLoginAttemptsTable(object $connection): array
    {
        if ($this->tableExists($connection, 'login_attempts')) {
            return [];
        }

        $this->applySqlFile($connection, $this->getLoginAttemptsTableMigrationPath());

        return ['Self-healed database: created login_attempts table.'];
    }

    private function getLoginAttemptsTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/010_create_login_attempts_table.sql';
    }

    /**
     * @param object $connection Medoo instance
     */
    private function dropForeignKeyIfExists(object $connection, string $table, string $constraintName): void
    {
        if (!$this->foreignKeyExists($connection, $table, $constraintName)) {
            return;
        }

        $this->safeQuery(
            $connection,
            sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY %s',
                $this->escapeIdentifier($table),
                $constraintName
            )
        );
    }

    /**
     * Run a schema mutation query without aborting boot when the target is already gone.
     *
     * @param object $connection Medoo instance
     */
    private function safeQuery(object $connection, string $sql): bool
    {
        try {
            $connection->query($sql);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Statements that should not crash initialization if already applied or missing.
     */
    private function isFailSafeSchemaStatement(string $statement): bool
    {
        $normalized = strtolower(trim($statement));

        return str_contains($normalized, 'drop foreign key')
            || str_contains($normalized, 'drop column')
            || str_contains($normalized, 'drop index')
            || str_contains($normalized, ' drop key ')
            || str_contains($normalized, 'add constraint')
            || preg_match('/\bdrop\s+table\s+if\s+exists\b/', $normalized) === 1;
    }

    /**
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchRemoveUsersPasswordHash(object $connection): array
    {
        if (!$this->columnExists($connection, 'users', 'password_hash')) {
            return [];
        }

        $this->safeQuery($connection, 'ALTER TABLE users DROP COLUMN password_hash');

        return ['Self-healed users table: dropped password_hash column.'];
    }

    /**
     * Promote legacy operator accounts into personnel admin roles when possible.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchPersonnelAccessRoles(object $connection): array
    {
        if (!$this->columnExists($connection, 'personnel', 'role')
            || !$this->usersTableExists($connection)
            || !$this->columnExists($connection, 'users', 'role')) {
            return [];
        }

        $connection->query(
            "UPDATE personnel p
            INNER JOIN users u ON LOWER(p.email) = LOWER(u.email)
            SET p.role = 'admin'
            WHERE u.role IN ('super_admin', 'technician')
              AND p.role <> 'admin'"
        );

        return ['Self-healed personnel table: migrated operator roles from users table.'];
    }

    /**
     * Ensure the default local super-admin exists after migrations or table splits.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function ensureDefaultAdminExists(object $connection): array
    {
        $defaultAdminEmail = 'admin@betech.local';

        if ($connection->has('users', ['email' => $defaultAdminEmail])) {
            return [];
        }

        $insertPayload = [
            'name' => 'Sistem Yöneticisi',
            'email' => $defaultAdminEmail,
            'role' => 'super_admin',
        ];

        if (!$this->columnExists($connection, 'users', 'role')) {
            unset($insertPayload['role']);
        }

        $connection->insert('users', $insertPayload);

        return ['Self-healed default admin: created admin@betech.local account.'];
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
            'password_hash' => password_hash('Betech2026!', PASSWORD_DEFAULT),
        ];

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
            $afterColumn = $this->columnExists($connection, 'assets', 'personnel_id')
                ? 'personnel_id'
                : ($this->columnExists($connection, 'assets', 'user_id') ? 'user_id' : 'status');
            $connection->query(
                sprintf(
                    'ALTER TABLE assets ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER %s',
                    $afterColumn
                )
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
     * Self-heal consumables inventory table.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchConsumables(object $connection): array
    {
        $warnings = [];

        if (!$this->tableExists($connection, 'locations')) {
            return $warnings;
        }

        if (!$this->tableExists($connection, 'consumables')) {
            $this->applySqlFile($connection, $this->getConsumablesTableMigrationPath());
            $warnings[] = 'Self-healed database: created consumables table.';
        }

        return $warnings;
    }

    private function getConsumablesTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/012_create_consumables_table.sql';
    }

    /**
     * Self-heal help desk ticketing tables.
     *
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchTickets(object $connection): array
    {
        $warnings = [];

        if (!$this->tableExists($connection, 'personnel')) {
            return $warnings;
        }

        if (!$this->tableExists($connection, 'tickets')) {
            $this->applySqlFile($connection, $this->getTicketsTableMigrationPath());
            $warnings[] = 'Self-healed database: created tickets and ticket_comments tables.';
        }

        return $warnings;
    }

    /**
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchAuditLogs(object $connection): array
    {
        $warnings = [];

        if (!$this->tableExists($connection, 'audit_logs')) {
            $this->applySqlFile($connection, $this->getAuditLogsTableMigrationPath());
            $warnings[] = 'Self-healed database: created audit_logs table.';
        }

        return $warnings;
    }

    /**
     * @param object $connection Medoo instance
     *
     * @return list<string>
     */
    private function patchIpam(object $connection): array
    {
        $warnings = [];

        if (!$this->tableExists($connection, 'ip_networks')) {
            $this->applySqlFile($connection, $this->getIpamMigrationPath());
            $warnings[] = 'Self-healed database: created IPAM tables.';
        }

        return $warnings;
    }

    private function getIpamMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/016_create_ipam_tables.sql';
    }

    private function getAuditLogsTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/015_create_audit_logs_table.sql';
    }

    private function getTicketsTableMigrationPath(): string
    {
        return dirname($this->schemaPath) . '/migrations/014_create_tickets_tables.sql';
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
    private function usersTableHasLegacyPersonnelColumns(object $connection): bool
    {
        foreach (['external_id', 'department', 'title', 'provider', 'auth_provider', 'provider_subject', 'status'] as $column) {
            if ($this->columnExists($connection, 'users', $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Copy remaining end_user rows from the legacy unified users table into personnel.
     * Column references are resolved dynamically so partially migrated databases do not fail.
     *
     * @param object $connection Medoo instance
     */
    private function migrateLegacyPersonnelFromUsers(object $connection): void
    {
        $departmentSelect = $this->columnExists($connection, 'users', 'department')
            ? 'u.department'
            : 'NULL';
        $titleSelect = $this->columnExists($connection, 'users', 'title')
            ? 'u.title'
            : 'NULL';
        $externalIdSelect = $this->columnExists($connection, 'users', 'external_id')
            ? 'u.external_id'
            : 'NULL';
        $providerSelect = $this->columnExists($connection, 'users', 'provider')
            ? "COALESCE(u.provider, 'local')"
            : ($this->columnExists($connection, 'users', 'auth_provider')
                ? "COALESCE(u.auth_provider, 'local')"
                : "'local'");
        $statusSelect = $this->columnExists($connection, 'users', 'status')
            ? "COALESCE(u.status, 'active')"
            : "'active'";
        $createdAtSelect = $this->columnExists($connection, 'users', 'created_at')
            ? 'u.created_at'
            : 'CURRENT_TIMESTAMP';
        $whereClause = $this->columnExists($connection, 'users', 'role')
            ? "u.role = 'end_user'"
            : '1=1';

        $connection->query(sprintf(
            'INSERT INTO personnel (id, name, email, department, title, external_id, provider, status, created_at)
            SELECT u.id, u.name, u.email, %1$s, %2$s, %3$s, %4$s, %5$s, %6$s
            FROM users u
            WHERE %7$s
            ON DUPLICATE KEY UPDATE
                personnel.name = VALUES(name),
                personnel.email = VALUES(email),
                personnel.department = VALUES(department),
                personnel.title = VALUES(title),
                personnel.external_id = COALESCE(personnel.external_id, VALUES(external_id)),
                personnel.provider = VALUES(provider),
                personnel.status = VALUES(status)',
            $departmentSelect,
            $titleSelect,
            $externalIdSelect,
            $providerSelect,
            $statusSelect,
            $createdAtSelect,
            $whereClause
        ));
    }

    /**
     * @param object $connection Medoo instance
     * @param list<string> $preferredColumns
     */
    private function resolveUsersAlterAfterColumn(object $connection, string ...$preferredColumns): string
    {
        foreach ($preferredColumns as $column) {
            if ($this->columnExists($connection, 'users', $column)) {
                return $column;
            }
        }

        return 'email';
    }

    /**
     * @param object $connection Medoo instance
     */
    private function resolvePersonnelAlterAfterColumn(object $connection, string $targetColumn): string
    {
        $chain = [
            'department' => ['email', 'name'],
            'title' => ['department', 'email', 'name'],
            'external_id' => ['title', 'department', 'email', 'name'],
            'provider' => ['external_id', 'title', 'department', 'email', 'name'],
            'status' => ['provider', 'external_id', 'title', 'department', 'email', 'name'],
            'role' => ['status', 'provider', 'external_id', 'title', 'department', 'email', 'name'],
            'created_at' => ['role', 'status', 'provider', 'external_id', 'title', 'department', 'email', 'name'],
        ];

        foreach ($chain[$targetColumn] ?? ['email'] as $column) {
            if ($this->columnExists($connection, 'personnel', $column)) {
                return $column;
            }
        }

        return 'email';
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
            if ($this->isFailSafeSchemaStatement($statement)) {
                $this->safeQuery($connection, $statement);
                continue;
            }

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
