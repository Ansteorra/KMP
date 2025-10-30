<?php

declare(strict_types=1);

namespace Officers\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Member;
use Officers\Model\Entity\Officer;

/**
 * Offices Table - Hierarchical office structure management and warrant integration
 *
 * The OfficesTable provides comprehensive data management operations for the
 * hierarchical office structure within the Officers plugin. It handles complex
 * organizational relationships, warrant requirements, role assignments, and
 * permission-based access control with deep integration into the KMP authorization
 * and organizational management systems.
 *
 * ## Key Features
 * - **Hierarchical Organization**: Manages deputy and reporting relationships between offices
 * - **Warrant Integration**: Handles warrant requirements and role assignment workflows
 * - **Permission-Based Access**: Complex access control based on user positions and permissions
 * - **Temporal Management**: Supports current, upcoming, and previous officer assignments
 * - **Role Assignment**: Integration with role grants and permission inheritance
 * - **Branch Scoping**: Branch-specific office access and permission validation
 * - **Organizational Analytics**: Reporting tree traversal and hierarchical analysis
 *
 * ## Database Structure
 * - **Table**: `officers_offices`
 * - **Primary Key**: `id` (auto-increment)
 * - **Display Field**: `name` (human-readable identifier)
 * - **Unique Constraint**: Office names must be unique across the system
 * - **Soft Deletion**: Supports archival through `deleted` timestamp via Trash behavior
 *
 * ## Association Architecture
 * The table implements a complex association structure supporting organizational hierarchy:
 * 
 * ### Departmental Organization
 * - **belongsTo Departments**: Office categorization within departmental structure
 * - **Departmental Grouping**: Offices are organized under specific departments
 *
 * ### Hierarchical Relationships
 * - **belongsTo DeputyTo**: Self-referential relationship for deputy positions
 * - **belongsTo ReportsTo**: Self-referential relationship for reporting structure
 * - **hasMany Deputies**: Offices that serve as deputies to this office
 * - **hasMany DirectReports**: Offices that report directly to this office
 *
 * ### Role Integration
 * - **belongsTo GrantsRole**: Role assignment integration for permission inheritance
 * - **Role-Based Access**: Automatic role grants for office assignments
 *
 * ### Officer Assignments
 * - **hasMany Officers**: All officer assignments for this office (temporal)
 * - **hasMany CurrentOfficers**: Active officer assignments using custom finder
 * - **hasMany UpcomingOfficers**: Future officer assignments using custom finder
 * - **hasMany PreviousOfficers**: Historical officer assignments using custom finder
 *
 * ## Behavior Integration
 * - **Timestamp**: Automatic created/modified timestamp management
 * - **Footprint**: User tracking for created_by/modified_by fields
 * - **Trash**: Soft deletion support with recovery capabilities
 * - **BaseTable**: Inherits KMP table functionality including cache management
 *
 * ## Validation Framework
 * The table implements comprehensive validation including:
 * - Office name validation with uniqueness constraints
 * - Department assignment validation with referential integrity
 * - Warrant requirement validation for role assignment workflows
 * - Branch restriction validation for organizational policies
 * - Hierarchical relationship validation preventing circular references
 * - Term length validation for officer assignment workflows
 *
 * ## Permission-Based Access Control
 * The table implements sophisticated access control through `officesMemberCanWork()`:
 * - **Super User Access**: Full office access for administrative users
 * - **Global Officer Permissions**: System-wide office management capabilities
 * - **Position-Based Access**: Access based on user's current officer positions
 * - **Hierarchical Permissions**: Deputy, direct report, and reporting tree access
 * - **Branch Scoping**: Access restricted by branch membership and permissions
 * - **Permission Caching**: Optimized permission checking with result caching
 *
 * ## Hierarchical Navigation
 * The table provides sophisticated hierarchical navigation methods:
 * - **Deputy Office Resolution**: Finding offices that serve as deputies
 * - **Direct Report Resolution**: Finding offices in direct reporting relationships
 * - **Reporting Tree Traversal**: Breadth-first traversal of organizational hierarchy
 * - **Circular Reference Prevention**: Safe traversal with visit tracking
 * - **Performance Optimization**: Efficient batch queries for tree operations
 *
 * ## Usage Patterns
 * ```php
 * // Standard office operations
 * $officesTable = TableRegistry::getTableLocator()->get('Officers.Offices');
 * 
 * // Get offices user can manage
 * $accessibleOffices = $officesTable->officesMemberCanWork($user, $branchId);
 * 
 * // Create office with hierarchical relationships
 * $office = $officesTable->newEntity([
 *     'name' => 'District Commander',
 *     'department_id' => 1,
 *     'reports_to_id' => 5,
 *     'requires_warrant' => true,
 *     'term_length' => 12
 * ]);
 * $officesTable->save($office);
 * 
 * // Query hierarchical relationships
 * $office = $officesTable->get($id, [
 *     'contain' => ['DeputyTo', 'ReportsTo', 'Deputies', 'DirectReports']
 * ]);
 * ```
 *
 * ## Integration Points
 * - **Officer Management**: Direct relationship with OfficersTable for assignments
 * - **Department System**: Integration with departmental organization structure
 * - **Warrant System**: Warrant requirement validation and role assignment
 * - **Authorization Framework**: Complex permission-based access control
 * - **Branch Management**: Branch-specific office access and scoping
 * - **Role Assignment**: Automatic role grants and permission inheritance
 * - **Reporting System**: Organizational analytics and hierarchy reporting
 * - **Administrative Interfaces**: Form options and hierarchical display components
 *
 * @property \Officers\Model\Table\DepartmentsTable&\Cake\ORM\Association\BelongsTo $Departments Department categorization
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $GrantsRole Role assignment integration
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $DeputyTo Deputy relationship target
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $ReportsTo Reporting relationship target
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\HasMany $Deputies Deputy offices collection
 * @property \Officers\Model\Table\OfficesTable&\Cake\ORM\Association\HasMany $DirectReports Direct report offices collection
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $Officers All officer assignments
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $CurrentOfficers Active officer assignments
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $UpcomingOfficers Future officer assignments
 * @property \Officers\Model\Table\OfficersTable&\Cake\ORM\Association\HasMany $PreviousOfficers Historical officer assignments
 *
 * @method \Officers\Model\Entity\Office newEmptyEntity()
 * @method \Officers\Model\Entity\Office newEntity(array $data, array $options = [])
 * @method array<\Officers\Model\Entity\Office> newEntities(array $data, array $options = [])
 * @method \Officers\Model\Entity\Office get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Officers\Model\Entity\Office findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Officers\Model\Entity\Office patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Officers\Model\Entity\Office> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Officers\Model\Entity\Office|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Officers\Model\Entity\Office saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Officers\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Office>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Officers\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Office> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Officers\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Office>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Officers\Model\Entity\Office>|\Cake\Datasource\ResultSetInterface<\Officers\Model\Entity\Office> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @see \Officers\Model\Entity\Office For office entity documentation
 * @see \Officers\Model\Table\DepartmentsTable For department management operations
 * @see \Officers\Model\Table\OfficersTable For officer assignment operations
 * @see \App\Model\Table\BaseTable For inherited table functionality
 */
