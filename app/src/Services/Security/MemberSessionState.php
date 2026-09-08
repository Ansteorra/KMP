<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\KMP\TenantContext;
use App\Model\Entity\Member;
use Cake\Core\Configure;
use RuntimeException;
use function Cake\Core\env;

/** Minimal tenant-bound session credentials; authorization is always loaded afresh. */
final class MemberSessionState
{
    /** Return the immutable tenant identifier; missing tenant context fails closed. */
    public static function tenantId(): ?string
    {
        $enabled = Configure::read(
            'KMP.tenancy.enabled',
            filter_var((string)env('KMP_TENANCY_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        );

        return TenantContext::tryCurrent()?->id ?? ($enabled ? null : 'single-tenant');
    }

    /** Create a minimal credential envelope without profile or permission data. */
    public static function fromMember(Member $member): array
    {
        $tenantId = self::tenantId();
        if ($tenantId === null || empty($member->auth_version)) {
            throw new RuntimeException('Authentication context is unavailable.');
        }

        return [
            'version' => 1,
            'tenant_id' => $tenantId,
            'member_id' => (int)$member->id,
            'auth_version' => (string)$member->auth_version,
            'issued_at' => time(),
        ];
    }

    /** Compare the credential epoch and current account eligibility. */
    public static function matches(mixed $state, Member $member): bool
    {
        return is_array($state)
            && ($state['version'] ?? null) === 1
            && self::tenantId() !== null
            && ($state['tenant_id'] ?? null) === self::tenantId()
            && ($state['member_id'] ?? null) === (int)$member->id
            && is_string($state['auth_version'] ?? null)
            && !empty($member->auth_version)
            && hash_equals((string)$member->auth_version, $state['auth_version'])
            && self::eligible($member);
    }

    /** Check account states allowed to authenticate. */
    public static function eligible(Member $member): bool
    {
        return empty($member->deleted) && !in_array($member->status, [
            Member::STATUS_DEACTIVATED,
            Member::STATUS_UNVERIFIED_MINOR,
            Member::STATUS_MINOR_MEMBERSHIP_VERIFIED,
        ], true);
    }
}
