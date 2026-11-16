<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\TenantContext;
use App\Services\Cache\TenantAwareCache;
use Cake\Cache\Cache;
use Cake\Database\Connection;
use Cake\Log\Log;
use Cake\Routing\Router;
use Cake\Utility\Text;
use Closure;
use Throwable;

/** Record on the domain connection; publishing failures cannot undo a committed save. */
final class UiUpdateOutbox
{
    /**
     * @var \Closure(array<string, mixed>): void
     */
    private Closure $publish;

    /** Inject delivery so retry and failure behavior can be verified without external services. */
    public function __construct(?Closure $publish = null)
    {
        $this->publish = $publish ?? (new UiUpdatePublisher())->publish(...);
    }

    /** @param array<string, list<string>> $topics Authorized reads resolve actual content later. */
    public function record(Connection $connection, array $topics): void
    {
        if (
            !TurboConfig::liveEnabled() || $topics === []
            || filter_var(env('KMP_ADMIN_JOB', false), FILTER_VALIDATE_BOOL)
        ) {
            return;
        }
        $id = Text::uuid();
        $tenant = TenantContext::current();
        $sourceRequest = Router::getRequest()?->getHeaderLine('X-KMP-Mutation-ID') ?? '';
        $connection->insert('ui_update_outbox', [
            'id' => $id,
            'tenant_id' => $tenant->id,
            'topics' => json_encode($topics, JSON_THROW_ON_ERROR),
            'source_request' => preg_match('/^[a-f0-9-]{36}$/i', $sourceRequest) === 1 ? $sourceRequest : null,
        ]);
        $connection->afterCommit(function () use ($connection, $id, $tenant): void {
            try {
                TenantContext::with($tenant, fn(): int => $this->dispatch($connection, $id));
            } catch (Throwable $exception) {
                Log::error('Committed UI update awaits retry: ' . $exception::class, ['event_id' => $id]);
            }
        });
    }

    /** Retry committed rows only; duplicates are identified by event UUID. */
    public function dispatch(Connection $connection, ?string $eventId = null): int
    {
        $rows = $connection->execute(
            'SELECT id, tenant_id, topics, source_request FROM ui_update_outbox'
            . ' WHERE tenant_id = :tenantId AND delivered IS NULL'
            . ' AND available_after <= CURRENT_TIMESTAMP' . ($eventId === null ? '' : ' AND id = :id')
            . ' ORDER BY created LIMIT 100',
            ['tenantId' => TenantContext::id()] + ($eventId === null ? [] : ['id' => $eventId]),
        )->fetchAll('assoc');
        $delivered = 0;
        foreach ($rows as $row) {
            try {
                if (isset(json_decode($row['topics'], true, flags: JSON_THROW_ON_ERROR)['Security:list'])) {
                    // Revoke cached grants after commit, before clients can reconcile the signal.
                    Cache::clearGroup('security');
                    Cache::write(TenantAwareCache::tenantScopedKey('security_generation'), Text::uuid());
                }
                ($this->publish)($row);
                $connection->execute(
                    'UPDATE ui_update_outbox SET delivered = CURRENT_TIMESTAMP WHERE id = :id',
                    ['id' => $row['id']],
                );
                $delivered++;
            } catch (Throwable $exception) {
                $connection->execute(
                    'UPDATE ui_update_outbox SET attempts = attempts + 1,'
                    . " available_after = CURRENT_TIMESTAMP + INTERVAL '10 seconds' WHERE id = :id",
                    ['id' => $row['id']],
                );
                Log::warning('UI update delivery pending: ' . $exception::class, ['event_id' => $row['id']]);
            }
        }
        $connection->execute(
            'DELETE FROM ui_update_outbox WHERE id IN (SELECT id FROM ui_update_outbox'
            . " WHERE tenant_id = :tenantId AND delivered < CURRENT_TIMESTAMP - INTERVAL '1 day' LIMIT 100)",
            ['tenantId' => TenantContext::id()],
        );

        return $delivered;
    }
}
