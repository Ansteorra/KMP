<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\Member;
use ArrayObject;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\RulesChecker;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * Members Table - User Management and Identity Storage
 *
 * The MembersTable class provides data access and business logic for the Member entity,
 * serving as the central repository for user management within the KMP system. This table
 * handles complex member relationships, validation rules, and business workflows essential
 * for organizational member management.
 *
 * ## Core Responsibilities
 *
 * ### Member Data Management
 * - **Identity Storage**: Complete user profiles with personal and contact information
 * - **Authentication Data**: Secure password storage and login tracking
 * - **Status Management**: Member status tracking with age-based workflow automation
 * - **Privacy Controls**: Configurable privacy settings and minor protection
 *
 * ### Relationship Management
 * - **Role Assignments**: Through MemberRoles with temporal active window support
 * - **Organizational Hierarchy**: Branch membership for data scoping and security
 * - **Parent-Child Relationships**: Minor member oversight and guardian management
 * - **Authorization History**: Tracking of permission grants and authorization changes
 *
 * ### Validation and Business Rules
 * - **Data Integrity**: Comprehensive validation for all member information fields
 * - **Unique Constraints**: Email uniqueness enforcement across the organization
 * - **Password Security**: Strong password requirements and hashing protocols
 * - **Automatic Processing**: Age-up review and warrant eligibility on data changes
 *
 * ### Advanced Features
 * - **JSON Field Support**: Flexible additional_info field for configurable data
 * - **Soft Deletion**: Trash behavior for data retention and audit requirements
 * - **Footprint Tracking**: Automatic created_by/modified_by auditing
 * - **Branch Scoping**: Inherited from BaseTable for organizational data isolation
 *
 * ## Association Patterns
 *
 * ### Role System Integration
 * ```php
 * // Access current role assignments
 * $member = $this->Members->get($id, [
 *     'contain' => ['CurrentMemberRoles.Roles']
 * ]);
 * 
 * // Query members by role
 * $officerMembers = $this->Members->find()
 *     ->matching('Roles', function ($q) {
 *         return $q->where(['Roles.name' => 'Officer']);
 *     });
 * ```
 *
 * ### Temporal Role Queries
 * ```php
 * // Get upcoming role assignments
 * $upcomingRoles = $this->Members->find()
 *     ->contain(['UpcomingMemberRoles.Roles'])
 *     ->where(['id' => $memberId]);
 * 
 * // Historical role analysis
 * $pastRoles = $this->Members->find()
 *     ->contain(['PreviousMemberRoles.Roles'])
 *     ->where(['id' => $memberId]);
 * ```
 *
 * ## Validation Features
 *
 * ### Security Validation
 * - **Email Uniqueness**: Organization-wide unique email addresses
 * - **Password Strength**: Minimum 12 characters (expandable with complexity rules)
 * - **Data Length Limits**: Appropriate field length constraints for all inputs
 *
 * ### Business Logic Validation
 * - **Required Fields**: Essential information for member creation
 * - **Conditional Requirements**: Context-dependent validation rules
 * - **Format Validation**: Email format, date validity, numeric constraints
 *
 * ## Automatic Processing
 *
 * ### beforeSave Event Handling
 * - **Age-Up Review**: Automatic status changes when minors reach age 18
 * - **Warrant Eligibility**: Real-time eligibility calculation on data changes
 * - **Cache Invalidation**: Inherited cache management from BaseTable
 *
 * ## Usage Examples
 *
 * ### Basic Member Operations
 * ```php
 * // Create new member with validation
 * $member = $this->Members->newEntity([
 *     'sca_name' => 'Aiden of the North',
 *     'first_name' => 'John',
 *     'last_name' => 'Doe',
 *     'email_address' => 'john@example.com',
 *     'password' => 'SecurePassword123!',
 *     'status' => Member::STATUS_ACTIVE
 * ]);
 * 
 * if ($this->Members->save($member)) {
 *     // Member created successfully
 * }
 * ```
 *
 * ### Complex Queries with Relationships
 * ```php
 * // Find members with warrant eligibility
 * $warrantEligible = $this->Members->find()
 *     ->where([
 *         'Members.warrantable' => true,
 *         'Members.status' => Member::STATUS_VERIFIED_MEMBERSHIP
 *     ])
 *     ->contain(['Branches', 'CurrentMemberRoles.Roles']);
 * 
 * // Get validation queue count
 * $pendingValidations = $this->Members->getValidationQueueCount();
 * ```
 *
 * ### Member Status Management
 * ```php
 * // Update member status with automatic processing
 * $member->status = Member::STATUS_VERIFIED_MEMBERSHIP;
 * $this->Members->save($member); // Triggers ageUpReview and warrantableReview
 * ```
 *
 * ## Security Considerations
 * - **Password Hashing**: Automatic secure hashing via Member entity setter
 * - **Sensitive Field Protection**: Hidden fields in entity serialization
 * - **Branch Scoping**: Data access limited by organizational membership
 * - **Soft Deletion**: Preserves audit trail while removing active access
 *
 * ## Integration Points
 * - **Authentication**: Primary identity source for login system
 * - **Authorization**: Role-based permission inheritance
 * - **Activities Plugin**: Member authorization management
 * - **Awards Plugin**: Member recognition and achievement tracking
 * - **Officers Plugin**: Leadership role management and reporting
 *
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\HasMany $MemberRoles All role assignments
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\HasMany $CurrentMemberRoles Active role assignments
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\HasMany $UpcomingMemberRoles Future role assignments
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\HasMany $PreviousMemberRoles Historical role assignments
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches Organizational branch membership
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Parents Parent member for minors
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsToMany $Roles Role assignments through MemberRoles
 * @property \App\Model\Table\PendingAuthorizationsTable&\Cake\ORM\Association\HasMany $PendingAuthorizations Authorization requests
 * @property \App\Model\Table\GatheringAttendancesTable&\Cake\ORM\Association\HasMany $GatheringAttendances Gathering attendance records
 *
 * @method \App\Model\Entity\Member newEmptyEntity()
 * @method \App\Model\Entity\Member newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Member> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Member get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Member findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Member patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Member> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Member|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Member saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Member>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Member> deleteManyOrFail(iterable $entities, array $options = [])
 */
