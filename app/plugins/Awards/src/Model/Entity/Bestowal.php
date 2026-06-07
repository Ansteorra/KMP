<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;

/**
 * Bestowal Entity - Award presentation workflow with state machine.
 *
 * Implements dual status/state tracking:
 * - Status: high-level category (Planning, Preparation, Scheduling, Ready, Closed)
 * - State: specific workflow position within the status category
 *
 * @property int $id
 * @property int $member_id
 * @property int|null $gathering_id
 * @property int|null $gathering_scheduled_activity_id
 * @property bool $roaming_court
 * @property int|null $primary_recommendation_id
 * @property int|null $award_id
 * @property string $status
 * @property string $state
 * @property \Cake\I18n\DateTime|null $state_date
 * @property int $stack_rank
 * @property \Cake\I18n\DateTime|null $bestowed_at
 * @property string $source
 * @property int|null $source_approval_run_id
 * @property string|null $noble_notes
 * @property string|null $herald_notes
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
 * @property \App\Model\Entity\Gathering|null $gathering
 * @property \App\Model\Entity\GatheringScheduledActivity|null $gathering_scheduled_activity
 * @property \Awards\Model\Entity\Recommendation|null $primary_recommendation
 * @property \Awards\Model\Entity\Award|null $award
 * @property \Awards\Model\Entity\Recommendation[] $recommendations
 * @property \Awards\Model\Entity\BestowalRecommendation[] $bestowal_recommendations
 * @property \Awards\Model\Entity\BestowalsStatesLog[] $bestowal_state_logs
 */
class Bestowal extends BaseEntity
{
    public const SOURCE_RECOMMENDATION = 'recommendation';

    public const SOURCE_AD_HOC = 'ad_hoc';

    /** Terminal states where the bestowal lifecycle is complete. */
    public const TERMINAL_STATES = ['Given', 'Cancelled'];

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
        'member_id' => true,
        'gathering_id' => true,
        'gathering_scheduled_activity_id' => true,
        'roaming_court' => true,
        'primary_recommendation_id' => true,
        'award_id' => true,
        'status' => true,
        'state' => true,
        'state_date' => true,
        'stack_rank' => true,
        'bestowed_at' => true,
        'source' => true,
        'source_approval_run_id' => true,
        'noble_notes' => true,
        'herald_notes' => true,
        'call_into_court' => true,
        'court_availability' => true,
        'person_to_notify' => true,
        'close_reason' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'member' => true,
        'gathering' => true,
        'gathering_scheduled_activity' => true,
        'primary_recommendation' => true,
        'award' => true,
        'recommendations' => true,
        'bestowal_recommendations' => true,
    ];

    /**
     * Handle date format conversion for bestowed_at.
     *
     * @param mixed $value Date value
     * @return \DateTime|\Cake\I18n\DateTime|null
     */
    protected function _setBestowedAt(mixed $value): \DateTime|DateTime|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = new \DateTime($value);
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
    protected function _setState(string $value): string
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
            $this->gathering_scheduled_activity_id = null;
            $this->roaming_court = false;
        }

        return $value;
    }

    /**
     * Whether this bestowal is still active (not in a terminal state).
     *
     * @return bool
     */
    public function isActiveBestowal(): bool
    {
        return !in_array((string)$this->state, self::TERMINAL_STATES, true);
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

        $statusesTable = TableRegistry::getTableLocator()->get('Awards.BestowalStatuses');
        $statuses = $statusesTable->find()
            ->contain(['BestowalStates' => function ($q) {
                return $q->orderBy(['BestowalStates.sort_order' => 'ASC']);
            }])
            ->orderBy(['BestowalStatuses.sort_order' => 'ASC'])
            ->all();

        $result = [];
        foreach ($statuses as $status) {
            $result[$status->name] = [];
            foreach ($status->bestowal_states as $state) {
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
    public static function getStates(?string $status = null): array
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

        $statesTable = TableRegistry::getTableLocator()->get('Awards.BestowalStates');
        $states = $statesTable->find()
            ->contain(['BestowalStateFieldRules'])
            ->all();

        $rules = [];
        foreach ($states as $state) {
            if (empty($state->bestowal_state_field_rules)) {
                continue;
            }
            $stateRules = [];
            foreach ($state->bestowal_state_field_rules as $rule) {
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
        $statesTable = TableRegistry::getTableLocator()->get('Awards.BestowalStates');
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
     * Determine whether a bestowal state supports a scheduled gathering assignment.
     *
     * @param string $state Workflow state name.
     * @return bool
     */
    public static function supportsGatheringAssignmentForState(string $state): bool
    {
        if (self::$cachedGatheringStates === null) {
            $statesTable = TableRegistry::getTableLocator()->get('Awards.BestowalStates');
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
        $statesTable = TableRegistry::getTableLocator()->get('Awards.BestowalStates');
        $fromStateEntity = $statesTable->find()
            ->where(['name' => $fromState])
            ->first();

        if (!$fromStateEntity) {
            return [];
        }

        $transitionsTable = TableRegistry::getTableLocator()->get('Awards.BestowalStateTransitions');
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
}
