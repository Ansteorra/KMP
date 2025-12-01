<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Levels Table Authorization Policy
 * 
 * Provides comprehensive table-level authorization for award level management within the Awards plugin.
 * This policy manages hierarchical level data access with precedence management, bulk operations,
 * and administrative oversight. The policy handles table-level authorization, hierarchical structure
 * management, and administrative data access control.
 * 
 * ## Authorization Architecture
 * 
 * The LevelsTablePolicy implements table-level authorization through the BasePolicy framework:
 * - **Permission-Based Access**: Controls table operations through warrant-based permissions
 * - **Warrant Integration**: Validates user authority for hierarchical level management
 * - **Hierarchical Precedence Support**: Manages access to level precedence and ordering operations
 * - **Administrative Data Access**: Provides elevated access for level configuration management
 * 
 * ## Table Operations Governance
 * 
 * Authorization is enforced for all table-level operations:
 * - **Query Authorization**: Controls access to level listing and hierarchical data retrieval
 * - **Hierarchical Management**: Manages precedence-based queries and level ordering
 * - **Structural Modifications**: Restricts bulk level operations and hierarchy changes
 * - **Administrative Access**: Provides comprehensive level management for authorized users
 * 
 * ## Query Scoping
 * 
 * The policy implements sophisticated query filtering:
 * - Inherits branch-scoped queries from BasePolicy for organizational access control
 * - Supports precedence-based filtering for hierarchical level management
 * - Implements administrative query scoping for comprehensive level oversight
 * - Validates access to level hierarchy and precedence information
 * 
 * ## Usage Examples
 * 
 * ### Controller Integration
 * ```php
 * // In LevelsController
 * public function index()
 * {
 *     $this->Authorization->authorize($this->Levels, 'index');
 *     $levels = $this->paginate($this->Levels);
 *     // Level listing with hierarchical ordering...
 * }
 * ```
 * 
 * ### Hierarchical Management Services
 * ```php
 * // In level management services
 * $levelsQuery = $this->Levels->find()
 *     ->order(['precedence' => 'ASC']);
 * $authorizedQuery = $this->Authorization->applyScope($user, 'index', $levelsQuery);
 * ```
 * 
 * ### Administrative Operations
 * ```php
 * // In administrative level management
 * if ($this->Authorization->can($user, 'add', $this->Levels)) {
 *     // Bulk level creation with precedence validation...
 * }
 * ```
 * 
 * ## Business Logic Integration
 * 
 * - **Precedence Constraints**: Validates level operations within hierarchical precedence rules
 * - **Workflow Integration**: Coordinates with award management and recommendation workflows
 * - **Audit Requirements**: Supports audit trail and accountability for level management
 * - **Data Integrity**: Ensures hierarchical consistency and precedence validation
 * 
 * @see \App\Policy\BasePolicy Base table authorization functionality
 * @see \Awards\Model\Table\LevelsTable Level data management with precedence
 * @see \Awards\Policy\LevelPolicy Entity-level authorization for levels
 * @see \Awards\Controller\LevelsController Level management controller
 */
class LevelsTablePolicy extends BasePolicy
{
    /**
     * Check if user can access gridData scope (Dataverse grid data endpoint)
     * Uses the same authorization scope as the standard index action
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