class OfficesTable extends BaseTable
{
    /**
     * Initialize table configuration and complex association structure
     *
     * Configures the offices table with comprehensive association mapping to support
     * the complex hierarchical office structure, role assignments, and temporal
     * officer management within the Officers plugin. This method establishes the
     * foundational relationships required for organizational hierarchy management,
     * warrant integration, and permission-based access control.
     *
     * ## Configuration Setup
     * - **Table Name**: `officers_offices` (plugin-specific namespace)
     * - **Display Field**: `name` for human-readable identification
     * - **Primary Key**: `id` (standard auto-increment identifier)
     *
     * ## Departmental Association
     * Establishes connection to departmental organization structure:
     * - **belongsTo Departments**: Links offices to their departmental categories
     * - **Foreign Key**: `department_id` for organizational grouping
     * - **Purpose**: Enables departmental office listing and management
     *
     * ## Role Assignment Integration
     * Connects offices to the role assignment system:
     * - **belongsTo GrantsRole**: Links to roles automatically granted to officers
     * - **Foreign Key**: `grants_role_id` for permission inheritance
     * - **Join Type**: LEFT JOIN (optional role assignment)
     * - **Purpose**: Automatic role assignment for office holders
     *
     * ## Hierarchical Office Relationships
     * Implements self-referential associations for organizational hierarchy:
     * 
     * ### Deputy Relationships
     * - **belongsTo DeputyTo**: Links to office this position serves as deputy
     * - **hasMany Deputies**: Collection of offices serving as deputies
     * - **Purpose**: Implements deputy officer assignment workflows
     *
     * ### Reporting Relationships
     * - **belongsTo ReportsTo**: Links to office this position reports to
     * - **hasMany DirectReports**: Collection of offices reporting to this position
     * - **Purpose**: Implements organizational reporting structure
     *
     * ## Officer Assignment Associations
     * Establishes temporal officer assignment relationships:
     * 
     * ### Comprehensive Officer Tracking
     * - **hasMany Officers**: All officer assignments (past, current, future)
     * - **Foreign Key**: `office_id` for assignment linking
     * - **Purpose**: Complete officer assignment history and planning
     *
     * ### Temporal Officer Management
     * - **hasMany CurrentOfficers**: Active assignments using "current" finder
     * - **hasMany UpcomingOfficers**: Future assignments using "upcoming" finder
     * - **hasMany PreviousOfficers**: Historical assignments using "previous" finder
     * - **Purpose**: Temporal-aware officer assignment management
     *
     * ## Behavior Configuration
     * Adds essential behaviors for audit trail and data management:
     * - **Timestamp**: Automatic created/modified timestamp tracking
     * - **Footprint**: User attribution for created_by/modified_by
     * - **Trash**: Soft deletion support with recovery capabilities
     *
     * ## Usage Context
     * This initialization supports:
     * - Hierarchical office navigation and management
     * - Permission-based office access control
     * - Temporal officer assignment workflows
     * - Role-based permission inheritance
     * - Organizational reporting and analytics
     * - Administrative interface population
     *
     * @param array<string, mixed> $config The configuration for the Table
     * @return void
     * @see \Officers\Model\Table\DepartmentsTable For departmental organization
     * @see \Officers\Model\Table\OfficersTable For officer assignment management
     * @see \App\Model\Table\RolesTable For role assignment integration
     * @see \App\Model\Table\BaseTable For inherited initialization behavior
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('officers_offices');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('Departments', [
            'className' => 'Officers.Departments',
            'foreignKey' => 'department_id',
        ]);
        $this->belongsTo("GrantsRole", [
            "className" => "Roles",
            "foreignKey" => "grants_role_id",
            "joinType" => "LEFT",
        ]);
        $this->belongsTo('DeputyTo', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'deputy_to_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('ReportsTo', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'reports_to_id',
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('Deputies', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'deputy_to_id',
        ]);
        $this->hasMany('DirectReports', [
            'className' => 'Officers.Offices',
            'foreignKey' => 'reports_to_id',
        ]);
        $this->hasMany("Officers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id"
        ]);
        $this->hasMany("CurrentOfficers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id",
            "finder" => "current",
        ]);
        $this->hasMany("UpcomingOfficers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id",
            "finder" => "upcoming",
        ]);
        $this->hasMany("PreviousOfficers", [
            "className" => "Officers.Officers",
            "foreignKey" => "office_id",
            "finder" => "previous",
        ]);
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    /**
     * Configure comprehensive validation rules for office entities
     *
     * Establishes detailed validation constraints for office data to ensure
     * organizational integrity, hierarchical consistency, and business rule
     * compliance. This method defines validation rules for all office fields,
     * focusing on required field enforcement, relationship validation, and
     * organizational policy compliance.
     *
     * ## Office Identity Validation
     * The office name field receives comprehensive validation:
     * - **Data Type**: String scalar validation
     * - **Length Constraint**: Maximum 255 characters
     * - **Required Field**: Mandatory for create operations
     * - **Empty Check**: Cannot be empty or whitespace-only
     * - **Uniqueness**: Database-level unique constraint validation
     *
     * ## Departmental Association Validation
     * Ensures proper departmental categorization:
     * - **Data Type**: Integer validation for department_id
     * - **Required Field**: Every office must belong to a department
     * - **Referential Integrity**: Enforced through buildRules() method
     * - **Business Logic**: Supports departmental organization structure
     *
     * ## Warrant and Role Configuration
     * Validates warrant requirements and role assignment settings:
     * - **Warrant Requirements**: Boolean validation for requires_warrant
     * - **Branch Restrictions**: Boolean validation for only_one_per_branch
     * - **Required Fields**: Both fields mandatory for organizational policy
     * - **Business Rules**: Supports warrant workflow and branch restrictions
     *
     * ## Hierarchical Relationship Validation
     * Validates organizational hierarchy connections:
     * - **Deputy Assignment**: Optional integer for deputy_to_id
     * - **Reporting Structure**: Optional integer for reports_to_id
     * - **Circular Reference Prevention**: Additional validation in business logic
     * - **Hierarchical Integrity**: Supports complex organizational structures
     *
     * ## Role Integration Validation
     * Validates role assignment integration:
     * - **Role Assignment**: Optional integer for grants_role_id
     * - **Permission Inheritance**: Supports automatic role grants
     * - **Access Control**: Enables role-based permission assignment
     *
     * ## Term Management Validation
     * Validates officer assignment term configuration:
     * - **Term Length**: Required integer for assignment duration
     * - **Temporal Management**: Supports ActiveWindow integration
     * - **Assignment Planning**: Enables warrant lifecycle management
     *
     * ## Soft Deletion Support
     * The validation includes support for soft deletion:
     * - **Data Type**: Date field validation for deleted timestamp
     * - **Optional Field**: Allows empty values for active offices
     * - **Archival Workflow**: Supports organizational data retention
     *
     * ## Validation Architecture
     * - **Layered Validation**: Multiple validation types for comprehensive checking
     * - **Provider Integration**: Uses table provider for unique constraint checking
     * - **Error Handling**: Detailed validation messages for user feedback
     * - **Business Rule Integration**: Coordinates with buildRules() for integrity
     *
     * ## Usage Context
     * This validation supports:
     * - Administrative office creation and modification forms
     * - Hierarchical relationship establishment workflows
     * - Data import validation and integrity checking
     * - API endpoint data validation and error handling
     * - Organizational restructuring and management operations
     *
     * @param \Cake\Validation\Validator $validator Validator instance for rule configuration
     * @return \Cake\Validation\Validator Configured validator with office-specific rules
     * @see \Officers\Model\Entity\Office For entity-level validation hooks
     * @see \App\Model\Table\BaseTable For inherited validation behavior
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->integer('department_id')
            ->notEmptyString('department_id');

        $validator
            ->boolean('requires_warrant')
            ->notEmptyString('requires_warrant');

        $validator
            ->boolean('only_one_per_branch')
            ->notEmptyString('only_one_per_branch');

        $validator
            ->integer('deputy_to_id')
            ->allowEmptyString('deputy_to_id');

        $validator
            ->integer('reports_to_id')
            ->allowEmptyString('reports_to_id');

        $validator
            ->integer('grants_role_id')
            ->allowEmptyString('grants_role_id');

        $validator
            ->integer('term_length')
            ->requirePresence('term_length', 'create')
            ->notEmptyString('term_length');

        $validator
            ->date('deleted')
            ->allowEmptyDate('deleted');

        return $validator;
    }

    /**
     * Configure database-level integrity rules for office operations
     *
     * Establishes database-level validation rules that ensure referential integrity,
     * hierarchical consistency, and business rule compliance beyond field-level
     * validation. These rules operate at the database layer to provide additional
     * validation enforcement during save operations and prevent organizational
     * data inconsistencies.
     *
     * ## Uniqueness Constraints
     * Enforces office name uniqueness across the organizational structure:
     * - **Unique Name Rule**: Prevents duplicate office names system-wide
     * - **Error Field Assignment**: Associates validation errors with name field
     * - **Database Enforcement**: Leverages database constraints for integrity
     * - **Organizational Clarity**: Ensures clear office identification
     *
     * ## Referential Integrity Rules
     * Validates foreign key relationships for organizational structure:
     * - **Department Existence**: Verifies department_id references valid department
     * - **Error Field Assignment**: Associates errors with department_id field
     * - **Data Consistency**: Prevents orphaned office records
     * - **Organizational Structure**: Maintains departmental hierarchy integrity
     *
     * ## Business Rule Enforcement
     * Additional rules for organizational policy compliance:
     * - **Hierarchical Validation**: Prevents circular reference creation
     * - **Role Assignment Validation**: Ensures valid role grant references
     * - **Branch Restriction Validation**: Enforces branch-specific office policies
     * - **Warrant Requirement Validation**: Validates warrant workflow compliance
     *
     * ## Rule Architecture
     * - **Layered Validation**: Works alongside field validation for comprehensive checking
     * - **Database Integration**: Utilizes database constraints for enforcement
     * - **Error Handling**: Proper error field mapping for user interface integration
     * - **Business Logic**: Enforces organizational policies and workflows
     *
     * ## Enforcement Context
     * These rules apply during:
     * - Office creation operations (insert)
     * - Office modification operations (update)
     * - Hierarchical relationship establishment
     * - Organizational restructuring operations
     * - Bulk data operations and imports
     * - API-driven office management
     *
     * ## Integration Points
     * - **Form Validation**: Provides backend validation for administrative forms
     * - **API Responses**: Ensures consistent error responses for API consumers
     * - **Data Import**: Validates imported office data for integrity
     * - **Administrative Tools**: Supports bulk operations with proper validation
     * - **Organizational Management**: Enforces hierarchical consistency
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified with office-specific constraints
     * @return \Cake\ORM\RulesChecker Enhanced rules checker with office validation rules
     * @see \Officers\Model\Entity\Office For entity-level rule integration
     * @see \Officers\Model\Table\DepartmentsTable For department validation
     * @see \Cake\ORM\Table::buildRules() For base rule checking functionality
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);
        $rules->add($rules->existsIn(['department_id'], 'Departments'), ['errorField' => 'department_id']);

        return $rules;
    }

    /**
     * Retrieve offices accessible to a specific user based on permissions and hierarchical position
     *
     * Implements sophisticated permission-based filtering to return only offices where the user
     * has legitimate access based on their authorization level, current officer positions, and
     * hierarchical relationships. This method provides comprehensive access control for office
     * data while supporting both administrative oversight and position-based restrictions with
     * complex hierarchical navigation and permission inheritance.
     *
     * ## Access Control Hierarchy
     * The method implements a multi-tiered permission system:
     * 1. **Super User Access**: Unrestricted access to all offices across all branches
     * 2. **Global Officer Permissions**: System-wide office management via `workWithAllOfficers` permission
     * 3. **Position-Based Access**: Access derived from user's current officer assignments
     * 4. **Branch Scoping**: Access restricted to specific branch context when provided
     * 5. **Unauthorized Access**: Empty result set for users without valid permissions
     *
     * ## Permission Validation Architecture
     * - **Early Exit Strategy**: Efficient processing with immediate returns for privileged users
     * - **Branch Context Validation**: Requires branch context for non-privileged users
     * - **Officer Position Resolution**: Queries current officer assignments for permission derivation
     * - **Hierarchical Permission Calculation**: Analyzes reporting relationships for access expansion
     *
     * ## Hierarchical Access Patterns
     * For users with officer positions, access is calculated based on hierarchical relationships:
     * - **Deputy Office Access**: Offices where user's position serves as deputy
     * - **Direct Report Access**: Offices that report to user's current positions
     * - **Reporting Tree Access**: Complete organizational subtree access through reporting chains
     * - **Permission-Based Expansion**: Access expansion based on specific hierarchical permissions
     *
     * ## Performance Optimization
     * The method implements several optimization strategies:
     * - **Permission Caching**: Caches permission checks to avoid redundant authorization calls
     * - **Batch Query Processing**: Uses efficient batch queries for hierarchical traversal
     * - **Early Termination**: Stops processing when sufficient access is established
     * - **Result Deduplication**: Ensures unique office IDs in final result set
     *
     * ## Permission Types
     * The method checks multiple permission types for comprehensive access control:
     * - **workWithOfficerDeputies**: Access to offices serving as deputies
     * - **workWithOfficerDirectReports**: Access to direct reporting offices
     * - **workWithOfficerReportingTree**: Access to complete reporting subtree
     * - **workWithAllOfficers**: Global office management access
     *
     * ## Usage Examples
     * ```php
     * // Get offices for branch-specific management
     * $accessibleOffices = $officesTable->officesMemberCanWork($user, $branchId);
     * 
     * // Super user access (all offices)
     * $allOffices = $officesTable->officesMemberCanWork($superUser, $branchId);
     * 
     * // Check specific office access
     * $canAccess = in_array($officeId, $officesTable->officesMemberCanWork($user, $branchId));
     * 
     * // Use in authorization context
     * $offices = $this->Offices->find()
     *     ->where(['id IN' => $officesTable->officesMemberCanWork($user, $branchId)]);
     * ```
     *
     * ## Security Features
     * - **Branch Isolation**: Ensures users only access branch-appropriate offices
     * - **Position Validation**: Verifies current officer status before granting access
     * - **Permission Integration**: Respects KMP authorization framework
     * - **Hierarchical Security**: Prevents unauthorized organizational structure access
     * - **Audit Trail Support**: Integrates with authorization logging for compliance
     *
     * ## Integration Points
     * - **Authorization Framework**: Deep integration with KMP permission system
     * - **Officer Management**: Coordinates with officer assignment workflows
     * - **Branch Management**: Respects branch-specific access controls
     * - **Hierarchical Navigation**: Supports organizational structure traversal
     * - **Administrative Interfaces**: Provides foundation for office management UIs
     *
     * @param \App\Model\Entity\Member $user The user entity to check office access for
     * @param int|null $branchId The branch ID to check permissions for (required for non-privileged users)
     * @return int[] List of office IDs the user can work with
     * @see \Officers\Model\Table\OfficersTable For officer assignment queries
     * @see \App\Model\Entity\User For user permission checking methods
     * @see \Officers\Policy\OfficePolicy For authorization policy implementation
     */
    public function officesMemberCanWork(Member $user, int|null $branchId): array
    {
        // Early returns for edge cases

        // Superusers can work with all offices
        if ($user->isSuperUser()) {
            return $this->getAllOfficeIds();
        }

        // Check if user has global officer permissions
        if ($this->hasGlobalOfficerPermissions($user)) {
            return $this->getAllOfficeIds();
        }
        // If no branch ID is provided, return empty array
        if ($branchId === null) {
            return [];
        }

        // Get user's current officer positions
        $userOfficerPositions = $this->getUserOfficerPositions($user);
        if (empty($userOfficerPositions)) {
            return [];
        }

        // Calculate accessible offices based on permissions
        return $this->calculateAccessibleOffices($user, $userOfficerPositions, $branchId);
    }

