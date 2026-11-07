<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;

/** Keeps plugin delegation out of core gathering policies and UI. */
class GatheringActivityDelegationRegistry
{
    /**
     * @var array<string, \App\Services\GatheringActivityDelegationProviderInterface>
     */
    private static array $providers = [];

    /** Retained providers must resolve tenant tables only when invoked. */
    public static function register(string $owner, GatheringActivityDelegationProviderInterface $provider): void
    {
        self::$providers[$owner] = $provider;
    }

    /** Current event grants supplement native assignment without changing the identity. */
    public static function canAssign(
        KmpIdentityInterface $user,
        GatheringActivity $activity,
        Gathering $gathering,
    ): bool {
        foreach (self::$providers as $provider) {
            if ($provider->canAssign($user, $activity, $gathering)) {
                return true;
            }
        }

        return false;
    }
}
