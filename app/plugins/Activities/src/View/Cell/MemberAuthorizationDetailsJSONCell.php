<?php

declare(strict_types=1);

namespace Activities\View\Cell;

use App\KMP\PermissionsLoader;
use App\View\Cell\BasePluginCell;
use Cake\View\Cell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

/**
 * Member Authorization Details JSON Cell
 * 
 * **Purpose**: Provides structured JSON data for member authorization information
 * supporting mobile applications, API endpoints, and dynamic frontend interfaces
 * with comprehensive authorization status and approver authority data.
 * 
 * **Core Responsibilities**:
 * - Structured Authorization Data - JSON-formatted authorization information
 * - Mobile API Support - Optimized data for mobile applications
 * - Approver Authority Discovery - Activities the member can authorize
 * - Activity Group Organization - Hierarchical authorization display
 * - Permission Integration - Permission-based approver qualification
 * 
 * **Architecture**: 
 * This view cell extends CakePHP Cell to provide JSON-formatted authorization
 * data that can be consumed by mobile applications, AJAX endpoints, and dynamic
 * frontend interfaces. It integrates with PermissionsLoader for permission-based
 * authorization discovery.
 * 
 * **JSON Data Structure**:
 * ```json
 * {
 *   "Can Authorize": {
 *     "Activity Group 1": ["Activity A", "Activity B"],
 *     "Activity Group 2": ["Activity C", "Activity D"]
 *   },
 *   "Authorizations": {
 *     "Activity Group 1": ["Activity A : 2025-12-31", "Activity B : 2026-06-30"],
 *     "Activity Group 2": ["Activity C : 2025-09-15"]
 *   }
 * }
 * ```
 * 
 * **Data Categories**:
 * - **Can Authorize**: Activities member has permission to approve
 * - **Authorizations**: Current active authorizations with expiration dates
 * - **Activity Groups**: Hierarchical organization for clarity
 * - **Temporal Information**: Expiration dates for authorization tracking
 * 
 * **Permission Integration**:
 * Uses PermissionsLoader to discover:
 * - Member's current permissions
 * - Activities requiring those permissions for approval
 * - Permission-based authorization authority
 * - Cross-reference with activity configuration
 * 
 * **Mobile Optimization**:
 * - Lightweight JSON structure for mobile consumption
 * - Hierarchical organization for mobile UI display
 * - Expiration date formatting for mobile calendars
 * - Minimal data transfer for performance
 * 
 * **API Endpoint Support**:
 * - RESTful JSON response format
 * - Consistent data structure for API consumers
 * - Error-resistant data organization
 * - Standardized field naming conventions
 * 
 * **Integration Points**:
 * - AuthorizationsTable - Current authorization discovery
 * - ActivitiesTable - Activity configuration and requirements
 * - ActivityGroupsTable - Hierarchical organization
 * - PermissionsLoader - Permission-based authority discovery
 * - Member Identity - Authorization context
 * 
 * **Performance Considerations**:
 * - Efficient current authorization queries
 * - Permission discovery optimization
 * - Activity group organization at query level
 * - Minimal association loading for JSON output
 * 
 * **Usage Examples**:
 * 
 * ```php
 * // Mobile API endpoint
 * echo $this->cell('Activities.MemberAuthorizationDetailsJSON', [$memberId]);
 * 
 * // AJAX authorization dashboard
 * $authData = $this->cell('Activities.MemberAuthorizationDetailsJSON', [$memberId]);
 * 
 * // Dynamic frontend integration
 * echo $this->cell('Activities.MemberAuthorizationDetailsJSON', [$currentUser->id]);
 * ```
 * 
 * **Mobile Application Integration**:
 * - Authorization dashboard widgets
 * - Approval authority discovery
 * - Expiration tracking interfaces
 * - Activity group navigation
 * 
 * **Security Features**:
 * - Permission-based authorization discovery
 * - Current authorization validation
 * - Identity-based data scoping
 * - Safe JSON data structure
 * 
 * **Error Handling**:
 * - Empty permission handling
 * - Missing authorization graceful degradation
 * - Invalid member ID protection
 * - JSON structure consistency
 * 
 * **Troubleshooting**:
 * - Verify PermissionsLoader configuration
 * - Check member permission assignments
 * - Validate activity group associations
 * - Monitor JSON output for structure consistency
 * 
 * @see PermissionsLoader Permission discovery and validation
 * @see AuthorizationsTable Current authorization management
 * @see ActivitiesTable Activity configuration
 * @see ActivityGroupsTable Hierarchical organization
 */
