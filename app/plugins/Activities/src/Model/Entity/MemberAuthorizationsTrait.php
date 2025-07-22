<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\TableRegistry;

/**
 * MemberAuthorizationsTrait
 * 
 * Provides authorization-related functionality to Member entities within the Activities Plugin.
 * This trait extends Member entities with methods for managing authorization workflows,
 * tracking approval responsibilities, and providing authorization-related data access.
 * 
 * The trait integrates seamlessly with the Member entity to provide authorization workflow
 * functionality without modifying the core Member class. It focuses specifically on the
 * approver role within authorization workflows, tracking pending approval responsibilities
 * and providing navigation support for approval management.
 * 
 * ## Functionality Overview
 * This trait provides Members with:
 * - **Approval Tracking**: Count and management of pending authorization approvals
 * - **Workflow Integration**: Integration with Activities Plugin approval processes
 * - **Navigation Support**: Data for approval badge counts and navigation indicators
 * - **Performance Optimization**: Efficient queries for approval-related data
 * 
 * ## Integration Architecture
 * The trait integrates with the Activities Plugin architecture:
 * - **AuthorizationApprovals Table**: Direct access for pending approval queries
 * - **TableRegistry**: Dynamic table access for cross-plugin integration
 * - **Member Entity**: Seamless extension of Member functionality
 * - **Navigation System**: Support for approval count badges in navigation
 * 
 * ## Usage Patterns
 * Applied to Member entities through trait inclusion:
 * ```php
 * // In Member entity class
 * use Activities\Model\Entity\MemberAuthorizationsTrait;
 * 
 * class Member extends KMPIdentity {
 *     use MemberAuthorizationsTrait;
 *     // ... other member functionality
 * }
 * ```
 * 
 * ## Performance Considerations
 * - Efficient count queries avoiding unnecessary data loading
 * - Direct table access through TableRegistry for optimal performance
 * - Simple query structure minimizing database overhead
 * - Optimized for navigation badge display and approval workflow integration
 * 
 * @see \App\Model\Entity\Member Member entity that uses this trait
 * @see \Activities\Model\Table\AuthorizationApprovalsTable Authorization approvals table
 * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
 */

trait MemberAuthorizationsTrait
{
    /**
     * Get the number of pending approvals for the user
     * 
     * Returns the count of authorization approval requests where this member
     * is designated as the approver and has not yet responded. This method
     * supports navigation badge counts and approval workflow management.
     * 
     * The query efficiently counts pending approvals by:
     * - Filtering on approver_id matching this member's ID
     * - Checking for null responded_on (indicating no response yet)
     * - Using count() for optimal performance without loading entities
     * 
     * ## Usage Examples
     * 
     * ### Navigation Badge Integration
     * ```php
     * // In navigation or dashboard view
     * $pendingCount = $currentMember->getPendingApprovalsCount();
     * if ($pendingCount > 0) {
     *     echo "<span class='badge badge-warning'>{$pendingCount}</span>";
     * }
     * ```
     * 
     * ### Approval Workflow Management
     * ```php
     * // Check if member has pending approval responsibilities
     * if ($member->getPendingApprovalsCount() > 0) {
     *     // Redirect to approval management interface
     *     return $this->redirect(['plugin' => 'Activities', 'controller' => 'AuthorizationApprovals']);
     * }
     * ```
     * 
     * ### Administrative Reporting
     * ```php
     * // Generate approval workload report
     * $members = $membersTable->find()->all();
     * $approvalWorkload = [];
     * foreach ($members as $member) {
     *     $pendingCount = $member->getPendingApprovalsCount();
     *     if ($pendingCount > 0) {
     *         $approvalWorkload[] = [
     *             'member' => $member,
     *             'pending_approvals' => $pendingCount
     *         ];
     *     }
     * }
     * ```
     * 
     * ## Performance Optimization
     * - Uses TableRegistry for efficient table access without dependency injection
     * - Count query avoids loading AuthorizationApproval entities for better performance
     * - Simple WHERE conditions enable database index optimization
     * - Minimal memory footprint for navigation and badge display use cases
     * 
     * ## Integration Points
     * - **Navigation System**: Badge counts for pending approval indicators
     * - **Dashboard Widgets**: Quick approval status overview
     * - **Approval Controllers**: Workflow entry point validation
     * - **Administrative Reports**: Approval workload and bottleneck analysis
     * 
     * @return int Number of pending authorization approvals for this member
     * 
     * @see \Activities\Model\Table\AuthorizationApprovalsTable For approval management
     * @see \Activities\Controller\AuthorizationApprovalsController For approval processing
     */
    public function getPendingApprovalsCount(): int
    {
        $count = 0;
        $approvalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $query = $approvalsTable->find()
            ->where([
                "approver_id" => $this->id,
                "responded_on is" => null,
            ]);
        $count = $query->count();
        return $count;
    }
}
