<?php

declare(strict_types=1);

namespace ActionItems\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Activities Navigation Provider
 * 
 * Provides Activities plugin navigation items.
 * Replaces the functionality from Activities\Event\CallForNavHandler
 */
class ActionItemsNavigationProvider
{
    /**
     * Get ActionItems plugin navigation items
     *
     * @param Member $user Current user
     * @param array $params Request parameters
     * @return array Navigation items
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('ActionItems') == false) {
            return [];
        }

        return [
            [
                "type" => "link",
                "mergePath" => ["Action Items", $user->sca_name],
                "label" => "My Auth Queue",
                "order" => 20,
                "url" => [
                    "controller" => "AuthorizationApprovals",
                    "plugin" => "Activities",
                    "model" => "Activities.AuthorizationApprovals",
                    "action" => "myQueue",
                ],
                "icon" => "bi-person-fill-check",
                "badgeClass" => "bg-danger",
                "badgeValue" => [
                    "class" => "Activities\Model\Table\AuthorizationApprovalsTable",
                    "method" => "memberAuthQueueCount",
                    "argument" => $user->id
                ],
            ]
        ];
    }
}