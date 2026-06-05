<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use DateTime as NativeDateTime;
use InvalidArgumentException;

/**
 * Recommendation Entity - Award recommendation workflow with state machine.
 *
 * Implements dual status/state tracking:
 * - Status: high-level category (In Progress, Scheduling, To Give, Closed)
 * - State: specific workflow position within the status category
 *
 * @property int $id
 * @property int|null $recommendation_group_id
 * @property string|null $group_origin_state
 * @property string|null $group_origin_status
 * @property int $requester_id
 * @property int|null $member_id
 * @property int|null $branch_id
 * @property int $award_id
 * @property int|null $event_id
 * @property int|null $gathering_id
 * @property int|null $bestowal_id
 * @property string $status
 * @property string $state
 * @property \Cake\I18n\DateTime|null $state_date
 * @property \Cake\I18n\DateTime|null $given
 * @property int|null $stack_rank
 * @property string $requester_sca_name
 * @property string $member_sca_name
 * @property string $contact_number
 * @property string|null $contact_email
 * @property string|null $reason
 * @property string|null $specialty
 * @property string|null $call_into_court
 * @property string|null $court_availability
 * @property string|null $person_to_notify
 * @property string|null $close_reason
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\Member $requester
 * @property \Awards\Model\Entity\Award $award
 * @property \Awards\Model\Entity\Event $event
 * @property \App\Model\Entity\Branch $branch
 * @property \Awards\Model\Entity\Recommendation|null $group_head
 * @property \Awards\Model\Entity\Recommendation[] $group_children
 * @property \Awards\Model\Entity\Bestowal|null $bestowal
 * @property \Awards\Model\Entity\RecommendationApprovalRun|null $current_approval_run
 */
class Recommendation extends BaseEntity
{
    /**
     * Per-request cache for status→state hierarchy.
     */
    private static ?array $cachedStatuses = null;

    /**
     * Per-request cache for all state names.
     */
    private static ?array $cachedStates = null;

    /**
     * Per-request cache for state rules (state name → rule arrays).
     */
    private static ?array $cachedStateRules = null;

