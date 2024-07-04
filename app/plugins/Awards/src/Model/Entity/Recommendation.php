<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;

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


    const STATUS_SUBMITTED = "submitted";
    const STATUS_IN_CONSIDERATION = "in consideration";
    const STATUS_AWAITING_FEEDBACK = "awaiting feedback";
    const STATUS_DECLINED = "declined";
    const STATUS_NEED_TO_SCHEDULE = "scheduling";
    const STATUS_SCHEDULED = "scheduled";
    const STATUS_GIVEN = "given";

    public static function getStatues(): array
    {
        return [
            self::STATUS_SUBMITTED => "Submitted",
            self::STATUS_IN_CONSIDERATION => "In Consideration",
            self::STATUS_AWAITING_FEEDBACK => "Awaiting Feedback",
            self::STATUS_DECLINED => "Declined",
            self::STATUS_NEED_TO_SCHEDULE => "Need to Schedule",
            self::STATUS_SCHEDULED => "Scheduled",
            self::STATUS_GIVEN => "Given",
        ];
    }

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
        'status' => true,
        'status_date' => true,
        'stack_rank' => true,
        'requester_sca_name' => true,
        'member_sca_name' => true,
        'contact_number' => true,
        'contact_email' => true,
        'reason' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'member' => true,
        'events' => true,
    ];

    protected function _setStatus($value)
    {
        //the status must be one of the constants defined in this class
        switch ($value) {
            case self::STATUS_SUBMITTED:
            case self::STATUS_IN_CONSIDERATION:
            case self::STATUS_AWAITING_FEEDBACK:
            case self::STATUS_DECLINED:
            case self::STATUS_NEED_TO_SCHEDULE:
            case self::STATUS_SCHEDULED:
            case self::STATUS_GIVEN:
                return $value;
            default:
                throw new \InvalidArgumentException("Invalid status");
        }
    }
}