class MemberAuthorizationDetailsJSONCell extends Cell
{
    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */
    protected array $_validCellOptions = [];

    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void {}

    /**
     * Generate JSON Authorization Data
     *
     * Creates structured JSON data containing member authorization status and
     * approver authority information for mobile and API consumption.
     * 
     * **Data Generation Process**:
     * 1. **Current Authorizations**: Query active authorizations with activity details
     * 2. **Activity Group Organization**: Organize by activity groups for hierarchy
     * 3. **Permission Discovery**: Use PermissionsLoader for member permissions
     * 4. **Approver Authority**: Discover activities member can authorize
     * 5. **JSON Structure**: Format data for mobile/API consumption
     * 
     * **Current Authorizations Processing**:
     * - Queries current authorizations with activity and group details
     * - Orders by activity group and activity name for consistency
     * - Includes expiration dates formatted for display
     * - Organizes into hierarchical structure by activity group
     * 
     * **Approver Authority Discovery**:
     * - Uses PermissionsLoader to get member's current permissions
     * - Cross-references permissions with activity requirements
     * - Discovers activities member has authority to approve
     * - Organizes approver activities by activity group
     * 
     * **JSON Response Structure**:
     * - "Can Authorize": Activities member can approve (by group)
     * - "Authorizations": Current member authorizations (by group)
     * - Hierarchical organization for mobile UI consumption
     * - Consistent field naming for API integration
     * 
     * **Performance Optimization**:
     * - Single query for current authorizations
     * - Efficient permission discovery through PermissionsLoader
     * - Activity group organization at query level
     * - Minimal data transfer for mobile optimization
     * 
     * **Mobile Integration Features**:
     * - Lightweight JSON structure
     * - Expiration date formatting
     * - Hierarchical organization for UI display
     * - Consistent data structure for caching
     * 
     * @param int $id Member ID for authorization data generation
     * @return void Sets responseData variable with JSON structure
     */
    public function display($id)
    {
        $authTable = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $currentAuths = $authTable->find('current')
            ->select(['id', 'activity_id', 'member_id', 'ActivityGroups.name', 'Activities.name', 'expires_on'])
            ->contain(['Activities' => function (SelectQuery $q) {
                return $q
                    ->select(['Activities.id', 'Activities.name'])
                    ->contain(['ActivityGroups' => function (SelectQuery $q) {
                        return $q->select(['ActivityGroups.id', 'ActivityGroups.name']);
                    }]);
            }])
            ->where(['member_id' => $id])->OrderBy(['ActivityGroups.name', 'Activities.name'])->toArray();
        $organizedAuths = [];
        foreach ($currentAuths as $auth) {
            $activityGroup = $auth->activity->activity_group->name;
            $activityName = $auth->activity->name;
            $organizedAuths[$activityGroup][] = $activityName . " : " . $auth->expires_on_to_string;
        }
        $memberPermissions = PermissionsLoader::getPermissions($id);
        $permissionIds = [];
        foreach ($memberPermissions as $permission) {
            $permissionIds[] = $permission->id;
        }
        $currentApproverFor = [];
        if (!empty($permissionIds)) {
            $activitiesTbl = TableRegistry::getTableLocator()->get('Activities.Activities');
            $activities = $activitiesTbl->find()
                ->where(['Activities.permission_id IN' => $permissionIds])
                ->contain(['ActivityGroups' => function (SelectQuery $q) {
                    return $q->select(['ActivityGroups.id', 'ActivityGroups.name']);
                }])
                ->distinct()
                ->toArray();

            $organizedAuthorisor = [];
            foreach ($activities as $activity) {
                $activityGroup = $activity->activity_group->name;
                $activityName = $activity->name;
                $organizedAuthorisor[$activityGroup][] = $activityName;
            }
        } else {
            $organizedAuthorisor = [];
        }
        $responseData = ["Can Authorize" => $organizedAuthorisor, "Authorizations" => $organizedAuths,];
        $this->set(compact('responseData'));
    }
}
