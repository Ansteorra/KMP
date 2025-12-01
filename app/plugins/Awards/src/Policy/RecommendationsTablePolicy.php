<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */

/**
 * Recommendations Table Authorization Policy
 * 
 * Provides comprehensive table-level authorization for recommendation workflow management within
 * the Awards plugin. This policy manages recommendation data access with workflow scoping, bulk
 * operations, and administrative oversight. The policy handles table-level authorization,
 * workflow structure management, and administrative data access control.
 * 
 * ## Authorization Architecture
 * 
 * The RecommendationsTablePolicy implements table-level authorization through the BasePolicy framework:
 * - **Permission-Based Access**: Controls table operations through warrant-based permissions
 * - **Warrant Integration**: Validates user authority for recommendation workflow management
 * - **Workflow Scoping Support**: Manages access to recommendation lifecycle and approval operations
 * - **Administrative Data Access**: Provides elevated access for recommendation oversight
 * 
 * ## Table Operations Governance
 * 
 * Authorization is enforced for all table-level operations:
 * - **Query Authorization**: Controls access to recommendation listing and workflow data retrieval
 * - **Workflow Management**: Manages state-based queries and approval level filtering
 * - **State-Based Filtering**: Restricts recommendation access based on approval authority
 * - **Administrative Access**: Provides comprehensive recommendation management for authorized users
 * 
 * ## Advanced Query Scoping
 * 
 * ### scopeIndex()
 * Implements sophisticated recommendation filtering based on user approval authority and
 * organizational scope. The method performs multi-level authorization checking:
 * 
 * 1. **Branch-Based Scoping**: Filters recommendations by organizational branch access
 * 2. **Approval Level Discovery**: Analyzes user permissions to identify approval authority
 * 3. **Dynamic Filtering**: Restricts recommendations to user's approval levels
 * 4. **Permission Integration**: Uses RecommendationPolicy analysis for dynamic authorization
 * 
 * The scoping logic discovers approval levels by analyzing user policies for methods
 * starting with 'canApproveLevel', then filters recommendations to only show those
 * at award levels the user can approve.
 * 
 * ```php
 * // Branch scoping with approval level filtering
 * $query = $table->addBranchScopeQuery($query, $branchIds);
 * $query = $query->contain(['Awards.Levels'])
 *     ->where(['Levels.name in' => $approvalLevels]);
 * ```
 * 
 * ### canAdd()
 * Provides open access for recommendation submission, supporting both authenticated
 * and unauthenticated recommendation creation. This enables public recommendation
 * submission workflows while maintaining security through entity-level policies.
 * 
 * ## Usage Examples
 * 
 * ### Controller Integration
 * ```php
 * // In RecommendationsController
 * public function index()
 * {
 *     $this->Authorization->authorize($this->Recommendations, 'index');
 *     $recommendations = $this->paginate($this->Recommendations);
 *     // Recommendation listing with approval level filtering...
 * }
 * ```
 * 
 * ### Workflow Management Services
 * ```php
 * // In recommendation management services
 * $query = $this->Recommendations->find()
 *     ->contain(['Awards', 'Events', 'Members']);
 * $authorizedQuery = $this->Authorization->applyScope($user, 'index', $query);
 * ```
 * 
 * ### Administrative Operations
 * ```php
 * // In administrative recommendation management
 * if ($this->Authorization->can($user, 'add', $this->Recommendations)) {
 *     // Bulk recommendation processing with workflow validation...
 * }
 * ```
 * 
 * ## Business Logic Integration
 * 
 * - **Workflow Constraints**: Validates recommendation operations within workflow rules and state management
 * - **State Management**: Coordinates with recommendation state machine and approval workflows
 * - **Audit Requirements**: Supports audit trail and accountability for recommendation management
 * - **Data Integrity**: Ensures workflow consistency and approval authority validation
 * 
 * @see \App\Policy\BasePolicy Base table authorization functionality
 * @see \Awards\Model\Table\RecommendationsTable Recommendation data management with state machine
 * @see \Awards\Policy\RecommendationPolicy Entity-level authorization with dynamic approval methods
 * @see \Awards\Controller\RecommendationsController Recommendation management controller
 */
class RecommendationsTablePolicy extends BasePolicy
{
    /**
     * Apply authorization scoping to recommendation queries
     * 
     * Implements sophisticated recommendation filtering based on user approval authority
     * and organizational scope. This method performs multi-level authorization checking
     * to ensure users only see recommendations they have authority to manage.
     * 
     * The scoping process:
     * 1. Discovers branch access through _getBranchIdsForPolicy()
     * 2. Analyzes user policies to identify approval authority levels
     * 3. Extracts approval levels from 'canApproveLevel*' permission methods
     * 4. Applies branch scoping through addBranchScopeQuery()
     * 5. Filters recommendations by award levels user can approve
     * 
     * @param \App\KMP\KmpIdentityInterface $user The user requesting data access
     * @param \Cake\ORM\Query $query The base query to scope
     * @return \Cake\ORM\Query The scoped query with authorization filtering
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        $table = $query->getRepository();
        $branchIds = $this->_getBranchIdsForPolicy($user, "canIndex");

        // Extract user policies for approval authority discovery
        $branchPolicies = $user->getPolicies($branchIds);
        $approvalLevels = [];
        $recommendationPolicies = $branchPolicies["Awards\Policy\RecommendationPolicy"] ?? [];

        // Discover approval levels from dynamic permission methods
        foreach ($recommendationPolicies as $method => $policy) {
            if (strpos($method, 'canApproveLevel') === 0) {
                $level = str_replace("canApproveLevel", "", $method);
                $approvalLevels[] = $level;
            }
        }

        // Apply branch scoping if branch restrictions exist
        if (!empty($branchIds)) {
            if ($branchIds[0] != -10000000) {  // Global access check
                $query = $table->addBranchScopeQuery($query, $branchIds);
            }
        }

        // Filter by approval levels if user has specific approval authority
        if (!empty($approvalLevels)) {
            return $query->contain(['Awards.Levels'])->where(['Levels.name in' => $approvalLevels]);
        }

        return $query;
    }

    /**
     * Authorize recommendation creation
     * 
     * Provides open access for recommendation submission to support both authenticated
     * and unauthenticated recommendation workflows. This enables public recommendation
     * submission while relying on entity-level policies for detailed authorization.
     * 
     * @param \App\KMP\KmpIdentityInterface $user The user requesting creation access
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The target entity or table
     * @param mixed ...$optionalArgs Additional authorization arguments
     * @return bool Always returns true for open recommendation submission
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Authorize recommendation export to CSV
     * 
     * Users who can index recommendations can also export them to CSV.
     * This delegates to the canIndex permission check.
     * 
     * @param \App\KMP\KmpIdentityInterface $user The user requesting export access
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The target entity or table
     * @param mixed ...$optionalArgs Additional authorization arguments
     * @return bool True if user has index permission
     */
    public function canExport(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $entity, ...$optionalArgs);
    }
}