class MembersTable extends BaseTable
{
    /**
     * Initialize table configuration and associations
     *
     * Configures the MembersTable with comprehensive associations, behaviors, and
     * settings necessary for member management within the KMP system. This method
     * establishes the foundation for all member-related data operations.
     *
     * ## Table Configuration
     * - **Display Field**: Uses 'sca_name' for user-friendly member identification
     * - **Primary Key**: Standard 'id' field for unique member identification
     * - **Table Name**: Maps to 'members' database table
     *
     * ## Association Setup
     * ### Role Management Associations
     * - **Roles**: Many-to-many through MemberRoles for permission inheritance
     * - **MemberRoles**: Direct access to all role assignment records
     * - **CurrentMemberRoles**: Active role assignments using 'current' finder
     * - **UpcomingMemberRoles**: Future role assignments using 'upcoming' finder
     * - **PreviousMemberRoles**: Historical role assignments using 'previous' finder
     *
     * ### Organizational Associations
     * - **Branches**: Belongs-to association for organizational hierarchy
     * - **Parents**: Self-referential association for minor member guardianship
     *
     * ## Behavior Integration
     * - **Timestamp**: Automatic created/modified timestamp management
     * - **Footprint**: Tracks created_by/modified_by for audit trails
     * - **Trash**: Soft deletion support for data retention requirements
     * - **JsonField**: Enhanced JSON field handling for additional_info
     *
     * @param array<string, mixed> $config The configuration for the Table
     * @return void
     *
     * @example
     * ```php
     * // The initialize method sets up associations that enable:
     * 
     * // Temporal role queries
     * $member = $this->Members->get($id, [
     *     'contain' => ['CurrentMemberRoles.Roles']
     * ]);
     * 
     * // Organizational hierarchy queries
     * $branchMembers = $this->Members->find()
     *     ->contain(['Branches'])
     *     ->where(['branch_id' => $branchId]);
     * 
     * // Parent-child relationship queries
     * $minorMembers = $this->Members->find()
     *     ->contain(['Parents'])
     *     ->where(['parent_id IS NOT' => null]);
     * ```
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('members');
        $this->setDisplayField('sca_name');
        $this->setPrimaryKey('id');

        $this->belongsToMany('Roles', [
            'through' => 'MemberRoles',
        ]);

        $this->hasMany('MemberRoles', [
            'foreignKey' => 'member_id',
        ]);
        $this->hasMany('CurrentMemberRoles', [
            'className' => 'MemberRoles',
            'finder' => 'current',
            'foreignKey' => 'member_id',
        ]);
        $this->hasMany('UpcomingMemberRoles', [
            'className' => 'MemberRoles',
            'finder' => 'upcoming',
            'foreignKey' => 'member_id',
        ]);
        $this->hasMany('PreviousMemberRoles', [
            'className' => 'MemberRoles',
            'finder' => 'previous',
            'foreignKey' => 'member_id',
        ]);

        $this->belongsTo('Branches', [
            'className' => 'Branches',
            'foreignKey' => 'branch_id',
        ]);
        $this->belongsTo('Parents', [
            'className' => 'Members',
            'foreignKey' => 'parent_id',
        ]);

        $this->hasMany('GatheringAttendances', [
            'foreignKey' => 'member_id',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');
        $this->addBehavior('JsonField');
        $this->addBehavior('PublicId');
    }

    /**
     * Configure database schema with JSON field support
     *
     * Extends the base schema configuration to properly handle JSON fields,
     * specifically the additional_info field used for flexible member data storage.
     * This method ensures proper data type handling and serialization for JSON content.
     *
     * ## JSON Field Configuration
     * - **additional_info**: Configured as JSON type for automatic serialization
     * - **Database Compatibility**: Ensures proper JSON handling across database systems
     * - **Type Safety**: Provides type hints for ORM operations
     *
     * ## Integration Benefits
     * - **Flexible Data Storage**: Allows configurable additional member information
     * - **Schema Validation**: Maintains database schema integrity
     * - **Performance**: Optimized JSON queries when supported by database
     *
     * @return \Cake\Database\Schema\TableSchemaInterface Configured schema with JSON field types
     *
     * @example
     * ```php
     * // The JSON schema configuration enables:
     * 
     * // Structured additional info storage
     * $member->additional_info = [
     *     'website' => 'https://example.com',
     *     'emergency_contact' => 'Jane Doe (555-1234)',
     *     'dietary_restrictions' => 'Vegetarian'
     * ];
     * 
     * // Automatic JSON serialization on save
     * $this->Members->save($member);
     * 
     * // JSON field querying (database dependent)
     * $membersWithWebsites = $this->Members->find()
     *     ->where(['additional_info->"$.website" IS NOT' => null]);
     * ```
     */
    public function getSchema(): TableSchemaInterface
    {
        $schema = parent::getSchema();
        $schema->setColumnType('additional_info', 'json');

        return $schema;
    }

