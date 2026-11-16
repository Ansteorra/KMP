<?php
declare(strict_types=1);

namespace Activities\Services;

use App\Services\UiUpdateProviderInterface;

/** Invalidation metadata is resolved against the active tenant at use time. */
final class UiUpdateProvider implements UiUpdateProviderInterface
{
    /** @inheritDoc */
    public static function definitions(): array
    {
        return [
            'Activities.Activities' => ['controller' => 'Activities',
                'invalidatesScreens' => ['Members', 'Gatherings', 'Activities.Authorizations']],
            'Activities.ActivityGroups' => ['controller' => 'ActivityGroups',
                'invalidatesScreens' => ['Activities.Activities', 'Members']],
            'Activities.Authorizations' => [
                'controller' => 'Authorizations',
                'parents' => ['member_id' => 'Members'],
                'changes' => ['authorizations'],
            ],
        ];
    }
}
