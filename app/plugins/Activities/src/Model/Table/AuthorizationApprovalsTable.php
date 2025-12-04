<?php

declare(strict_types=1);

namespace Activities\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use App\Model\Table\BaseTable;

/**
 * AuthorizationApprovalsTable
 *
 * Manages approval workflow tracking for authorization requests. Each record represents
 * an individual approval request sent to a specific approver, tracking decisions, timing,
 * and complete accountability.
 *
 * **Core Responsibilities:**
 * - Track multi-level approval workflows with secure token validation
 * - Record approver decisions (approve/deny) with timing and notes
 * - Provide complete audit trail of approval process
 * - Support email-based approval workflows
 * - Enable approval analytics and performance metrics
 *
 * **Database Schema:**
 * - `activities_authorization_approvals` table
 * - Foreign keys: authorization_id, approver_id
 * - Unique index on authorization_token for secure validation
 * - Indexes on authorization_id, approver_id, responded_on for queries
 *
 * **Key Methods:**
 * - `initialize()`: Set up associations and configuration
 * - `validationDefault()`: Field validation rules
 * - `buildRules()`: Referential integrity rules
 * - `memberAuthQueueCount()`: Static method for pending approval count
 *
 * **Performance:**
 * - Indexed on approver_id + responded_on for efficient pending queries
 * - Token-based lookups use unique index on authorization_token
 * - Association loading optimized for email notification workflows
 *
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\BelongsTo $Authorizations
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Approvers
 *
 * @see \Activities\Model\Entity\AuthorizationApproval Authorization approval entity
 * @see 5.6.8-authorization-approval-entity-reference.md Comprehensive technical reference
 * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
 */
class AuthorizationApprovalsTable extends BaseTable
{
    /**
     * Initialize the table with associations and configuration.
     *
     * **Associations:**
     * - `Authorizations` (INNER JOIN): Required authorization context
     * - `Approvers` (LEFT JOIN): Optional approver member context
     *
     * **Configuration:**
     * - Table name: `activities_authorization_approvals`
     * - Display field: `id`
     * - Primary key: `id`
     *
     * **Inherited Behaviors from BaseTable:**
     * - Timestamp tracking (created, modified)
     * - Footprint tracking (created_by, modified_by)
     * - Cache integration
     * - Branch scoping
     *
     * @param array<string, mixed> $config The configuration for the Table
     * @return void
     *
     * @see \Activities\Model\Table\AuthorizationsTable Authorization context
     * @see \App\Model\Table\MembersTable Approver member information
     * @see \App\Model\Table\BaseTable Inherited behaviors
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("activities_authorization_approvals");
        $this->setDisplayField("id");
        $this->setPrimaryKey("id");

        $this->belongsTo("Authorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "authorization_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Approvers", [
            "className" => "Members",
            "foreignKey" => "approver_id",
            "joinType" => "LEFT",
        ]);
    }

    /**
     * Default validation rules for authorization approval workflow data.
     *
     * **Required Fields:**
     * - `authorization_id`: Integer linking to valid Authorization
     * - `approver_id`: Integer linking to valid Member
     * - `authorization_token`: String (max 255) required on creation
     * - `requested_on`: Date field required on creation
     * - `approved`: Boolean (required when responding)
     *
     * **Optional Fields:**
     * - `responded_on`: Date, nullable until approval decision
     * - `approver_notes`: String (max 255), nullable
     *
     * **Validation Chain:**
     * 1. Field validation (types, lengths, presence)
     * 2. Referential integrity rules via `buildRules()`
     * 3. Custom business logic rules if needed
     *
     * @param \Cake\Validation\Validator $validator Validator configuration
     * @return \Cake\Validation\Validator Configured validator
     *
     * @see \Activities\Model\Entity\AuthorizationApproval Entity field mappings
     * @see \Cake\ORM\Table::buildRules() For referential integrity rules
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer("authorization_id")
            ->notEmptyString("authorization_id");

        $validator->integer("approver_id")->notEmptyString("approver_id");

        $validator
            ->scalar("authorization_token")
            ->maxLength("authorization_token", 255)
            ->requirePresence("authorization_token", "create")
            ->notEmptyString("authorization_token");

        $validator
            ->date("requested_on")
            ->requirePresence("requested_on", "create")
            ->notEmptyDate("requested_on");

        $validator->date("responded_on")->allowEmptyDate("responded_on");

        $validator->boolean("approved")->notEmptyString("approved");

        $validator
            ->scalar("approver_notes")
            ->maxLength("approver_notes", 255)
            ->allowEmptyString("approver_notes");

        return $validator;
    }

    /**
     * Application integrity rules for authorization approval workflow.
     *
     * **Rules Enforced:**
     * - `authorization_id` must reference existing Authorization entity
     * - `approver_id` must reference existing Member entity
     *
     * **Validation Chain:**
     * 1. Field validation via `validationDefault()`
     * 2. Referential integrity rules via `buildRules()`
     * 3. Database constraints at persistence time
     *
     * **Error Handling:**
     * Rule violations produce field-specific error messages for targeted
     * error display in forms and API responses.
     *
     * **Performance:**
     * Rules use CakePHP's caching for existence checks, with queries
     * leveraging primary key indexes for fast validation.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker configuration
     * @return \Cake\ORM\RulesChecker Configured rules checker
     *
     * @see \Activities\Model\Table\AuthorizationsTable Authorization existence check
     * @see \App\Model\Table\MembersTable Approver existence check
     * @see \Cake\ORM\RulesChecker CakePHP rules checker documentation
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(["authorization_id"], "Authorizations"), [
            "errorField" => "authorization_id",
        ]);
        $rules->add($rules->existsIn(["approver_id"], "Approvers"), [
            "errorField" => "approver_id",
        ]);

        return $rules;
    }

    /**
     * Get pending approval queue count for a specific member.
     *
     * Returns the count of pending authorization approvals assigned to a specific
     * member. Optimized for navigation badge display and member dashboard integration
     * as a lightweight static query.
     *
     * **Query Criteria:**
     * - `approver_id = $memberId`: Approvals assigned to this member
     * - `responded_on IS NULL`: Pending (not yet responded)
     *
     * **Return Value:**
     * Integer count of pending approvals (0 if none)
     *
     * **Performance:**
     * - Static method: No table instantiation overhead
     * - COUNT(*) query: Minimal database load
     * - Indexed on approver_id + responded_on
     * - Compatible with query caching
     *
     * **Common Uses:**
     * - Navigation badge display
     * - Member dashboard statistics
     * - Approval queue monitoring
     * - Real-time notification systems
     *
     * @param int $memberId The ID of the member
     * @return int Count of pending approvals assigned to member
     *
     * @see \Activities\Model\Entity\AuthorizationApproval Authorization approval entity
     * @see 5.6.8-authorization-approval-entity-reference.md Technical reference
     */
    public static function memberAuthQueueCount($memberId): int
    {
        $approvals = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $query = $approvals->find("all")
            ->where([
                "approver_id" => $memberId,
                "responded_on IS" => null,
            ]);

        return $query->count();
    }
}
