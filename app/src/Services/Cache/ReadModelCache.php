<?php
declare(strict_types=1);

namespace App\Services\Cache;

use Cake\Cache\Cache;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Routing\Router;
use Closure;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use WeakMap;

/** Cache read models, never mutation authorization or uncommitted database state. */
final class ReadModelCache
{
    /**
     * @var \WeakMap<\Psr\Http\Message\ServerRequestInterface, array<string, string>>|null
     */
    private static ?WeakMap $revisions = null;

    /**
     * @var \WeakMap<\Cake\Database\Connection, bool>|null
     */
    private static ?WeakMap $watchedConnections = null;

    /**
     * The database revision changes atomically with every dependency write, including bulk SQL/CLI writes.
     * A GET uses one revision snapshot; mutations and transactions always evaluate fresh data.
     *
     * @template T
     * @param string $key Read-model name, including any member/resource IDs.
     * @param \Closure():T $load Fresh database read.
     * @param \Closure():int|null $validUntil Earliest time-based authorization change.
     * @return T
     */
    public static function remember(string $key, Closure $load, ?Closure $validUntil = null): mixed
    {
        $request = Router::getRequest();
        $connection = ConnectionManager::get('default');
        if (
            $request === null || !in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || $connection->inTransaction()
        ) {
            if ($request !== null && self::$revisions !== null) {
                unset(self::$revisions[$request]);
            }

            return $load();
        }
        $scope = TenantAwareCache::tenantScopedKey(hash('sha256', json_encode([
            $connection->config()['host'] ?? '', $connection->config()['port'] ?? '',
            $connection->config()['database'] ?? '', $connection->config()['schema'] ?? '',
            $connection->config()['username'] ?? '',
        ], JSON_THROW_ON_ERROR)));
        $revision = self::revision($request, $scope);
        $cacheKey = 'read_model_v1:' . $scope . ':' . $revision . ':' . $key;
        $cached = Cache::read($cacheKey);
        if (
            is_array($cached) && ($cached['until'] ?? 0) > DateTime::now()->getTimestamp()
            && array_key_exists('value', $cached)
        ) {
            return $cached['value'];
        }
        // Capture deadlines before loading: a query can finish after a grant's expiry.
        $until = DateTime::now()->getTimestamp() + 600;
        if ($validUntil !== null) {
            $until = min($until, $validUntil());
        }
        $value = $load();
        if ($until > DateTime::now()->getTimestamp()) {
            Cache::write($cacheKey, ['until' => $until, 'value' => $value]);
        }

        return $value;
    }

    /** Refresh after legacy table initialization performs autocommitted maintenance writes. */
    public static function forgetRequestRevision(): void
    {
        self::$revisions = null;
    }

    /** Read committed generation once per HTTP request and tenant/database scope. */
    private static function revision(ServerRequestInterface $request, string $scope): string
    {
        $connection = ConnectionManager::get('default');
        self::$watchedConnections ??= new WeakMap();
        if (!isset(self::$watchedConnections[$connection])) {
            $connection->getEventManager()->on('Connection.afterCommit', static function (): void {
                self::$revisions = null;
            });
            self::$watchedConnections[$connection] = true;
        }
        self::$revisions ??= new WeakMap();
        $revisions = self::$revisions[$request] ?? [];
        if (!isset($revisions[$scope])) {
            $row = ConnectionManager::get('default')->execute(
                "SELECT revision::text || ':' || COALESCE((SELECT md5(string_agg(transaction_id::text, ','"
                . " ORDER BY transaction_id)) FROM application_read_cache_changes), '') AS revision"
                . ' FROM application_read_cache_revision WHERE id = 1',
            )->fetch('assoc');
            if (!$row) {
                throw new RuntimeException('Read cache revision is unavailable.');
            }
            $revisions[$scope] = (string)$row['revision'];
            self::$revisions[$request] = $revisions;
        }

        return $revisions[$scope];
    }
}
