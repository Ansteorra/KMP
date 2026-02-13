<?php

declare(strict_types=1);

namespace App\Model\Entity;

/**
 * Branch Entity - Hierarchical Organizational Structure for KMP
 * 
 * Represents a branch in the KMP organizational hierarchy, supporting nested tree structures
 * for kingdoms, principalities, baronies, shires, and other administrative divisions.
 * Provides organizational scoping for members, roles, permissions, and activities.
 * 
 * **Tree Structure Features:**
 * - Hierarchical organization using nested set model (Tree behavior)
 * - Parent-child relationships with unlimited depth
 * - Automatic tree integrity maintenance and recovery
 * - Efficient descendant and ancestor queries with caching
 * 
 * **Member Management:**
 * - Associates members to specific organizational units
 * - Controls member visibility and authorization scope
 * - Supports branch-specific role assignments and permissions
 * - Enables organizational reporting and analytics
 * 
 * **Configuration & Links:**
 * - JSON-based links storage for external resources and websites
 * - Configurable branch types (Kingdom, Principality, Barony, Shire, etc.)
 * - Domain association for organization-specific branding
 * - Member enrollment controls and visibility settings
 * 
 * **Authorization Integration:**
 * - Implements getBranchId() for authorization system compatibility
 * - Supports branch-scoped permissions and data access control
 * - Enables hierarchical permission inheritance through tree structure
 * - Integrates with policy-based authorization framework
 * 
 * **Usage Examples:**
 * ```php
 * // Basic branch information
 * $branch = $branchesTable->get($id);
 * echo $branch->name;          // "Kingdom of Atlantia"
 * echo $branch->location;      // "Eastern United States"
 * echo $branch->type;          // "Kingdom"
 * 
 * // Tree operations
 * $children = $branch->children;           // Direct child branches
 * $descendants = $branch->getAllDescendants(); // All descendant branches
 * $parents = $branch->getAllParents();     // Path to root
 * 
 * // Member associations
 * foreach ($branch->members as $member) {
 *     echo $member->sca_name;
 * }
 * 
 * // JSON links configuration
 * $branch->links = [
 *     'website' => 'https://atlantia.sca.org',
 *     'calendar' => 'https://calendar.atlantia.sca.org',
 *     'newsletter' => 'https://acorn.atlantia.sca.org'
 * ];
 * ```
 * 
 * **Database Schema:**
 * - id: Primary key
 * - name: Unique branch name
 * - location: Geographic or administrative location
 * - type: Branch classification (Kingdom, Principality, etc.)
 * - parent_id: Parent branch for tree structure
 * - links: JSON field for external resource links
 * - can_have_members: Boolean flag for member enrollment
 * - domain: Associated domain for branding and access
 * - lft/rght: Nested set model tree structure fields
 * - created/modified: Timestamp tracking with user attribution
 * 
 * @property int $id Primary key identifier
 * @property string $public_id Public-facing identifier for URL routing
 * @property string $name Unique branch name (e.g., "Kingdom of Atlantia")
 * @property string $location Geographic or administrative location description
 * @property string|null $type Branch classification (Kingdom, Principality, Barony, Shire, etc.)
 * @property int|null $parent_id Parent branch ID for hierarchical structure
 * @property array|null $links JSON array of external resource links and websites
 * @property bool $can_have_members Whether this branch can directly enroll members
 * @property bool $can_have_officers Whether this branch can have officers assigned
 * @property int|null $contact_id FK to members.id for hamlet-mode point of contact
 * @property string|null $domain Associated domain for organization-specific access
 *
 * @property \App\Model\Entity\Member|null $contact Point of contact member for hamlet-mode branches
 * @property int|null $lft Left boundary for nested set model tree structure
 * @property int|null $rght Right boundary for nested set model tree structure
 * @property \Cake\I18n\DateTime $created Creation timestamp
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp
 * @property int|null $created_by ID of user who created this branch
 * @property int|null $modified_by ID of user who last modified this branch
 * 
 * @property \App\Model\Entity\Branch|null $parent Parent branch entity
 * @property \App\Model\Entity\Branch[] $children Direct child branch entities
 * @property \App\Model\Entity\Member[] $members Members associated with this branch
 * 
 * @see \App\Model\Table\BranchesTable For tree operations and caching strategies
 * @see \App\Controller\BranchesController For branch management workflows
 * @see \App\Policy\BranchPolicy For authorization rules and permissions
 * @see \App\Model\Entity\Member For member-branch associations
 * @see \App\KMP\PermissionsLoader For hierarchical permission inheritance
 */
class Branch extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Defines which fields can be safely mass-assigned during entity creation
     * and updates. The ID field is protected to prevent unauthorized changes
     * to the primary key.
     *
     * **Security Considerations:**
     * - ID field is protected to prevent primary key manipulation
     * - All other fields are accessible for administrative flexibility
     * - Tree structure fields (lft/rght) are managed by Tree behavior
     * - Timestamp fields are handled by Timestamp behavior
     *
     * **Mass Assignment Examples:**
     * ```php
     * // Safe mass assignment
     * $branch = $branchesTable->newEntity([
     *     'name' => 'Barony of Windmasters Hill',
     *     'location' => 'Northern Virginia',
     *     'type' => 'Barony',
     *     'parent_id' => $atlantiaId,
     *     'links' => [
     *         'website' => 'https://windmastershill.atlantia.sca.org'
     *     ]
     * ]);
     * 
     * // Update existing branch
     * $branchesTable->patchEntity($branch, [
     *     'location' => 'Updated Location',
     *     'links' => $updatedLinks
     * ]);
     * ```
     *
     * @var array<string, bool> Mass assignment configuration
     */
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];

    /**
     * Get branch ID for authorization system compatibility.
     * 
     * Implements the getBranchId() pattern required by the KMP authorization
     * system. For Branch entities, this returns the entity's own ID since
     * branches are the primary organizational scope for authorization.
     * 
     * **Authorization Integration:**
     * - Enables branch-scoped policy authorization
     * - Supports hierarchical permission inheritance
     * - Integrates with PermissionsLoader for role-based access control
     * - Used by AuthorizationService for scope validation
     * 
     * **Hierarchical Permissions:**
     * The authorization system uses this ID in combination with the tree
     * structure to determine:
     * - Direct branch permissions (this branch only)
     * - Inherited permissions (from parent branches)
     * - Descendant permissions (applied to child branches)
     * 
     * **Usage Examples:**
     * ```php
     * // Direct authorization check
     * $user->checkCan('edit', $branch);  // Uses $branch->getBranchId()
     * 
     * // Policy-based authorization
     * $this->Authorization->authorize($branch);  // In controller
     * 
     * // Permission inheritance
     * $kingdoms = $user->getPermission('manage_branches')->branch_ids;
     * // Includes all descendant branches through tree hierarchy
     * ```
     * 
     * @return int|null The branch's own ID for authorization scoping, or null if entity is new
     * @see \App\Model\Entity\BaseEntity::getBranchId() Base implementation pattern
     * @see \App\KMP\PermissionsLoader For hierarchical permission processing
     * @see \App\Services\AuthorizationService For authorization workflow integration
     */
    public function getBranchId(): ?int
    {
        return $this->id;
    }
}
