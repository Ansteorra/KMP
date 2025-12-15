<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;
use App\Model\Entity\BaseEntity;
use Cake\ORM\TableRegistry;

/**
 * Recommendation Entity - Award recommendation workflow with state machine.
 *
 * Implements dual status/state tracking:
 * - Status: high-level category (In Progress, Scheduling, To Give, Closed)
 * - State: specific workflow position within the status category
 *
 * @property int $id
 * @property int $requester_id
 * @property int|null $member_id
 * @property int|null $branch_id
 * @property int $award_id
 * @property int|null $event_id
 * @property int|null $gathering_id
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
 */
class Recommendation extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'requester_id' => true,
        'stack_rank' => true,
        'member_id' => true,
        'branch_id' => true,
        'award_id' => true,
        'event_id' => true,  // Deprecated - kept for migration compatibility, use gathering_id instead
        'gathering_id' => true,
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
    protected function _setGiven($value)
    {
        if (is_string($value)) {
            $value = new \DateTime($value);
        }
        return $value;
    }

    /**
     * State machine setter - validates state and auto-updates status.
     *
     * Applies configuration-driven state rules from Awards.RecommendationStateRules.
     *
     * @param string $value New state value
     * @return string Validated state value
     * @throws \InvalidArgumentException When state is invalid
     */
    protected function _setState($value)
    {
        $this->beforeState = $this->state;
        $this->beforeStatus = $this->status;

        $states = self::getStates();
        if (!in_array($value, $states)) {
            throw new \InvalidArgumentException("Invalid State");
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
        $stateRules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");
        if (isset($stateRules[$value])) {
            $rule = $stateRules[$value];
            if (isset($rule['Set'])) {
                $fieldsToSet = $rule['Set'];
                foreach ($fieldsToSet as $field => $fieldValue) {
                    $this->$field = $fieldValue;
                }
            }
        }
        return $value;
    }

    /**
     * Get status categories and their state mappings from configuration.
     *
     * @return array Status configuration
     */
    public static function getStatuses(): array
    {
        $statusList = StaticHelpers::getAppSetting("Awards.RecommendationStatuses");
        return $statusList;
    }

    /**
     * Get valid states, optionally filtered by status.
     *
     * @param string|null $status Optional status filter
     * @return array Valid states list
     */
    public static function getStates($status = null): array
    {
        if ($status) {
            $statusList = self::getStatuses();
            return $statusList[$status];
        }
        $statuses = self::getStatuses();
        $states = [];
        foreach ($statuses as $status) {
            foreach ($status as $state) {
                $states[] = $state;
            }
        }
        return $states;
    }

    /**
     * Get the branch ID from the associated award.
     *
     * @return int|null Branch ID or null if not determinable
     */
    public function getBranchId(): ?int
    {
        if ($this->award)
            return $this->award->branch_id;

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
}