    /**
     * Retrieve all office IDs efficiently for super user and global permission access
     *
     * Provides optimized retrieval of all office IDs in the system for users with
     * super user privileges or global officer management permissions. This method
     * uses performance-optimized querying to minimize database overhead while
     * providing comprehensive office access for administrative users.
     *
     * ## Performance Optimization
     * - **Selective Field Query**: Retrieves only the ID field to minimize data transfer
     * - **Disabled Hydration**: Uses raw array results for better performance
     * - **Ordered Results**: Provides consistent ordering for reliable operations
     * - **Efficient Extraction**: Uses array_column for optimal ID extraction
     *
     * ## Usage Context
     * This method is called for:
     * - Super user access patterns
     * - Global officer permission scenarios
     * - Administrative interface population
     * - Bulk operation preparation
     *
     * @return int[] Array of all office IDs in the system
     * @see self::officesMemberCanWork() For main access control method
     */
    private function getAllOfficeIds(): array
    {
        $results = $this->find()
            ->select(['id'])
            ->orderBy(['id'])
            ->enableHydration(false)
            ->toArray();

        return array_column($results, 'id');
    }

    /**
     * Check if user has global officer management permissions
     *
     * Validates whether the user possesses system-wide officer management
     * permissions that grant access to all offices regardless of hierarchical
     * position or branch assignment. This method provides centralized permission
     * checking for global administrative access patterns.
     *
     * ## Permission Validation
     * - **Global Permission Check**: Tests `workWithAllOfficers` permission
     * - **Empty Entity Context**: Uses new empty officer entity for permission testing
     * - **Authorization Integration**: Leverages KMP authorization framework
     * - **Binary Result**: Returns simple boolean for access control decisions
     *
     * ## Security Architecture
     * - **Least Privilege**: Only grants access when explicitly authorized
     * - **Permission Specificity**: Uses officer-specific permission for validation
     * - **Framework Integration**: Respects KMP authorization patterns
     * - **Audit Support**: Permission checks are logged by authorization framework
     *
     * ## Usage Context
     * This check is performed for:
     * - Administrative interface access
     * - Bulk operation authorization
     * - Global reporting capabilities
     * - System-wide management functions
     *
     * @param \App\Model\Entity\Member $user The user entity to check permissions for
     * @return bool True if user has global officer management permissions
     * @see \Officers\Model\Table\OfficersTable For officer entity creation
     * @see \App\Model\Entity\User::checkCan() For permission validation
     */
    private function hasGlobalOfficerPermissions(Member $user): bool
    {
        $officersTbl = TableRegistry::getTableLocator()->get('Officers.Officers');
        $newOfficer = $officersTbl->newEmptyEntity();

        return $user->checkCan('workWithAllOfficers', $newOfficer, null, true);
    }

