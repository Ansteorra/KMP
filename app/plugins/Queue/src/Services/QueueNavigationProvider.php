<?php

declare(strict_types=1);

namespace Queue\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Queue Navigation Provider
 * 
 * Provides Queue plugin navigation items.
 * Replaces the functionality from Queue\Event\CallForNavHandler
 */
class QueueNavigationProvider
{
    /**
     * Get Queue plugin navigation items
     *
     * @param Member $user Current user
     * @param array $params Request parameters
     * @return array Navigation items
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Queue') == false) {
            return [];
        }

        return [
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Queue Engine",
                "order" => 40,
                "url" => [
                    "plugin" => "Queue",
                    "controller" => "Queue",
                    "action" => "index",
                    "model" => "Queue.QueuedJobs",
                ],
                "icon" => "bi-stack",
                "activePaths" => [
                    "queue/*",
                ]
            ],
        ];
    }
}