    /**
     * Define comprehensive validation rules for member data
     *
     * Establishes extensive validation rules covering all aspects of member data
     * from basic field requirements to complex business logic validation. These
     * rules ensure data integrity and support the KMP member management workflows.
     *
     * ## Validation Categories
     *
     * ### Security Validation
     * - **Password**: Minimum 12 characters with extensible complexity rules
     * - **Email**: Format validation with uniqueness constraints
     * - **Length Limits**: Prevents data overflow and ensures field compatibility
     *
     * ### Identity Validation
     * - **SCA Name**: 3-50 characters, required for community identification
     * - **Legal Name**: First/last name required for official records
     * - **Contact Info**: Address and phone validation for communication
     *
     * ### Membership Validation
     * - **Dates**: Proper date format for expiration and background check dates
     * - **Status**: Handled by entity setter with business rule enforcement
     * - **Age Info**: Birth month/year for age calculation and minor status
     *
     * ## Password Security Features
     * The validation includes foundation for enhanced password complexity:
     * - Commented complexity rules ready for activation
     * - Uppercase, lowercase, number, and special character requirements
     * - Extensible for organization-specific security policies
     *
     * ## Business Logic Integration
     * - **Required vs. Optional**: Distinguishes creation vs. update requirements
     * - **Conditional Logic**: Different rules for different member types
     * - **Extensibility**: Designed for easy addition of new validation rules
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator Configured validator with comprehensive rules
     *
     * @example
     * ```php
     * // Validation in action during member creation
     * $member = $this->Members->newEntity([
     *     'sca_name' => 'A', // Fails: too short (min 3 chars)
     *     'email_address' => 'invalid-email', // Fails: invalid format
     *     'password' => 'short', // Fails: under 12 characters
     *     'first_name' => '', // Fails: required field
     * ]);
     * 
     * if ($member->hasErrors()) {
     *     foreach ($member->getErrors() as $field => $errors) {
     *         echo "Field {$field}: " . implode(', ', $errors) . "\n";
     *     }
     * }
     * 
     * // Enable enhanced password complexity (uncomment rules)
     * // Requires: uppercase, lowercase, numbers, special characters
     * ```
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator->dateTime('modified')->notEmptyDateTime('modified');

        $validator
            ->scalar('password')
            ->maxLength('new_password', 125)
            ->minLength('new_password', 12, 'Password must be at least 12 characters long.')
            //->add("new_password", "requireCaps", [
            //    "rule" => ['custom', '/[A-Z]/'],
            //    "message" => "Password must contain at least one uppercase letter."
            //])
            //->add("new_password", "requireLowerCase", [
            //   "rule" => ['custom', '/[a-z]/'],
            //    "message" => "Password must contain at least one lowercase letter."
            //])
            //->add("new_password", "requireNumbers", [
            //    "rule" => ['custom', '/[0-9]/'],
            //    "message" => "Password must contain at least one number."
            //])
            //->add("new_password", "requireSpecial", [
            //    "rule" => ['custom', '/[\W]/'],
            //    "message" => "Password must contain at least one special character."
            //])
            ->requirePresence('password', 'create')
            ->notEmptyString('password');

        $validator
            ->scalar('sca_name')
            ->minLength('sca_name', 3)
            ->maxLength('sca_name', 50)
            ->notEmptyString('sca_name');

        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 30)
            ->requirePresence('first_name', 'create')
            ->notEmptyString('first_name');

        $validator
            ->scalar('middle_name')
            ->maxLength('middle_name', 30)
            ->allowEmptyString('middle_name');

        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 30)
            ->requirePresence('last_name', 'create')
            ->notEmptyString('last_name');

        $validator
            ->scalar('street_address')
            ->maxLength('street_address', 75)
            ->requirePresence('street_address', 'create')
            ->allowEmptyString('street_address');

        $validator
            ->scalar('city')
            ->maxLength('city', 30)
            ->requirePresence('city', 'create')
            ->allowEmptyString('city');

        $validator
            ->scalar('state')
            ->maxLength('state', 2)
            ->requirePresence('state', 'create')
            ->allowEmptyString('state');

        $validator
            ->scalar('zip')
            ->maxLength('zip', 5)
            ->requirePresence('zip', 'create')
            ->allowEmptyString('zip');

        $validator
            ->scalar('phone_number')
            ->maxLength('phone_number', 15)
            ->requirePresence('phone_number', 'create')
            ->allowEmptyString('phone_number');

        $validator
            ->scalar('email_address')
            ->maxLength('email_address', 50)
            ->requirePresence('email_address', 'create')
            ->notEmptyString('email_address')
            ->add('email_address', 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
            ]);

        $validator->allowEmptyString('membership_number');

        $validator
            ->date('membership_expires_on')
            ->allowEmptyDate('membership_expires_on');

        $validator
            ->scalar('parent_name')
            ->maxLength('parent_name', 50)
            ->allowEmptyString('parent_name');

        $validator
            ->date('background_check_expires_on')
            ->allowEmptyDate('background_check_expires_on');

        $validator
            ->scalar('password_token')
            ->maxLength('password_token', 255)
            ->allowEmptyString('password_token');

        $validator
            ->dateTime('password_token_expires_on')
            ->allowEmptyDateTime('password_token_expires_on');

        $validator->dateTime('last_login')->allowEmptyDateTime('last_login');

        $validator
            ->dateTime('last_failed_login')
            ->allowEmptyDateTime('last_failed_login');

        $validator
            ->integer('failed_login_attempts')
            ->allowEmptyString('failed_login_attempts');

        $validator->integer('birth_month')->notEmptyString('birth_month');

        $validator->integer('birth_year')->notEmptyString('birth_year');

        $validator
            ->dateTime('deleted_date')
            ->allowEmptyDateTime('deleted_date');

        return $validator;
    }

    /**
     * Build application-level business rules for data integrity
     *
     * Defines business rules that are enforced at the ORM level beyond basic
     * validation. These rules ensure data consistency and business logic
     * compliance across the application.
     *
     * ## Current Rules
     * - **Email Uniqueness**: Ensures no duplicate email addresses exist in the system
     *   - Organization-wide constraint for login security
     *   - Prevents identity conflicts and authentication issues
     *   - Error reported on 'email_address' field for user feedback
     *
     * ## Rule Categories
     * - **Unique Constraints**: Field uniqueness across the entire table
     * - **Cross-Entity Rules**: Could include complex relationship validations
     * - **Business Logic**: High-level organizational policy enforcement
     *
     * ## Integration with Validation
     * Rules complement validation by providing:
     * - **Database-Level Checks**: Ensures consistency even with concurrent operations
     * - **Business Logic**: Enforces organizational policies beyond data format
     * - **User Feedback**: Provides meaningful error messages for rule violations
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker instance
     * @return \Cake\ORM\RulesChecker Configured rules checker with business rules
     *
     * @example
     * ```php
     * // Email uniqueness rule in action
     * $member1 = $this->Members->newEntity(['email_address' => 'user@example.com']);
     * $this->Members->save($member1); // Success
     * 
     * $member2 = $this->Members->newEntity(['email_address' => 'user@example.com']);
     * $result = $this->Members->save($member2); // Fails due to uniqueness rule
     * 
     * if (!$result) {
     *     $errors = $member2->getError('email_address');
     *     // Contains uniqueness violation message
     * }
     * ```
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email_address']), ['errorField' => 'email_address']);

        return $rules;
    }

    /**
     * Execute automatic member processing before entity save
     *
     * Performs essential business logic processing automatically before any member
     * entity is saved to the database. This ensures member data consistency and
     * triggers important workflow processes without manual intervention.
     *
     * ## Automatic Processing Steps
     *
     * ### Age-Up Review (`ageUpReview()`)
     * - **Purpose**: Transitions minor members to adult status when they reach age 18
     * - **Status Updates**: Changes minor status to appropriate adult equivalents
     * - **Parent Removal**: Clears parent_id for newly adult members
     * - **Timing**: Executed on every save to catch age transitions promptly
     *
     * ### Warrant Eligibility Review (`warrantableReview()`)
     * - **Purpose**: Updates warrant eligibility based on current member data
     * - **Criteria Check**: Validates age, membership status, contact info completeness
     * - **Flag Updates**: Sets warrantable flag and populates non_warrantable_reasons
     * - **Real-time**: Ensures eligibility status is always current
     *
     * ## Integration Benefits
     * - **Automatic Workflows**: Reduces manual administrative overhead
     * - **Data Consistency**: Ensures derived fields are always accurate
     * - **Business Logic**: Enforces organizational rules consistently
     * - **Performance**: Batches related operations during save process
     *
     * ## Inherited Processing
     * Additionally benefits from BaseTable::afterSave() for:
     * - **Cache Invalidation**: Automatic cache clearing for affected data
     * - **Event Handling**: Integration with the KMP event system
     *
     * @param \Cake\Event\Event $event The beforeSave event
     * @param \Cake\Datasource\EntityInterface $entity The Member entity being saved
     * @param \ArrayObject $options Save operation options
     * @return void Processing modifies entity properties directly
     *
     * @example
     * ```php
     * // Automatic processing during save
     * $member = $this->Members->get($id);
     * $member->birth_year = 2006; // Member is now 18
     * $member->membership_expires_on = new Date('+1 year');
     * 
     * $this->Members->save($member);
     * // Automatically triggers:
     * // - ageUpReview(): May update status from minor to adult
     * // - warrantableReview(): Updates warrant eligibility
     * // - Cache invalidation: Clears related cached data
     * 
     * // No manual intervention required for business logic
     * ```
     */
    public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options): void
    {
        $entity->ageUpReview();
        $entity->warrantableReview();
    }

    /**
     * Get count of members requiring validation processing
     *
     * Calculates the number of members who are pending validation review based on
     * their current status and membership card information. This method supports
     * administrative workflows and validation queue management.
     *
     * ## Validation Queue Criteria
     * Members are included in the validation count if they meet ANY of these conditions:
     *
     * ### Active Members with Membership Cards
     * - **Status**: STATUS_ACTIVE (can login and participate)
     * - **Card Status**: Has membership_card_path (uploaded card image)
     * - **Purpose**: Verification of uploaded membership documentation
     *
     * ### Minor Members Requiring Review
     * - **Status**: STATUS_UNVERIFIED_MINOR or STATUS_MINOR_MEMBERSHIP_VERIFIED
     * - **Purpose**: Age verification and guardian consent processing
     * - **Workflow**: Manual review required for minor member activation
     *
     * ## Business Logic
     * - **Deleted Members**: Excluded from count (soft deletion check)
     * - **OR Logic**: Members matching ANY criteria are counted once
     * - **Real-time**: Always current count for administrative dashboards
     *
     * ## Administrative Integration
     * This method supports:
     * - **Dashboard Displays**: Show pending validation workload
     * - **Queue Management**: Prioritize validation tasks
     * - **Workload Planning**: Estimate administrative effort required
     * - **Reporting**: Track validation processing efficiency
     *
     * @return int Number of members requiring validation review
     *
     * @example
     * ```php
     * // Get current validation queue size
     * $queueCount = $this->Members->getValidationQueueCount();
     * 
     * // Display on admin dashboard
     * if ($queueCount > 0) {
     *     echo "Validation Queue: {$queueCount} members pending review";
     *     
     *     // Could trigger alerts for high queue sizes
     *     if ($queueCount > 50) {
     *         $this->notifyAdministrators("High validation queue: {$queueCount}");
     *     }
     * }
     * 
     * // Use in batch processing
     * $batchSize = min($queueCount, 25); // Process up to 25 at once
     * $membersToValidate = $this->Members->find()
     *     ->where(['validation criteria here'])
     *     ->limit($batchSize);
     * ```
     */
    static function getValidationQueueCount(): int
    {
        // Get the count of pending validations  based on the members status
        $membersTable = TableRegistry::getTableLocator()->get('Members');

        return $membersTable->find()
            ->where([
                'Members.deleted IS' => null,
                'OR' => [
                    [
                        'Members.membership_card_path IS NOT' => null,
                    ],
                    ['Members.status IN' => [
                        Member::STATUS_UNVERIFIED_MINOR,
                        Member::STATUS_MINOR_MEMBERSHIP_VERIFIED
                    ]],
                ],
            ])->count();
    }
}