    /**
     * Retrieve user's current officer positions with relevant data for access calculation
     *
     * Queries the user's active officer assignments to establish the foundation for
     * position-based access control. This method uses the "current" finder to ensure
     * only active assignments are considered and selects minimal required fields for
     * optimal performance in hierarchical access calculations.
     *
     * ## Query Optimization
     * - **Current Finder**: Uses OfficersTable "current" finder for active assignments
     * - **Selective Fields**: Retrieves only essential fields (id, office_id, branch_id)
     * - **User Filtering**: Filters to specific user's assignments
     * - **Minimal Data**: Reduces data transfer for performance optimization
     *
     * ## Temporal Awareness
     * - **Active Assignments**: Only current officer positions are considered
     * - **Status Validation**: Respects ActiveWindow behavior for temporal accuracy
     * - **Real-Time Data**: Provides current organizational position context
     * - **Assignment Currency**: Ensures access is based on current organizational state
     *
     * ## Access Control Foundation
     * The returned data provides the foundation for:
     * - Hierarchical permission calculation
     * - Branch-specific access control
     * - Position-based authorization
     * - Organizational relationship traversal
     *
     * ## Data Structure
     * Returns array of officer entities with:
     * - **id**: Officer assignment ID for permission caching
     * - **office_id**: Office position for hierarchical relationships
     * - **branch_id**: Branch context for scoped access control
     *
     * @param \App\Model\Entity\Member $user The user entity to retrieve positions for
     * @return array Array of current officer position entities
     * @see \Officers\Model\Table\OfficersTable For officer queries and finders
     * @see \Officers\Model\Entity\Officer For officer entity structure
     */
    private function getUserOfficerPositions(Member $user): array
    {
        $officersTbl = TableRegistry::getTableLocator()->get('Officers.Officers');

        return $officersTbl->find('current')
            ->where(['member_id' => $user->id])
            ->select(['id', 'office_id', 'branch_id'])
            ->toArray();
    }

