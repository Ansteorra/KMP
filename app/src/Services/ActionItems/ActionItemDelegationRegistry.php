<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

use App\Model\Entity\ActionItem;

/** Plugin-owned eligibility extensions, keyed by persisted owner type. */
class ActionItemDelegationRegistry
{
    /**
     * @var array<string, \App\Services\ActionItems\ActionItemDelegationProviderInterface>
     */
    private static array $providers = [];

    /** Providers must resolve tenant tables lazily, after request tenant resolution. */
    public static function register(string $entityType, ActionItemDelegationProviderInterface $provider): void
    {
        self::$providers[$entityType] = $provider;
    }

    /** Return the extension registered for this persisted owner type. */
    public static function providerFor(ActionItem $item): ?ActionItemDelegationProviderInterface
    {
        return self::$providers[(string)$item->entity_type] ?? null;
    }

    /** Add candidates only; the assignee resolver still checks every returned item. */
    public static function candidateConditions(int $memberId): array
    {
        $conditions = [];
        foreach (self::$providers as $provider) {
            $conditions = array_merge($conditions, $provider->candidateConditions($memberId));
        }

        return $conditions;
    }
}
