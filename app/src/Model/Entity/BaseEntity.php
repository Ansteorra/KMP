<?php
declare(strict_types=1);

/**
 * Kingdom Management Portal (KMP) - Base Entity Class
 * 
 * This is the foundational entity class for the KMP application, providing shared
 * functionality across all entity classes in the system. It handles critical
 * entity-wide concerns including branch-based authorization support, common
 * property access patterns, and standardized entity behavior.
 * 
 * ## Core Responsibilities
 * 
 * ### 1. Branch-Based Authorization Support
 * - Provides standardized method for retrieving entity branch association
 * - Enables branch-based data filtering and security enforcement
 * - Supports both direct and indirect branch relationships
 * 
 * ### 2. Common Entity Patterns
 * - Establishes consistent entity inheritance hierarchy
 * - Provides extension points for specialized entity behaviors
 * - Maintains compatibility with CakePHP ORM patterns
 * 
 * ### 3. Integration Foundation
 * - Serves as integration point for authorization system
 * - Provides consistent interface for entity-level operations
 * - Enables plugin entities to inherit core KMP functionality
 * 
 * ## Architecture Integration
 * 
 * This base entity integrates with several KMP subsystems:
 * - Authorization System: Via getBranchId() for branch-based access control
 * - Plugin System: Provides consistent behavior across all plugins
 * - ORM Layer: Maintains compatibility with CakePHP entity patterns
 * - Security Layer: Enables data isolation through branch associations
 * 
 * ## Entity Hierarchy
 * 
 * KMP uses a two-tier entity inheritance structure:
 * ```
 * BaseEntity (this class)
 * ├── Branch                    // Direct branch entities
 * ├── Member                    // User entities with branch associations
 * ├── Permission               // Security entities
 * ├── ActiveWindowBaseEntity   // Time-bounded entities
 * │   ├── Warrant             // Officer appointments
 * │   ├── Authorization       // Activity permissions
 * │   └── ...                 // Other time-bounded entities
 * └── Plugin Entities         // All plugin-specific entities
 *     ├── Award               // Awards plugin entities
 *     ├── Activity            // Activities plugin entities
 *     └── ...                 // Other plugin entities
 * ```
 * 
 * ## Usage Patterns
 * 
 * ### Direct Inheritance
 * Most entities extend BaseEntity directly:
 * ```php
 * class Member extends BaseEntity
 * {
 *     // Member-specific properties and methods
 *     protected array $_accessible = [
 *         'sca_name' => true,
 *         'email' => true,
 *         // ...
 *     ];
 * }
 * ```
 * 
 * ### Specialized Inheritance via ActiveWindowBaseEntity
 * Time-bounded entities extend ActiveWindowBaseEntity:
 * ```php
 * class Warrant extends ActiveWindowBaseEntity
 * {
 *     // Inherits start/expire functionality
 *     // Additional warrant-specific methods
 * }
 * ```
 * 
 * ### Branch Association Patterns
 * Entities implement branch relationships in different ways:
 * 
 * #### Direct Branch ID
 * ```php
 * class Award extends BaseEntity
 * {
 *     // Has branch_id property - uses default getBranchId()
 * }
 * ```
 * 
 * #### Indirect Branch Association
 * ```php
 * class Recommendation extends BaseEntity
 * {
 *     public function getBranchId(): ?int
 *     {
 *         // Gets branch from associated award
 *         return $this->award ? $this->award->branch_id : null;
 *     }
 * }
 * ```
 * 
 * #### Dynamic Branch Resolution
 * ```php
 * class Note extends BaseEntity
 * {
 *     public function getBranchId(): ?int
 *     {
 *         // Gets branch from associated entity
 *         return $this->getTableLocator()
 *             ->get($this->entity_type)
 *             ->get($this->entity_id)
 *             ->branch_id;
 *     }
 * }
 * ```
 * 
 * ## Security Considerations
 * 
 * - Branch ID access enables authorization system to enforce data isolation
 * - Entities without proper branch association may bypass security controls
 * - Child classes must implement appropriate branch relationship logic
 * - Branch ID should never be null for entities requiring access control
 * 
 * ## Performance Considerations
 * 
 * - Default getBranchId() implementation is lightweight (property access)
 * - Complex branch resolution should be cached where appropriate
 * - Consider eager loading of branch relationships for bulk operations
 * 
 * @package App\Model\Entity
 * @author KMP Development Team
 * @since KMP 1.0
 * @see \Cake\ORM\Entity For base entity functionality
 * @see \App\Model\Entity\ActiveWindowBaseEntity For time-bounded entities
 * @see \App\KMP\KmpIdentityInterface For identity implementation
 */

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Base Entity for KMP Application
 * 
 * This abstract base class provides foundational functionality for all entities
 * in the KMP system. It establishes common patterns for branch-based authorization,
 * property access, and entity behavior standardization.
 * 
 * ## Key Features
 * 
 * - Branch-based authorization support via getBranchId()
 * - Consistent entity inheritance hierarchy
 * - Integration points for authorization and security systems
 * - Plugin compatibility and standardization
 * 
 * ## Entity Property Documentation
 * 
 * While this base class doesn't define specific properties, it establishes
 * patterns that child entities follow. Common property patterns include:
 * 
 * ### Standard Entity Properties
 * - `id`: Primary key (int)
 * - `created`: Creation timestamp (DateTime)
 * - `modified`: Last modification timestamp (DateTime)
 * - `created_by`: Creator user ID (int)
 * - `modified_by`: Last modifier user ID (int)
 * 
 * ### Branch Association Properties
 * - `branch_id`: Direct branch association (int)
 * - Alternative indirect associations via related entities
 * 
 * ### Status and State Properties
 * - `status`: Entity status (string) - for entities with workflow
 * - `state`: Entity state (string) - for entities with detailed state tracking
 * - `deleted`: Soft delete flag (boolean) - for entities supporting soft delete
 * 
 * ## Usage Examples
 * 
 * ### Basic Entity Extension
 * ```php
 * class MyEntity extends BaseEntity
 * {
 *     protected array $_accessible = [
 *         'name' => true,
 *         'description' => true,
 *         'branch_id' => true,
 *     ];
 * }
 * ```
 * 
 * ### Entity with Custom Branch Logic
 * ```php
 * class MyComplexEntity extends BaseEntity
 * {
 *     public function getBranchId(): ?int
 *     {
 *         // Custom logic for branch determination
 *         return $this->member ? $this->member->branch_id : null;
 *     }
 * }
 * ```
 * 
 * @property int $id Primary key identifier
 * @property \Cake\I18n\DateTime|null $created Creation timestamp
 * @property \Cake\I18n\DateTime|null $modified Last modification timestamp
 * @property int|null $created_by ID of the user who created this entity
 * @property int|null $modified_by ID of the user who last modified this entity
 * @property int|null $branch_id Associated branch ID (when applicable)
 * @property bool|null $deleted Soft delete flag (when applicable)
 */
