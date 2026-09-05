<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use RuntimeException;

/**
 * Reconciles existing application roles to database-local DML privileges.
 */
final class PostgresRuntimePrivileges
{
    /**
     * @param \Cake\Database\Connection $administrativeConnection Server/schema owner connection
     */
    public function __construct(private readonly Connection $administrativeConnection)
    {
    }

    /**
     * @param string $database Managed database
     * @param string $runtimeRole Non-owner runtime role
     * @param string|null $password Explicit default/platform password to create or rotate; null preserves tenant passwords
     * @param list<string> $otherRuntimeRoles Other managed roles whose stale grants must be removed
     * @return void
     */
    public function reconcile(
        string $database,
        string $runtimeRole,
        ?string $password = null,
        array $otherRuntimeRoles = [],
    ): void {
        AdministrativeDatabase::requireJob();
        $server = $this->administrativeConnection;
        if (!($server->getDriver() instanceof Postgres)) {
            throw new RuntimeException('Runtime privilege reconciliation requires PostgreSQL.');
        }
        $owner = (string)$server->execute('SELECT current_user')->fetchColumn(0);
        foreach ([$database, $runtimeRole, $owner, ...$otherRuntimeRoles] as $identifier) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/D', $identifier)) {
                throw new RuntimeException('Invalid database or role identifier for privilege reconciliation.');
            }
        }
        if ($owner === $runtimeRole || in_array($owner, $otherRuntimeRoles, true)) {
            throw new RuntimeException('An administrative database role cannot also be a runtime role.');
        }
        $driver = $server->getDriver();
        $role = $driver->quoteIdentifier($runtimeRole);
        $ownerSql = $driver->quoteIdentifier($owner);
        $databaseSql = $driver->quoteIdentifier($database);
        if ($password !== null && strlen($password) < 24) {
            throw new RuntimeException('Runtime database passwords must contain at least 24 bytes.');
        }
        $exists = (bool)$server->execute('SELECT 1 FROM pg_roles WHERE rolname = ?', [$runtimeRole])->fetchColumn(0);
        if (!$exists) {
            if ($password === null || strlen($password) < 24) {
                throw new RuntimeException(
                    'New runtime roles require a separately generated password of at least 24 bytes.',
                );
            }
            $server->execute(sprintf('CREATE ROLE %s LOGIN PASSWORD %s', $role, $driver->quote($password)));
        }
        $unsafeRole = (bool)$server->execute(
            'SELECT 1 FROM pg_roles WHERE rolname = ? AND (rolsuper OR rolreplication OR rolbypassrls)',
            [$runtimeRole],
        )->fetchColumn(0);
        if ($unsafeRole) {
            throw new RuntimeException(
                'Replace privileged database roles with distinct runtime roles before reconciliation.',
            );
        }
        $server->execute(sprintf(
            'ALTER ROLE %s LOGIN NOCREATEDB NOCREATEROLE NOINHERIT',
            $role,
        ));
        if ($exists && $password !== null) {
            $server->execute(sprintf('ALTER ROLE %s PASSWORD %s', $role, $driver->quote($password)));
        }
        // NOINHERIT alone still permits SET ROLE; remove inherited membership too.
        $memberships = $server->execute(
            'SELECT parent.rolname FROM pg_auth_members m JOIN pg_roles parent ON parent.oid = m.roleid '
            . 'JOIN pg_roles child ON child.oid = m.member WHERE child.rolname = ?',
            [$runtimeRole],
        )->fetchAll('assoc');
        foreach ($memberships as $membership) {
            $server->execute(sprintf('REVOKE %s FROM %s', $driver->quoteIdentifier($membership['rolname']), $role));
        }
        $config = $server->config();
        unset($config['url']);
        $config['className'] = Connection::class;
        $config['database'] = $database;
        $name = 'runtime_privileges_admin';
        ConnectionManager::drop($name);
        ConnectionManager::setConfig($name, $config);
        try {
            /** @var \Cake\Database\Connection $connection */
            $connection = ConnectionManager::get($name);
            // Azure's server administrator is not a PostgreSQL superuser. Temporary
            // membership allows transfer of existing tenant-owned objects.
            $wasMember = (bool)$server->execute('SELECT pg_has_role(?, ?, ?)', [$owner, $runtimeRole, 'MEMBER'])
                ->fetchColumn(0);
            $couldSetRole = (bool)$server->execute('SELECT pg_has_role(?, ?, ?)', [$owner, $runtimeRole, 'SET'])
                ->fetchColumn(0);
            if (!$couldSetRole) {
                $server->execute(sprintf('GRANT %s TO %s WITH SET TRUE', $role, $ownerSql));
            }
            try {
                $connection->execute(sprintf('REASSIGN OWNED BY %s TO %s', $role, $ownerSql));
            } finally {
                if (!$wasMember) {
                    $server->execute(sprintf('REVOKE %s FROM %s', $role, $ownerSql));
                } elseif (!$couldSetRole) {
                    $server->execute(sprintf('GRANT %s TO %s WITH SET FALSE', $role, $ownerSql));
                }
            }
            $connection->execute(sprintf('ALTER DATABASE %s OWNER TO %s', $databaseSql, $ownerSql));
            $connection->execute(sprintf('ALTER SCHEMA public OWNER TO %s', $ownerSql));
            foreach (array_unique(['PUBLIC', $runtimeRole, ...$otherRuntimeRoles]) as $grantee) {
                $granteeSql = $grantee === 'PUBLIC' ? 'PUBLIC' : $driver->quoteIdentifier($grantee);
                $connection->execute(sprintf('REVOKE ALL ON DATABASE %s FROM %s', $databaseSql, $granteeSql));
                $connection->execute(sprintf('REVOKE ALL ON SCHEMA public FROM %s', $granteeSql));
                $connection->execute(sprintf('REVOKE ALL ON ALL TABLES IN SCHEMA public FROM %s', $granteeSql));
                $connection->execute(sprintf('REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM %s', $granteeSql));
                $connection->execute(sprintf('REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM %s', $granteeSql));
            }
            $connection->execute(sprintf('GRANT CONNECT ON DATABASE %s TO %s', $databaseSql, $role));
            $connection->execute(sprintf('GRANT USAGE ON SCHEMA public TO %s', $role));
            $connection->execute(sprintf(
                'GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO %s',
                $role,
            ));
            $connection->execute(sprintf('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO %s', $role));
            $connection->execute(sprintf('GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO %s', $role));
            $prefix = sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s IN SCHEMA public ', $ownerSql);
            foreach (array_unique(['PUBLIC', $runtimeRole, ...$otherRuntimeRoles]) as $grantee) {
                $granteeSql = $grantee === 'PUBLIC' ? 'PUBLIC' : $driver->quoteIdentifier($grantee);
                foreach (['TABLES', 'SEQUENCES', 'FUNCTIONS'] as $objectType) {
                    $revoke = sprintf('REVOKE ALL ON %s FROM %s', $objectType, $granteeSql);
                    $connection->execute(sprintf('ALTER DEFAULT PRIVILEGES FOR ROLE %s ', $ownerSql) . $revoke);
                    $connection->execute($prefix . $revoke);
                }
            }
            $connection->execute($prefix . sprintf('GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %s', $role));
            $connection->execute($prefix . sprintf('GRANT USAGE, SELECT ON SEQUENCES TO %s', $role));
            $connection->execute($prefix . sprintf('GRANT EXECUTE ON FUNCTIONS TO %s', $role));
        } finally {
            ConnectionManager::drop($name);
        }
    }
}