    /**
     * Per-request cache for gathering-assignable state names.
     */
    private static ?array $cachedGatheringStates = null;

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'recommendation_group_id' => true,
        'group_origin_state' => true,
        'group_origin_status' => true,
        'requester_id' => true,
        'stack_rank' => true,
        'member_id' => true,
        'branch_id' => true,
        'award_id' => true,
        'event_id' => true, // Deprecated - kept for migration compatibility, use gathering_id instead
        'gathering_id' => true,
        'bestowal_id' => true,
        'given' => true,
        'status' => true,
        'state' => true,
        'state_date' => true,
        'stack_rank' => true,
        'requester_sca_name' => true,
        'member_sca_name' => true,
        'contact_number' => true,
        'contact_email' => true,
        'reason' => true,
        'specialty' => true,
        'call_into_court' => true,
        'court_availability' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'member' => true,
        'events' => true,
        'gatherings' => true,
        'person_to_notify' => true,
        'close_reason' => true,
    ];

    /**
     * Handle date format conversion for given date.
     *
     * @param mixed $value Date value
     * @return \DateTime
     */
    protected function _setGiven($value): NativeDateTime
    {
        if (is_string($value)) {
            $value = new NativeDateTime($value);
        }

        return $value;
    }

    /**
     * State machine setter - validates state and auto-updates status.
     *
     * Reads valid states and field rules from database tables.
     *
     * @param string $value New state value
     * @return string Validated state value
     * @throws \InvalidArgumentException When state is invalid
     */
    protected function _setState($value): string
    {
        $this->beforeState = $this->state;
        $this->beforeStatus = $this->status;

        $states = self::getStates();
        if (!in_array($value, $states)) {
            throw new InvalidArgumentException('Invalid State');
        }
        $statuses = self::getStatuses();
        $nextStatus = $this->status;
        foreach ($statuses as $statusKey => $status) {
            if (in_array($value, $status)) {
                $nextStatus = $statusKey;
                break;
            }
        }
        if ($nextStatus != $this->status) {
            $this->status = $nextStatus;
        }
        $this->state_date = new DateTime();

        // Apply field set rules from database
        $stateRules = self::getStateRules();
        if (isset($stateRules[$value]['Set'])) {
            foreach ($stateRules[$value]['Set'] as $field => $fieldValue) {
                $this->$field = $fieldValue;
            }
        }

        if (!self::supportsGatheringAssignmentForState((string)$value)) {
            $this->gathering_id = null;
        }

        return $value;
    }

    /**
     * Get status categories and their state mappings from database.
     *
     * @return array<string, array<int, string>> Status name => [state names]
     */
    public static function getStatuses(): array
    {
        if (self::$cachedStatuses !== null) {
            return self::$cachedStatuses;
        }

        $statusesTable = TableRegistry::getTableLocator()->get('Awards.RecommendationStatuses');
        $statuses = $statusesTable->find()
            ->contain(['RecommendationStates' => function ($q) {
                return $q->orderBy(['RecommendationStates.sort_order' => 'ASC']);
            }])
            ->orderBy(['RecommendationStatuses.sort_order' => 'ASC'])
            ->all();

        $result = [];
        foreach ($statuses as $status) {
            $result[$status->name] = [];
            foreach ($status->recommendation_states as $state) {
                $result[$status->name][] = $state->name;
            }
        }

        self::$cachedStatuses = $result;

        return $result;
    }

    /**
     * Get valid states, optionally filtered by status.
     *
     * @param string|null $status Optional status filter
     * @return array<int, string> Valid states list
     */
    public static function getStates($status = null): array
    {
        if ($status) {
            $statusList = self::getStatuses();

            return $statusList[$status] ?? [];
        }

        if (self::$cachedStates !== null) {
            return self::$cachedStates;
        }

        $statuses = self::getStatuses();
        $states = [];
        foreach ($statuses as $statusStates) {
            foreach ($statusStates as $state) {
                $states[] = $state;
            }
        }

        self::$cachedStates = $states;

        return $states;
    }

    /**
     * Get field rules grouped by state name from database.
     *
     * Returns an array keyed by state name where each value contains
     * rule type groups: Visible, Optional, Required, Disabled, Set.
     *
     * @return array<string, array<string, mixed>> State name => rules
     */
    public static function getStateRules(): array
    {
        if (self::$cachedStateRules !== null) {
            return self::$cachedStateRules;
        }

        $statesTable = TableRegistry::getTableLocator()->get('Awards.RecommendationStates');
        $states = $statesTable->find()
            ->contain(['RecommendationStateFieldRules'])
            ->all();

        $rules = [];
        foreach ($states as $state) {
            if (empty($state->recommendation_state_field_rules)) {
                continue;
            }
            $stateRules = [];
            foreach ($state->recommendation_state_field_rules as $rule) {
                if ($rule->rule_type === 'Set') {
                    $stateRules['Set'][$rule->field_target] = $rule->rule_value;
                } else {
                    $stateRules[$rule->rule_type][] = $rule->field_target;
                }
            }
            $rules[$state->name] = $stateRules;
        }

        self::$cachedStateRules = $rules;

        return $rules;
    }

    /**
     * Get hidden states that require ViewHidden permission.
     *
     * @return array<int, string> List of hidden state names
     */
    public static function getHiddenStates(): array
    {
        $statesTable = TableRegistry::getTableLocator()->get('Awards.RecommendationStates');
        $hidden = $statesTable->find()
            ->where(['is_hidden' => true])
            ->all();

        $result = [];
        foreach ($hidden as $state) {
            $result[] = $state->name;
        }

        return $result;
    }

    /**
     * Determine whether a recommendation state supports a scheduled gathering assignment.
     *
     * @param string $state Workflow state name.
     * @return bool
     */
    public static function supportsGatheringAssignmentForState(string $state): bool
    {
        if (self::$cachedGatheringStates === null) {
            $statesTable = TableRegistry::getTableLocator()->get('Awards.RecommendationStates');
            $gatheringStates = $statesTable->find()
                ->where(['supports_gathering' => true])
                ->all();

            self::$cachedGatheringStates = [];
            foreach ($gatheringStates as $s) {
                self::$cachedGatheringStates[] = $s->name;
            }
        }

        return in_array($state, self::$cachedGatheringStates, true);
    }

    /**
     * Get valid transitions from a given state.
     *
     * @param string $fromState The current state name
     * @return array<int, string> List of state names that can be transitioned to
     */
    public static function getValidTransitionsFrom(string $fromState): array
    {
        $statesTable = TableRegistry::getTableLocator()->get('Awards.RecommendationStates');
        $fromStateEntity = $statesTable->find()
            ->where(['name' => $fromState])
            ->first();

        if (!$fromStateEntity) {
            return [];
        }

        $transitionsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationStateTransitions');
        $transitions = $transitionsTable->find()
            ->contain(['ToStates'])
            ->where(['from_state_id' => $fromStateEntity->id])
            ->all();

        $result = [];
        foreach ($transitions as $transition) {
            $result[] = $transition->to_state->name;
        }

        return $result;
    }

    /**
     * Clear all cached state/status data. Call after modifying states or statuses.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$cachedStatuses = null;
        self::$cachedStates = null;
        self::$cachedStateRules = null;
        self::$cachedGatheringStates = null;
    }

    /**
     * Get the branch ID from the associated award.
     *
     * @return int|null Branch ID or null if not determinable
     */
    public function getBranchId(): ?int
    {
        if ($this->award) {
            return $this->award->branch_id;
        }

        if ($this->award_id == null) {
            return null;
        }
        $awardTbl = TableRegistry::getTableLocator()->get('Awards.Awards');
        $award = $awardTbl->find()
            ->where(['id' => $this->award_id])
            ->select('branch_id')
            ->first();
        if ($award) {
            return $award->branch_id;
        }

        return null;
    }

    /**
     * Whether this recommendation is locked because it is linked to a bestowal.
     *
     * Linked recommendations are read-only in the recommendation UI; changes flow
     * through the bestowal workflow until the link is cleared (e.g. cancellation).
     *
     * @return bool
     */
    public function isLockedByBestowal(): bool
    {
        return $this->bestowal_id !== null && (int)$this->bestowal_id > 0;
    }

    /**
     * Whether this recommendation is a group head (has children grouped under it).
     *
     * @return bool
     */
    public function isGroupHead(): bool
    {
        return $this->recommendation_group_id === null
            && isset($this->group_children_count)
            && $this->group_children_count > 0;
    }

    /**
     * Whether this recommendation is a child in a group.
     *
     * @return bool
     */
    public function isGroupChild(): bool
    {
        return $this->recommendation_group_id !== null;
    }
}