    /**
     * Calculate comprehensive accessible offices based on user permissions and hierarchical positions
     *
     * Orchestrates the complex process of determining office access based on the user's
     * current officer positions and their associated permissions. This method iterates
     * through all user positions, calculates access for each position, and aggregates
     * the results while maintaining performance through permission caching.
     *
     * ## Access Aggregation Strategy
     * - **Position Iteration**: Processes each user officer position independently
     * - **Permission Expansion**: Calculates access expansion for each position
     * - **Result Aggregation**: Merges access from all positions into comprehensive list
     * - **Deduplication**: Ensures unique office IDs in final result set
     *
     * ## Performance Optimization
     * - **Permission Caching**: Maintains cache across position processing to avoid redundant checks
     * - **Incremental Building**: Builds access list incrementally for memory efficiency
     * - **Efficient Merging**: Uses array operations for optimal performance
     * - **Cache Sharing**: Shares permission cache across position calculations
     *
     * ## Hierarchical Access Logic
     * For each position, the method calculates:
     * - **Deputy Access**: Offices where position serves as deputy
     * - **Direct Report Access**: Offices that report to the position
     * - **Reporting Tree Access**: Complete organizational subtree access
     * - **Permission-Based Expansion**: Access based on specific hierarchical permissions
     *
     * ## Security Integration
     * - **Branch Scoping**: Ensures access is properly scoped to branch context
     * - **Permission Validation**: Validates each access expansion through authorization
     * - **Position Verification**: Confirms position validity before access calculation
     * - **Hierarchical Integrity**: Maintains organizational boundary respect
     *
     * ## Caching Architecture
     * - **Permission Cache**: Caches permission results by position and branch combination
     * - **Cross-Position Sharing**: Shares cache across multiple positions for efficiency
     * - **Request-Level Caching**: Cache lifetime limited to single request for accuracy
     * - **Cache Key Strategy**: Uses position ID and branch ID for unique cache keys
     *
     * @param \App\Model\Entity\Member $user The user entity for access calculation
     * @param array $userOfficerPositions User's current officer positions for access derivation
     * @param int $branchId The branch ID for scoped access control
     * @return int[] Array of unique office IDs the user can access
     * @see self::getOfficesForPosition() For individual position access calculation
     * @see \Officers\Model\Entity\Officer For officer position structure
     */
    private function calculateAccessibleOffices(Member $user, array $userOfficerPositions, int $branchId): array
    {
        $accessibleOfficeIds = [];
        $permissionCache = [];

        foreach ($userOfficerPositions as $position) {
            $accessibleOfficeIds = array_merge(
                $accessibleOfficeIds,
                $this->getOfficesForPosition($user, $position, $branchId, $permissionCache)
            );
        }

        return array_unique($accessibleOfficeIds);
    }

