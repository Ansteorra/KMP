<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use App\KMP\StaticHelpers;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use App\Model\Table\BaseTable;

/**
 * Awards Recommendations Table - Recommendation Lifecycle Management and Complex State Machine
 * 
 * The RecommendationsTable class provides comprehensive data management for award recommendations
 * within the Awards plugin, implementing a complex state machine, workflow management, and
 * extensive association relationships. This table serves as the central component for managing
 * the complete recommendation lifecycle from submission through approval to award presentation.
 * 
 * ## State Machine Integration
 * 
 * The RecommendationsTable implements a sophisticated state machine for recommendation workflow:
 * - Automated state transitions with afterSave() lifecycle hooks
 * - State change logging through RecommendationsStatesLogs integration
 * - Status and state dual tracking for comprehensive workflow management
 * - Transaction-safe state changes with audit trail and accountability tracking
 * 
 * ## Complex Association Architecture
 * 
 * The table establishes extensive relationships within the Awards plugin ecosystem:
 * 
 * ### Member Associations
 * - **Requesters**: belongsTo relationship linking to recommendation submitters
 * - **Members**: belongsTo relationship linking to recommended members
 * - **Integration**: Supports both authenticated and guest recommendation workflows
 * 
 * ### Awards System Integration
 * - **Awards**: belongsTo relationship with INNER join for required award association
 * - **Branches**: belongsTo relationship for organizational scoping and access control
 * - **Integration**: Enables award-specific recommendation processing and organizational management
 * 
 * ### Event Management Integration
 * - **ScheduledEvent**: belongsTo relationship for event-based recommendation coordination
 * - **AssignedEvent**: belongsTo relationship for ceremony assignment and coordination
 * - **Events**: belongsToMany relationship through junction table for flexible event assignment
 * - **Junction Table**: `awards_recommendations_events` for complex event-recommendation mapping
 * 
 * ### Workflow Support Associations
 * - **Notes**: hasMany relationship for recommendation documentation and workflow tracking
 * - **RecommendationStateLogs**: hasMany relationship for comprehensive audit trail
 * - **Integration**: Supports documentation workflow and administrative oversight
 * 
 * ## Workflow Management Features
 * 
 * ### Recommendation Lifecycle
 * - Submission processing with member and award validation
 * - State transition management with automated logging and accountability
 * - Event assignment and ceremony coordination workflows
 * - Administrative oversight and approval processing capabilities
 * 
 * ### State Change Automation
 * - Automatic state logging through afterSave() lifecycle hooks
 * - State transition tracking with before/after state comparison
 * - User accountability through state change attribution
 * - Comprehensive audit trail for administrative oversight and compliance
 * 
 * ## Behavior Integration
 * 
 * The table incorporates essential CakePHP behaviors for comprehensive functionality:
 * - **Timestamp Behavior**: Automatic creation and modification timestamp management
 * - **Footprint Behavior**: User tracking for creation and modification accountability
 * - **Trash Behavior**: Soft deletion support with data retention and recovery capabilities
 * - **Sortable Behavior**: Stack ranking support for recommendation prioritization and organization
 * 
 * ## Usage Examples
 * 
 * ### Recommendation Submission
 * ```php
 * // Submit new recommendation
 * $recommendationsTable = TableRegistry::getTableLocator()->get('Awards.Recommendations');
 * $recommendation = $recommendationsTable->newEmptyEntity();
 * $recommendation = $recommendationsTable->patchEntity($recommendation, [
 *     'requester_sca_name' => 'Duke John',
 *     'member_sca_name' => 'Lady Jane',
 *     'contact_email' => 'duke.john@example.com',
 *     'award_id' => 1,
 *     'reason' => 'Outstanding service to the realm...',
 *     'state' => 'submitted',
 *     'status' => 'pending'
 * ]);
 * 
 * if ($recommendationsTable->save($recommendation)) {
 *     // State change automatically logged
 * }
 * ```
 * 
 * ### State Transition Processing
 * ```php
 * // Process recommendation state change
 * $recommendation = $recommendationsTable->get($recommendationId);
 * $recommendation->beforeState = $recommendation->state;
 * $recommendation->beforeStatus = $recommendation->status;
 * 
 * $recommendation = $recommendationsTable->patchEntity($recommendation, [
 *     'state' => 'approved',
 *     'status' => 'approved',
 *     'modified_by' => $userId
 * ]);
 * 
 * $recommendationsTable->save($recommendation); // Triggers automatic logging
 * ```
 * 
 * ### Workflow Processing
 * ```php
 * // Find recommendations for processing
 * $pendingRecommendations = $recommendationsTable->find()
 *     ->where(['state' => 'submitted'])
 *     ->contain(['Awards', 'Members', 'Requesters'])
 *     ->orderBy(['created' => 'ASC'])
 *     ->toArray();
 * 
 * // Event assignment processing
 * $eventRecommendations = $recommendationsTable->find()
 *     ->innerJoinWith('Events')
 *     ->where(['Events.start_date <=' => date('Y-m-d')])
 *     ->where(['Events.end_date >=' => date('Y-m-d')])
 *     ->toArray();
 * ```
 * 
 * ### Administrative Operations
 * ```php
 * // Branch-scoped recommendation discovery
 * $branchRecommendations = $recommendationsTable->find()
 *     ->innerJoinWith('Awards.Branches')
 *     ->where(['Awards.branch_id IN' => $authorizedBranchIds])
 *     ->contain(['RecommendationStateLogs'])
 *     ->toArray();
 * 
 * // Sortable stack ranking
 * $recommendationsTable->moveUp($recommendation, 3); // Move up 3 positions
 * $recommendationsTable->moveDown($recommendation, 1); // Move down 1 position
 * ```
 * 
 * ## Integration Points
 * 
 * ### Awards System Integration
 * - Award-specific recommendation validation and processing workflows
 * - Integration with award hierarchy and organizational structure management
 * - Administrative coordination for award presentation and ceremony planning
 * - Support for award lifecycle management and recommendation workflow integration
 * 
 * ### Event Management Integration
 * - Event-based recommendation processing and ceremony coordination workflows
 * - Temporal constraint management through event association and deadline enforcement
 * - Administrative tools for recommendation-event assignment and ceremony coordination
 * - Integration with ceremony planning and administrative oversight capabilities
 * 
 * ### State Logging Integration
 * - Comprehensive audit trail through RecommendationsStatesLogs association
 * - Automated state change logging with user accountability and timeline tracking
 * - Administrative oversight through state transition history and workflow analytics
 * - Integration with compliance monitoring and administrative reporting systems
 * 
 * ### Member Management Integration
 * - Member-specific recommendation processing and validation workflows
 * - Integration with member profiles and organizational hierarchy management
 * - Support for both authenticated and guest recommendation submission workflows
 * - Administrative coordination for member recognition and award processing
 * 
 * ### Approval Workflow Integration
 * - State machine integration with approval processing and workflow management
 * - Administrative oversight through state transition management and accountability
 * - Integration with notification systems and approval workflow coordination
 * - Support for multi-level approval processes and administrative review capabilities
 * 
 * ## Security Considerations
 * 
 * ### Data Integrity
 * - Validation rules ensuring proper recommendation data and association requirements
 * - Foreign key constraints maintaining referential integrity across complex associations
 * - Soft deletion preventing data loss while maintaining workflow and audit integrity
 * - Audit trail support through state logging and Footprint behavior for accountability
 * 
 * ### Access Control
 * - Integration with authorization policies for branch-scoped recommendation management
 * - Permission-based recommendation processing and administrative access control
 * - Administrative oversight for recommendation workflows and ceremony coordination
 * - Secure recommendation discovery and organizational data access patterns
 * 
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Requesters
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \Awards\Model\Table\EventsTable&\Cake\ORM\Association\BelongsTo $ScheduledEvent
 * @property \App\Model\Table\BranchesTable&\Cake\ORM\Association\BelongsTo $Branches
 * @property \Awards\Model\Table\AwardsTable&\Cake\ORM\Association\BelongsTo $Awards
 * @property \Awards\Model\Table\EventsTable&\Cake\ORM\Association\BelongsToMany $Events
 * @property \Awards\Model\Table\EventsTable&\Cake\ORM\Association\BelongsTo $AssignedEvent
 * @property \App\Model\Table\NotesTable&\Cake\ORM\Association\HasMany $Notes
 * @property \Awards\Model\Table\RecommendationsStatesLogsTable&\Cake\ORM\Association\HasMany $RecommendationStateLogs
 * @property \Cake\ORM\Behavior\TimestampBehavior&\Cake\ORM\Behavior $Timestamp
 * @property \Muffin\Footprint\Model\Behavior\FootprintBehavior&\Cake\ORM\Behavior $Footprint
 * @property \Muffin\Trash\Model\Behavior\TrashBehavior&\Cake\ORM\Behavior $Trash
 * @property \Cake\ORM\Behavior\Sortable\SortableBehavior&\Cake\ORM\Behavior $Sortable
 *
 * @method \Awards\Model\Entity\Recommendation newEmptyEntity()
 * @method \Awards\Model\Entity\Recommendation newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Recommendation> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\Recommendation get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\Recommendation findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\Recommendation patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Awards\Model\Entity\Recommendation> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\Recommendation|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\Recommendation saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\Recommendation>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\Recommendation> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RecommendationsTable extends BaseTable
{
    /**
     * Configure the Recommendations table: table name, display/primary fields, behaviors, and associations.
     *
     * Sets the database table and primary/display fields, attaches timestamp, footprint, trash, and sortable
     * behaviors, and defines associations used for member links, awards, events/gatherings, notes, and state logs.
     *
     * @param array<string,mixed> $config Table configuration options.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendations');
        $this->setDisplayField('member_sca_name');
        $this->setPrimaryKey('id');

        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
        $this->addBehavior("Sortable", [
            'field' => 'stack_rank',
        ]);

        $this->belongsTo('Requesters', [
            'foreignKey' => 'requester_id',
            'joinType' => 'LEFT',
            'className' => 'Members',
        ]);
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'LEFT',
            'className' => 'Members',
        ]);
        $this->belongsTo('ScheduledEvent', [
            'foreignKey' => 'event_id',
            'joinType' => 'LEFT',
            'className' => 'Awards.Events',
        ]);
        $this->belongsTo('Branches', [
            'foreignKey' => 'branch_id',
            'className' => 'Branches',
        ]);
        $this->belongsTo('Awards', [
            'foreignKey' => 'award_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Awards',
        ]);
        $this->belongsToMany("Events", [
            "joinTable" => "awards_recommendations_events",
            "foreignKey" => "recommendation_id",
            "targetForeignKey" => "event_id",
            "className" => "Awards.Events",
        ]);
        $this->belongsToMany("Gatherings", [
            "joinTable" => "awards_recommendations_events",
            "foreignKey" => "recommendation_id",
            "targetForeignKey" => "gathering_id",
            "className" => "Gatherings",
        ]);
        $this->belongsTo("AssignedEvent", [
            'foreignKey' => 'event_id',
            'joinType' => 'LEFT',
            "className" => "Awards.Events",
        ]);
        $this->belongsTo("AssignedGathering", [
            'foreignKey' => 'gathering_id',
            'joinType' => 'LEFT',
            "className" => "Gatherings",
        ]);
        $this->hasMany("Notes", [
            "foreignKey" => "entity_id",
            "className" => "Notes",
            "conditions" => ["Notes.entity_type" => "Awards.Recommendations"],
        ]);
        $this->hasMany("RecommendationStateLogs", [
            "foreignKey" => "recommendation_id",
            "className" => "Awards.RecommendationsStatesLog",
        ]);
    }

    /**
     * Default validation rules - Comprehensive recommendation data validation and workflow integrity
     * 
     * Establishes comprehensive validation rules for recommendation data management, ensuring
     * workflow integrity, member accountability, and administrative data quality. The validation
     * framework supports both required and optional fields with appropriate constraints for
     * recommendation processing, member integration, and state machine management.
     * 
     * ## Association Validation
     * 
     * ### Member Association Fields
     * - **Requester ID**: Integer field for recommendation submitter (optional)
     * - **Member ID**: Integer field for recommended member (optional)
     * - **Purpose**: Supports both authenticated and guest recommendation workflows
     * - **Integration**: Flexible association allowing guest submissions and member linkage
     * 
     * ### Organizational Fields
     * - **Branch ID**: Integer field for organizational scoping (optional)
     * - **Award ID**: Integer field for award association (required)
     * - **Purpose**: Links recommendations to organizational structure and specific awards
     * - **Integration**: Works with business rules for referential integrity validation
     * 
     * ## Core Recommendation Fields
     * 
     * ### Identity Fields
     * - **Requester SCA Name**: Required scalar field, max 255 characters
     * - **Member SCA Name**: Required scalar field, max 255 characters
     * - **Purpose**: Ensures clear identification for both submitter and recommended member
     * - **Workflow**: Critical for recommendation processing and administrative coordination
     * 
     * ### Contact Information
     * - **Contact Email**: Required scalar field, max 255 characters for communication
     * - **Contact Number**: Optional scalar field, max 100 characters for additional contact
     * - **Purpose**: Enables administrative contact and workflow communication
     * - **Integration**: Supports notification systems and approval workflow coordination
     * 
     * ### Recommendation Content
     * - **Reason**: Required scalar field, max 10,000 characters for detailed justification
     * - **Purpose**: Core recommendation content explaining award justification
     * - **Workflow**: Essential for approval processing and administrative review
     * - **Administrative**: Supports comprehensive recommendation evaluation and documentation
     * 
     * ## Administrative Fields
     * 
     * ### User Tracking Fields
     * - **Created By**: Integer field for creation accountability (optional)
     * - **Modified By**: Integer field for modification tracking (optional)
     * - **Purpose**: Supports audit trail and administrative oversight
     * - **Integration**: Works with Footprint behavior for automatic population
     * 
     * ### Lifecycle Management
     * - **Deleted Field**: DateTime field for soft deletion timestamp (optional)
     * - **Given Field**: Date field for award presentation tracking (optional)
     * - **Purpose**: Supports lifecycle management and ceremony coordination
     * - **Integration**: Works with Trash behavior and event management systems
     * 
     * ## Workflow Integration
     * 
     * ### State Machine Support
     * - Validation rules support state machine operation and workflow processing
     * - Integration with recommendation lifecycle and approval workflow management
     * - Administrative oversight through proper field validation and data integrity
     * - Support for automated workflow processing and state transition management
     * 
     * ### Guest Workflow Support
     * - Optional member associations enabling guest recommendation submissions
     * - Required identity fields ensuring accountability regardless of authentication status
     * - Contact information requirements supporting communication across workflow types
     * - Administrative flexibility for both authenticated and guest recommendation processing
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('requester_id')
            ->allowEmptyString('requester_id');

        $validator
            ->integer('member_id')
            ->allowEmptyString('member_id');

        $validator
            ->integer('branch_id')
            ->allowEmptyString('branch_id');

        $validator
            ->integer('award_id')
            ->notEmptyString('award_id');

        $validator
            ->scalar('requester_sca_name')
            ->maxLength('requester_sca_name', 255)
            ->requirePresence('requester_sca_name', 'create')
            ->notEmptyString('requester_sca_name');

        $validator
            ->scalar('member_sca_name')
            ->maxLength('member_sca_name', 255)
            ->requirePresence('member_sca_name', 'create')
            ->notEmptyString('member_sca_name');

        $validator
            ->scalar('contact_email')
            ->maxLength('contact_email', 255)
            ->requirePresence('contact_email', 'create')
            ->notEmptyString('contact_email');

        $validator
            ->scalar('contact_number')
            ->maxLength('contact_number', 100)
            ->allowEmptyString('contact_number');

        $validator
            ->scalar('reason')
            ->maxLength('reason', 10000)
            ->requirePresence('reason', 'create')
            ->notEmptyString('reason');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        $validator
            ->integer('modified_by')
            ->allowEmptyString('modified_by');

        $validator
            ->dateTime('deleted')
            ->allowEmptyDateTime('deleted');


        $validator
            ->date('given')
            ->allowEmptyDate('given');

        return $validator;
    }

    /**
     * Returns a rules checker object for comprehensive referential integrity validation
     * 
     * Establishes comprehensive business rules for recommendation data integrity, ensuring
     * proper association validation and referential integrity across the complex relationship
     * architecture. The rules checker provides database-level validation beyond field-level
     * validation, enforcing organizational constraints and workflow integrity requirements.
     * 
     * ## Referential Integrity Rules
     * 
     * ### Member Association Validation
     * - **Requester Validation**: Enforces valid requester references through existsIn validation
     * - **Member Validation**: Enforces valid member references for recommended individuals
     * - **Table References**: Both validate against Members table for member management integrity
     * - **Error Fields**: Proper error field association for administrative interface integration
     * - **Purpose**: Prevents orphaned recommendations without valid member associations
     * 
     * ### Organizational Validation
     * - **Branch Validation**: Enforces valid branch references for organizational scoping
     * - **Award Validation**: Enforces valid award references for recommendation processing
     * - **Table References**: Validates against Branches and Awards tables respectively
     * - **Administrative**: Supports organizational structure integrity and award system coordination
     * 
     * ## Data Integrity Features
     * 
     * ### Association Consistency
     * - Ensures recommendations are properly associated with valid organizational entities
     * - Prevents administrative confusion through orphaned recommendation records
     * - Supports organizational structure management and workflow coordination
     * - Enables consistent recommendation discovery and administrative oversight
     * 
     * ### Workflow Integrity
     * - Provides comprehensive validation for recommendation workflow processing
     * - Supports state machine operation through proper association validation
     * - Enables administrative workflow management and ceremony coordination
     * - Integration with recommendation lifecycle and approval workflow systems
     * 
     * ## Integration Points
     * 
     * ### Validation Workflow
     * - Works in conjunction with validationDefault() for comprehensive validation
     * - Provides database-level constraints beyond field validation
     * - Supports administrative form processing and workflow coordination
     * - Enables consistent validation across different recommendation access methods
     * 
     * ### Error Handling
     * - Proper error field association for administrative interface integration
     * - Clear validation messaging for administrative user feedback and workflow guidance
     * - Support for form validation and administrative workflow management
     * - Integration with administrative error handling and recommendation processing systems
     * 
     * ## Administrative Features
     * 
     * ### Organizational Oversight
     * - Branch and award validation ensures proper organizational scoping
     * - Member validation supports accountability and administrative coordination
     * - Comprehensive association validation for administrative workflow management
     * - Integration with organizational structure and administrative oversight systems
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['requester_id'], 'Members'), ['errorField' => 'requester_id']);
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);
        $rules->add($rules->existsIn(['branch_id'], 'Branches'), ['errorField' => 'branch_id']);
        $rules->add($rules->existsIn(['award_id'], 'Awards'), ['errorField' => 'award_id']);

        return $rules;
    }

    /**
     * After save lifecycle hook - Automated state change logging and audit trail management
     * 
     * Implements automated state change detection and logging functionality as part of the
     * recommendation lifecycle management system. This method triggers whenever a recommendation
     * entity is saved, detecting state changes and automatically creating audit trail records
     * for comprehensive workflow tracking and administrative oversight.
     * 
     * ## State Change Detection
     * 
     * ### Dirty Field Monitoring
     * - **Detection**: Monitors `state` field for changes using entity dirty field tracking
     * - **Trigger**: Automatically activates when state field has been modified
     * - **Efficiency**: Only processes logging when actual state changes occur
     * - **Integration**: Works with CakePHP entity system for reliable change detection
     * 
     * ### Workflow Integration
     * - Seamless integration with recommendation state machine and workflow processing
     * - Automatic activation during state transitions and administrative updates
     * - Support for both manual and automated state change processing
     * - Administrative oversight through consistent audit trail generation
     * 
     * ## Audit Trail Automation
     * 
     * ### Automated Logging
     * - **Trigger**: Automatically calls logStateChange() method when state changes detected
     * - **Consistency**: Ensures all state changes are properly logged for audit compliance
     * - **Reliability**: Integrated into save lifecycle for guaranteed execution
     * - **Administrative**: Supports comprehensive audit trail and accountability requirements
     * 
     * ### Workflow Transparency
     * - Provides complete visibility into recommendation state transitions
     * - Enables administrative tracking of workflow progression and decision points
     * - Supports compliance monitoring and audit trail requirements
     * - Integration with administrative oversight and reporting systems
     * 
     * ## Integration Features
     * 
     * ### State Machine Support
     * - Seamless integration with recommendation state machine and workflow management
     * - Automatic audit trail generation for all state transitions and workflow changes
     * - Support for complex workflow scenarios and administrative state management
     * - Integration with approval workflows and administrative decision tracking
     * 
     * ### Administrative Oversight
     * - Comprehensive audit trail for administrative review and compliance monitoring
     * - Timeline tracking for workflow analysis and administrative coordination
     * - User accountability through state change attribution and audit documentation
     * - Integration with administrative reporting and oversight systems
     * 
     * @param bool $created Whether the entity was created (true) or updated (false)
     * @param \Cake\Datasource\EntityInterface $entity The recommendation entity that was saved
     * @param \ArrayObject $options Save operation options and configuration
     * @return void
     */
    public function afterSave($created, $entity, $options): void
    {
        //check if the state is marked dirty in the entity->dirty array
        if ($entity->isDirty('state')) {
            $this->logStateChange($entity);
        }
    }
    /**
     * Log state change - Create comprehensive audit trail record for state transitions
     * 
     * Creates detailed audit trail records for recommendation state transitions, capturing
     * comprehensive information about state changes including before/after states, user
     * attribution, and timeline tracking. This method provides the core audit trail
     * functionality for the recommendation workflow system.
     * 
     * ## Audit Record Creation
     * 
     * ### State Transition Documentation
     * - **From State**: Records the previous state for complete transition tracking
     * - **To State**: Records the new state for workflow progression documentation
     * - **From Status**: Captures previous status information for comprehensive audit trail
     * - **To Status**: Captures new status information for workflow state documentation
     * - **Fallback**: Uses "New" as default for initial state transitions and data integrity
     * 
     * ### User Accountability
     * - **Created By**: Links state changes to modifying user for accountability tracking
     * - **Attribution**: Uses entity's modified_by field for user responsibility documentation
     * - **Timeline**: Automatic timestamp recording through RecommendationsStatesLogsTable behavior
     * - **Administrative**: Supports administrative oversight and user accountability requirements
     * 
     * ## Data Management
     * 
     * ### Entity Preparation
     * - Creates new empty RecommendationsStatesLog entity for audit record
     * - Populates comprehensive state transition data from recommendation entity
     * - Links audit record to recommendation through recommendation_id foreign key
     * - Ensures proper data integrity and referential consistency
     * 
     * ### State Information Capture
     * - **Before State Access**: Utilizes entity's beforeState property for transition tracking
     * - **Before Status Access**: Utilizes entity's beforeStatus property for comprehensive audit
     * - **Current State**: Captures entity's current state and status for complete documentation
     * - **Data Integrity**: Provides fallback values ensuring complete audit trail records
     * 
     * ## Integration Features
     * 
     * ### Table Registry Integration
     * - **Dynamic Loading**: Uses TableRegistry for RecommendationsStatesLogsTable access
     * - **Namespace**: Properly scoped to Awards.RecommendationsStatesLogs for plugin integration
     * - **Performance**: Efficient table access for audit logging operations
     * - **Architecture**: Clean separation of concerns with dedicated audit table management
     * 
     * ### Workflow Support
     * - Seamless integration with recommendation state machine and workflow processing
     * - Automatic audit trail generation for administrative oversight and compliance
     * - Support for complex state transitions and workflow management scenarios
     * - Integration with approval workflows and administrative decision tracking systems
     * 
     * ## Administrative Benefits
     * 
     * ### Compliance Support
     * - Complete audit trail for regulatory compliance and administrative oversight
     * - Timeline tracking for workflow analysis and administrative coordination
     * - User accountability through comprehensive state change attribution
     * - Integration with administrative reporting and compliance monitoring systems
     * 
     * ### Workflow Analytics
     * - Comprehensive state transition history for workflow analysis and optimization
     * - Administrative insight into recommendation processing patterns and efficiency
     * - Support for workflow improvement and administrative process optimization
     * - Integration with reporting systems for administrative dashboard and analytics
     * 
     * @param \Cake\Datasource\EntityInterface $entity The recommendation entity with state changes
     * @return void
     */
    protected function logStateChange($entity)
    {
        $logTbl = TableRegistry::getTableLocator()->get('Awards.RecommendationsStatesLogs');
        $log = $logTbl->newEmptyEntity();
        $log->recommendation_id = $entity->id;
        $log->to_state = $entity->state;
        $log->to_status = $entity->status;
        $log->from_status = $entity->beforeStatus ? $entity->beforeStatus : "New";
        $log->from_state = $entity->beforeState ? $entity->beforeState : "New";
        $log->created_by = $entity->modified_by;
        $logTbl->save($log);
    }

    /**
     * Apply branch-based filtering to a recommendations query.
     *
     * Filters the provided query so only records whose associated Awards.branch_id
     * is contained in $branchIDs are returned. If $branchIDs is empty, the query
     * is returned unchanged.
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query to modify.
     * @param int[] $branchIDs Array of branch IDs to restrict Awards.branch_id to.
     * @return \Cake\ORM\Query\SelectQuery The query with branch filtering applied.
     */
    public function addBranchScopeQuery($query, $branchIDs): SelectQuery
    {
        if (empty($branchIDs)) {
            return $query;
        }
        $query = $query->where([
            "Awards.branch_id IN" => $branchIDs,
        ]);
        return $query;
    }
}