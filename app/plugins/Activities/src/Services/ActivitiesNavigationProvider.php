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
     * Get Activities Plugin Navigation Items
     *
     * Generates complete navigation structure for Activities plugin with dynamic
     * badge support, permission-based visibility, and workflow integration.
     * 
     * **Navigation Structure**:
     * 1. **My Auth Queue** - Personal approval queue with real-time badge count
     * 2. **Auth Queues** - Administrative approval queue management
     * 3. **Activity Groups** - Categorical activity organization
     * 4. **Activities** - Activity configuration and management
     * 5. **Activity Authorizations** - Reporting and analytics
     * 
     * **Dynamic Badge Features**:
     * - Real-time approval queue counts for immediate workflow visibility
     * - Color-coded badges (bg-danger) for urgent attention items
     * - Automated badge value calculation through table method callbacks
     * 
     * **Navigation Organization**:
     * - Personal tools under "Members > {User Name}" path
     * - Administrative tools under "Members" and "Config" paths
     * - Reporting tools under "Reports" path
     * - Hierarchical organization with sub-navigation support
     * 
     * **Icon Integration**:
     * Uses Bootstrap Icons for consistent visual navigation:
     * - bi-person-fill-check: Personal approval queue
     * - bi-card-checklist: Administrative queues
     * - bi-archive: Activity groups
     * - bi-collection: Activities
     * - bi-person-lines-fill: Reports
     * 
     * **Active Path Highlighting**:
     * Includes activePaths configuration for context-aware navigation
     * highlighting when users are working within specific workflows.
     * 
     * @param Member $user Current authenticated user for personalization
     * @param array $params Request parameters for context (currently unused)
     * @return array Complete navigation items array with badges and permissions
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
                "mergePath" => ["Config", "Activity Groups"],
                "label" => "New Activity Group",
                "order" => 0,
                "url" => [
                    "controller" => "ActivityGroups",
                    "plugin" => "Activities",
                    "action" => "add",
                    "model" => "Activities.ActivityGroups",
                ],
                "icon" => "bi-plus",
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
                "mergePath" => ["Config", "Activities"],
                "label" => "New Activity",
                "order" => 0,
                "url" => [
                    "controller" => "Activities",
                    "action" => "add",
                    "plugin" => "Activities",
                    "model" => "Activities.Activities",
                ],
                "icon" => "bi-plus",
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