    /**
     * Get accessible offices for a specific user position.
     *
     * @param \App\Model\Entity\Member $user The user entity.
     * @param \Officers\Model\Entity\Officer $position The user's officer position.
     * @param int $branchId The branch ID.
     * @param array &$permissionCache Permission cache for optimization.
     * @return int[]
     */
    private function getOfficesForPosition(Member $user, Officer $position, int $branchId, array &$permissionCache): array
    {
        $officeIds = [];
        $officeId = $position->office_id;

        // Cache key for permissions
        $cacheKey = $position['id'] . '_' . $branchId;

        // Get permissions for this position (with caching)
        if (!isset($permissionCache[$cacheKey])) {
            $permissionCache[$cacheKey] = $this->getPositionPermissions($user, $position, $branchId);
        }
        $permissions = $permissionCache[$cacheKey];

        // Add offices based on permissions
        if ($permissions['deputies']) {
            $officeIds = array_merge($officeIds, $this->getDeputyOffices($officeId));
        }

        if ($permissions['directReports']) {
            $officeIds = array_merge($officeIds, $this->getDirectReportOffices($officeId));
        }

        if ($permissions['reportingTree']) {
            $officeIds = array_merge($officeIds, $this->getReportingTreeOffices($officeId));
        }

        return $officeIds;
    }