abstract class BaseEntity extends Entity
{
    /**
     * Get the branch ID associated with this entity
     * 
     * This method provides a standardized way to retrieve the branch ID
     * for any entity in the KMP system. It is used by the authorization
     * system to determine which users have access to this entity based
     * on their branch permissions.
     * 
     * ## Default Implementation
     * 
     * The base implementation assumes the entity has a direct `branch_id`
     * property and returns its value:
     * ```php
     * return $this->branch_id ?? null;
     * ```
     * 
     * ## Child Class Overrides
     * 
     * Child classes should override this method when the branch association
     * is more complex than a direct property:
     * 
     * ### Via Associated Entity
     * ```php
     * public function getBranchId(): ?int
     * {
     *     // Get branch from associated award
     *     return $this->award ? $this->award->branch_id : null;
     * }
     * ```
     * 
     * ### Via Dynamic Loading
     * ```php
     * public function getBranchId(): ?int
     * {
     *     if (!$this->entity_type || !$this->entity_id) {
     *         return null;
     *     }
     *     
     *     $entity = $this->getTableLocator()
     *         ->get($this->entity_type)
     *         ->get($this->entity_id);
     *     
     *     return $entity->branch_id;
     * }
     * ```
     * 
     * ### Via Multiple Sources
     * ```php
     * public function getBranchId(): ?int
     * {
     *     // Try primary branch first, fall back to secondary
     *     return $this->primary_branch_id ?? $this->secondary_branch_id ?? null;
     * }
     * ```
     * 
     * ## Authorization Integration
     * 
     * This method is called by the authorization system to determine access:
     * ```php
     * $entityBranchId = $entity->getBranchId();
     * $userAuthorizedBranches = $user->getAuthorizedBranches();
     * 
     * if (!in_array($entityBranchId, $userAuthorizedBranches)) {
     *     throw new ForbiddenException('Access denied');
     * }
     * ```
     * 
     * ## Performance Considerations
     * 
     * - Default implementation is very lightweight (single property access)
     * - Complex implementations should consider caching for frequently accessed entities
     * - Avoid N+1 queries by eager loading associations when possible
     * - Consider using virtual fields for computed branch associations
     * 
     * ## Return Value Handling
     * 
     * - **null**: Entity has no branch association (may indicate global scope)
     * - **int**: Valid branch ID for authorization checking
     * - **Never throw exceptions**: Authorization system handles access denial
     * 
     * ## Security Implications
     * 
     * - Returning null may grant broader access than intended
     * - Incorrect branch ID can lead to unauthorized data access
     * - This method is critical for data isolation security
     * - Always validate branch associations in complex implementations
     * 
     * @return int|null The branch ID associated with this entity, or null if no association
     * 
     * @example Basic Usage
     * ```php
     * $member = $this->Members->get(123);
     * $branchId = $member->getBranchId(); // Returns member's branch_id
     * ```
     * 
     * @example Authorization Check
     * ```php
     * $entity = $this->MyEntities->get($id);
     * $this->Authorization->authorize($entity); // Uses getBranchId() internally
     * ```
     * 
     * @example Bulk Processing
     * ```php
     * $entities = $this->MyEntities->find()
     *     ->contain(['Award']) // Eager load for complex branch resolution
     *     ->toArray();
     * 
     * foreach ($entities as $entity) {
     *     $branchId = $entity->getBranchId(); // No additional queries needed
     * }
     * ```
     * 
     * @see \App\Services\AuthorizationService For authorization system integration
     * @see \App\Model\Table\BaseTable::addBranchScopeQuery() For query-level branch filtering
     * @see \App\Model\Entity\ActiveWindowBaseEntity For time-bounded entity patterns
     */
    public function getBranchId(): ?int
    {
        // Default implementation assumes direct branch_id property
        // Child classes should override for more complex branch relationships
        return $this->branch_id ?? null;
    }
}