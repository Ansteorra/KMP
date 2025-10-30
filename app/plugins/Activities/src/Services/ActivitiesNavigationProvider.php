<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Activities Navigation Provider Service
 * 
 * **Purpose**: Provides navigation items for the Activities plugin with dynamic badge
 * support, permission-based visibility, and comprehensive workflow integration.
 * 
 * **Core Responsibilities**:
 * - Navigation Item Generation - Complete Activities plugin navigation structure
 * - Dynamic Badge Support - Real-time approval queue counts and notifications
 * - Permission Integration - Role-based navigation visibility
 * - Plugin State Management - Conditional navigation based on plugin availability
 * - Workflow Integration - Navigation items aligned with approval workflows
 * 
 * **Architecture**: 
 * This service replaces the event-driven navigation system (CallForNavHandler) with
 * a more efficient and maintainable static provider pattern. It generates navigation
 * items dynamically based on user permissions and current workflow state.
 * 
 * **Navigation Categories**:
 * - **Personal Workflows**: My Auth Queue with real-time badge counts
 * - **Administrative Tools**: Auth Queues management and oversight
 * - **Configuration Management**: Activity Groups and Activities administration
 * - **Reporting Tools**: Authorization analytics and compliance reports
 * 
 * **Dynamic Features**:
 * - Real-time approval queue badge counts
 * - Permission-based item visibility
 * - Active path highlighting for current context
 * - Icon-based visual navigation
 * - Hierarchical menu organization
 * 
 * **Badge System Integration**:
 * Navigation items include dynamic badge support that displays real-time counts
 * of pending approvals, providing immediate workflow status visibility to users
 * with approval authority.
 * 
 * **Permission Integration**:
 * Navigation items are filtered based on user permissions and plugin availability,
 * ensuring users only see functionality they can access and use.
 * 
 * **Performance Considerations**:
 * - Static method design for efficient navigation generation
 * - Plugin availability checking to avoid unnecessary processing
 * - Lazy loading of badge counts through table method callbacks
 * - Efficient navigation structure for fast rendering
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // Get navigation items for current user
 * $user = $this->Authentication->getIdentity();
 * $navigationItems = ActivitiesNavigationProvider::getNavigationItems($user);
 * 
 * // Navigation items include:
 * // - My Auth Queue (with real-time badge count)
 * // - Auth Queues (administrative oversight)
 * // - Activity Groups (configuration management)
 * // - Activities (activity management)
 * // - Activity Authorizations (reporting)
 * ```
 * 
 * **Integration Points**:
 * - StaticHelpers::pluginEnabled() - Plugin availability checking
 * - AuthorizationApprovalsTable::memberAuthQueueCount() - Badge count calculation
 * - KMP Navigation System - Navigation item registration and rendering
 * - Bootstrap Icons - Icon-based visual navigation
 * 
 * **Troubleshooting**:
 * - Verify plugin is enabled in configuration
 * - Check user permissions for navigation item visibility
 * - Validate badge count methods are accessible
 * - Monitor navigation rendering performance
 * 
 * @see StaticHelpers Plugin management utilities
 * @see AuthorizationApprovalsTable Badge count calculation
 * @see Member User entity for permission context
 */
class ActivitiesNavigationProvider
{
    /**
     * Build the Activities plugin navigation structure with dynamic badges and permission-aware visibility.
     *
     * When the Activities plugin is disabled this returns an empty array. Otherwise it returns
     * an array of associative navigation item definitions (labels, urls, icons, order, optional
     * badge configuration and active path hints) suitable for rendering the Activities menu.
     *
     * @param Member $user The current authenticated member used for personalization and badge calculations.
     * @param array $params Optional context parameters (currently unused).
     * @return array An array of navigation item definitions for the Activities plugin. 
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Activities') == false) {
            return [];
        }

        return [
            [
                "type" => "link",
                "mergePath" => ["Members", $user->sca_name],
                "label" => "My Auth Queue",
                "order" => 20,
                "url" => [
                    "controller" => "AuthorizationApprovals",
                    "plugin" => "Activities",
                    "model" => "Activities.AuthorizationApprovals",
                    "action" => "myQueue",
                ],
                "icon" => "bi-person-fill-check",
            ],
            [
                "type" => "link",
                "mergePath" => ["Action Items"],
                "label" => "Pending Auths",
                "order" => 20,
                "url" => [
                    "controller" => "AuthorizationApprovals",
                    "plugin" => "Activities",
                    "model" => "Activities.AuthorizationApprovals",
                    "action" => "myQueue",
                    "?" => ['src' => 'action_items']
                ],
                "icon" => "bi-person-fill-check",
                "badgeClass" => "bg-danger",
                "badgeValue" => [
                    "class" => "Activities\Model\Table\AuthorizationApprovalsTable",
                    "method" => "memberAuthQueueCount",
                    "argument" => $user->id
                ],
            ],
            [
                "type" => "link",
                "mergePath" => ["Members", "Members"],
                "label" => "Auth Queues",
                "order" => 10,
                "url" => [
                    "controller" => "AuthorizationApprovals",
                    "action" => "index",
                    "plugin" => "Activities",
                    "model" => "Activities.AuthorizationApprovals",
                ],
                "icon" => "bi-card-checklist",
                "activePaths" => [
                    "activities/AuthorizationApprovals/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Activity Groups",
                "order" => 20,
                "url" => [
                    "controller" => "ActivityGroups",
                    "plugin" => "Activities",
                    "action" => "index",
                    "model" => "Activities.ActivityGroups",
                ],
                "icon" => "bi-archive",
                "activePaths" => [
                    "activities/ActivityGroups/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Activities",
                "order" => 30,
                "url" => [
                    "controller" => "Activities",
                    "action" => "index",
                    "plugin" => "Activities",
                    "model" => "Activities.Activities",
                ],
                "icon" => "bi-collection",
                "activePaths" => [
                    "activities/activities/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Activity Authorizations",
                "order" => 10,
                "url" => [
                    "controller" => "Reports",
                    "action" => "Authorizations",
                    "plugin" => "Activities",
                ],
                "icon" => "bi-person-lines-fill",
            ]
        ];
    }
}