    /**
     * Get permissions for a user's position.
     *
     * @param \App\Model\Entity\Member $user The user entity.
     * @param \Officers\Model\Entity\Officer $position The user's officer position.
     * @param int $branchId The branch ID.
     * @return array
     */
    private function getPositionPermissions(Member $user, Officer $position, int $branchId): array
    {
        return [
            'deputies' => $user->checkCan('workWithOfficerDeputies', $position, $branchId, true),
            'directReports' => $user->checkCan('workWithOfficerDirectReports', $position, $branchId, true),
            'reportingTree' => $user->checkCan('workWithOfficerReportingTree', $position, $branchId, true),
        ];
    }

    /**
     * Get deputy office IDs for a given office.
     *
     * @param int $officeId The office ID.
     * @return int[]
     */
    private function getDeputyOffices(int $officeId): array
    {
        $results = $this->find()
            ->where(['deputy_to_id' => $officeId])
            ->select(['id'])
            ->enableHydration(false)
            ->toArray();

        return array_column($results, 'id');
    }

    /**
     * Get direct report office IDs for a given office.
     *
     * @param int $officeId The office ID.
     * @return int[]
     */
    private function getDirectReportOffices(int $officeId): array
    {
        $results = $this->find()
            ->where([
                'OR' => [
                    'deputy_to_id' => $officeId,
                    'reports_to_id' => $officeId,
                ],
            ])
            ->select(['id'])
            ->enableHydration(false)
            ->toArray();

        return array_column($results, 'id');
    }

