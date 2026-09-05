<?php
declare(strict_types=1);

namespace App\Services;

use App\Log\LogPrivacy;
use App\Model\Entity\Member;
use App\Services\Cache\TenantAwareCache;
use App\Services\Security\MemberSessionState;
use Cake\Cache\Cache;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Session;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Throwable;

/**
 * Manages impersonation session metadata for super user actions.
 *
 * Stores original administrator details alongside the impersonated
 * identity so we can revert safely and display contextual warnings.
 * All state is persisted in the session under a single namespace.
 */
class ImpersonationService
{
    /** @var string Session namespace for impersonation state */
    public const SESSION_KEY = 'Impersonation';

    /**
     * Persist impersonation metadata for the active session.
     *
     * @param \Cake\Http\Session $session Active request session
     * @param \App\Model\Entity\Member $impersonator Super user initiating impersonation
     * @param \App\Model\Entity\Member $target Member being impersonated
     * @return array<string, mixed> Immutable snapshot of impersonation state
     */
    public function start(Session $session, Member $impersonator, Member $target): array
    {
        $state = [
            'active' => true,
            'tenant_id' => MemberSessionState::tenantId(),
            'auth_version' => (string)$impersonator->auth_version,
            'impersonator_id' => (int)$impersonator->id,
            'impersonator_name' => (string)$impersonator->sca_name,
            'impersonated_member_id' => (int)$target->id,
            'impersonated_member_name' => (string)$target->sca_name,
            'started_at' => DateTime::now()->toIso8601String(),
        ];

        $session->write(self::SESSION_KEY, $state);
        $this->logSessionEvent('start', $impersonator, $target);
        $this->clearIdentityCaches($session, $impersonator->id, $target->id);

        return $state;
    }

    /**
     * Clear impersonation metadata from the session and return the snapshot.
     *
     * @param \Cake\Http\Session $session Active request session
     * @return array<string, mixed>|null Snapshot of impersonation state or null when inactive
     */
    public function stop(Session $session): ?array
    {
        $state = $session->read(self::SESSION_KEY);
        $session->delete(self::SESSION_KEY);

        if (!is_array($state)) {
            return null;
        }

        $impersonatorId = (int)($state['impersonator_id'] ?? 0);
        $impersonatedId = (int)($state['impersonated_member_id'] ?? 0);

        try {
            $impersonator = TableRegistry::getTableLocator()->get('Members')
                ->get($impersonatorId);
            $impersonated = TableRegistry::getTableLocator()->get('Members')
                ->get($impersonatedId);
            $this->logSessionEvent('stop', $impersonator, $impersonated);
        } catch (Throwable $exception) {
            Log::warning('Failed to record impersonation session stop: ' . $exception->getMessage());
        }

        $this->clearIdentityCaches($session, $impersonatorId, $impersonatedId);

        return $state;
    }

    /**
     * Retrieve the current impersonation state.
     *
     * @param \Cake\Http\Session $session Active request session
     * @return array<string, mixed>|null
     */
    public function getState(Session $session): ?array
    {
        $state = $session->read(self::SESSION_KEY);

        if (!is_array($state) || empty($state['active'])) {
            return null;
        }

        return $state;
    }

    /** Validate the original administrator as well as the effective identity. */
    public function isValid(Session $session): bool
    {
        $state = $this->getState($session);
        if ($state === null) {
            return true;
        }
        if (
            MemberSessionState::tenantId() === null
            || ($state['tenant_id'] ?? null) !== MemberSessionState::tenantId()
            || !is_string($state['auth_version'] ?? null)
        ) {
            return false;
        }
        try {
            $admin = TableRegistry::getTableLocator()->get('Members')->get((int)$state['impersonator_id']);
        } catch (RecordNotFoundException) {
            return false;
        }

        return (int)($state['impersonated_member_id'] ?? 0) === (int)$session->read('Auth.member_id')
            && MemberSessionState::eligible($admin)
            && hash_equals((string)$admin->auth_version, $state['auth_version'])
            && $admin->isSuperUser();
    }

    /**
     * Determine whether impersonation is active for the given session.
     *
     * @param \Cake\Http\Session $session Active request session
     * @return bool
     */
    public function isActive(Session $session): bool
    {
        return $this->getState($session) !== null;
    }

    /**
     * Persist start/stop events for impersonation sessions.
     */
    protected function logSessionEvent(string $event, Member $impersonator, Member $target): void
    {
        $request = Router::getRequest();
        $tableLocator = TableRegistry::getTableLocator();

        try {
            $logsTable = $tableLocator->get('ImpersonationSessionLogs');
        } catch (Throwable $exception) {
            Log::warning('Unable to access ImpersonationSessionLogs table: ' . $exception->getMessage());

            return;
        }

        $data = [
            'impersonator_id' => (int)$impersonator->id,
            'impersonated_member_id' => (int)$target->id,
            'event' => $event,
            'request_url' => $request ? LogPrivacy::path($request->getRequestTarget()) : null,
            'ip_address' => $request?->clientIp(),
            'user_agent' => $request?->getHeaderLine('User-Agent'),
            'created' => DateTime::now(),
        ];

        $log = $logsTable->newEntity($data, ['accessibleFields' => ['*' => true]]);
        if ($log->hasErrors()) {
            Log::warning('Failed to create impersonation session log entry: ' . json_encode($log->getErrors()));

            return;
        }

        $logsTable->save($log, ['checkRules' => false, 'atomic' => false]);
    }

    /**
     * Reset caches that depend on the current identity to prevent leakage between users.
     */
    protected function clearIdentityCaches(Session $session, int ...$memberIds): void
    {
        try {
            Cache::delete(TenantAwareCache::tenantScopedKey('navigation_items'));
        } catch (Throwable $exception) {
            Log::warning('Failed clearing navigation cache: ' . $exception->getMessage());
        }

        $session->delete(TenantAwareCache::tenantScopedKey('navigation_items'));
        $session->delete('navigation_items');
        $session->delete('pageStack');
        $session->delete('viewMode');

        foreach ($memberIds as $memberId) {
            Cache::delete(TenantAwareCache::tenantScopedKey('member_permissions' . $memberId), 'member_permissions');
            Cache::delete(TenantAwareCache::tenantScopedKey('permissions_policies' . $memberId), 'member_permissions');
        }
    }
}
