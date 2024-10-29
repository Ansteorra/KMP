<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;

/**
 * Recommendation Entity
 *
 * @property int $id
 * @property int $requester_id
 * @property int|null $member_id
 * @property int|null $branch_id
 * @property int $award_id
 * @property string $requester_sca_name
 * @property string $member_sca_name
 * @property string $contact_number
 * @property string|null $reason
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \Awards\Model\Entity\Member $member
 */
class Recommendation extends Entity
{


    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'requester_id' => true,
        'stack_rank' => true,
        'member_id' => true,
        'branch_id' => true,
        'award_id' => true,
        'event_id' => true,
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
        'person_to_notify' => true,
        'close_reason' => true,
    ];

    protected function _setGiven($value)
    {
        if (is_string($value)) {
            $value = new \DateTime($value);
        }
        return $value;
    }
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

    public static function getStatuses(): array
    {
        $statusList = StaticHelpers::getAppSetting("Awards.RecommendationStatuses");
        return $statusList;
    }
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
}