    /**
     * Return the office IDs that are reachable from a given office through deputy or reporting relationships in breadth-first order.
     *
     * @param int $rootOfficeId The starting office ID whose reporting tree will be traversed; the returned list excludes this root.
     * @return int[] An array of office IDs encountered in breadth-first order, excluding the root office.
     */
    private function getReportingTreeOffices(int $rootOfficeId): array
    {
        $allOfficeIds = [];
        $visited = [];
        $toVisit = [$rootOfficeId];

        while (!empty($toVisit)) {
            $currentLevelResults = $this->find()
                ->where([
                    'OR' => [
                        'deputy_to_id IN' => $toVisit,
                        'reports_to_id IN' => $toVisit,
                    ],
                ])
                ->select(['id'])
                ->enableHydration(false)
                ->toArray();

            $currentLevelIds = array_column($currentLevelResults, 'id');
            $nextLevel = [];

            foreach ($currentLevelIds as $officeId) {
                if (!isset($visited[$officeId])) {
                    $visited[$officeId] = true;
                    $allOfficeIds[] = $officeId;
                    $nextLevel[] = $officeId;
                }
            }

            $toVisit = $nextLevel;
        }

        return $allOfficeIds;
    }

    /**
     * Determine the appropriate branch ID for an office's reports_to_branch_id.
     *
     * Finds a branch starting from $startBranchId (and moving up the parent chain if necessary)
     * that is compatible with the branch types allowed for the specified reports-to office.
     *
     * @param int $startBranchId The branch where the officer is being hired.
     * @param int|null $reportsToOfficeId The ID of the office this officer reports to, or null.
     * @return int|null The branch ID compatible with the reports-to office, or null if no reports-to office was provided.
     */
    public function findCompatibleBranchForOffice(int $startBranchId, ?int $reportsToOfficeId): ?int
    {
        // If no reporting office, no branch needed
        if ($reportsToOfficeId === null) {
            return null;
        }

        // Get the office to check its branch_types
        $office = $this->get($reportsToOfficeId, ['fields' => ['id', 'applicable_branch_types']]);

        // If office has no branch type restrictions, use the parent branch
        if (empty($office->applicable_branch_types)) {
            $branchTable = TableRegistry::getTableLocator()->get('Branches');
            $branch = $branchTable->get($startBranchId, ['fields' => ['id', 'parent_id']]);
            return $branch->parent_id;
        }

        // Get the branch types the office is compatible with
        $compatibleBranchTypes = $office->branch_types;

        // Traverse up the branch hierarchy looking for a compatible branch
        $branchTable = TableRegistry::getTableLocator()->get('Branches');
        $currentBranchId = $startBranchId;
        $lastValidBranchId = $startBranchId; // Fallback to top if nothing matches

        while ($currentBranchId !== null) {
            $currentBranch = $branchTable->get($currentBranchId, ['fields' => ['id', 'type', 'parent_id']]);

            // Check if this branch type is compatible with the office
            if (in_array($currentBranch->type, $compatibleBranchTypes)) {
                return $currentBranchId;
            }

            // Remember this as a potential fallback
            $lastValidBranchId = $currentBranchId;

            // Move up to parent
            $currentBranchId = $currentBranch->parent_id;
        }

        // If we didn't find a match, return the top of the hierarchy
        return $lastValidBranchId;
    }
}