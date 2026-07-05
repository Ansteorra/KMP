<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use DateTime as NativeDateTime;

/**
 * Bestowal Entity - Award presentation workflow.
 *
 * Carries a minimal `lifecycle_status` (open|given|cancelled). Operational
 * progress (scroll prep, court scheduling, confirmation) is tracked separately
 * by the parallel to-do checklist subsystem.
 *
 * @property int $id
 * @property int|null $member_id
 * @property string $member_sca_name
 * @property int|null $gathering_id
 * @property int|null $gathering_scheduled_activity_id
 * @property bool $roaming_court
 * @property int|null $primary_recommendation_id
 * @property int|null $award_id
 * @property string|null $specialty
 * @property string $lifecycle_status
 * @property int $stack_rank
 * @property \Cake\I18n\DateTime|null $bestowed_at
 * @property string $source
 * @property int|null $source_approval_run_id
 * @property string|null $noble_notes
 * @property string|null $herald_notes
 * @property string|null $reason_summary
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
 */
class Bestowal extends BaseEntity
{
    public const SOURCE_RECOMMENDATION = 'recommendation';

    public const SOURCE_AD_HOC = 'ad_hoc';

    /** Polymorphic owner type used when materializing bestowal to-dos as ActionItems. */
    public const ACTION_ITEM_ENTITY_TYPE = 'Awards.Bestowals';

    /** Minimal lifecycle that replaces the legacy status/state machine. */
    public const LIFECYCLE_OPEN = 'open';

    public const LIFECYCLE_GIVEN = 'given';

    public const LIFECYCLE_CANCELLED = 'cancelled';

    public const LIFECYCLE_STATUSES = [
        self::LIFECYCLE_OPEN,
        self::LIFECYCLE_GIVEN,
        self::LIFECYCLE_CANCELLED,
    ];

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'member_id' => true,
        'member_sca_name' => true,
        'gathering_id' => true,
        'gathering_scheduled_activity_id' => true,
        'roaming_court' => true,
        'primary_recommendation_id' => true,
        'award_id' => true,
        'specialty' => true,
        'lifecycle_status' => true,
        'stack_rank' => true,
        'bestowed_at' => true,
        'source' => true,
        'source_approval_run_id' => true,
        'noble_notes' => true,
        'herald_notes' => true,
        'reason_summary' => true,
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
    protected function _setBestowedAt(mixed $value): NativeDateTime|DateTime|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = new NativeDateTime($value);
        }

        return $value;
    }

    /**
     * Whether this bestowal is still active (lifecycle still open).
     *
     * @return bool
     */
    public function isActiveBestowal(): bool
    {
        return ($this->lifecycle_status ?? self::LIFECYCLE_OPEN) === self::LIFECYCLE_OPEN;
    }

    /**
     * Get the branch ID from the associated award.
     *
     * @return int|null Branch ID or null if not determinable.
     */
    public function getBranchId(): ?int
    {
        if ($this->hasValue('award')) {
            return $this->award->branch_id !== null ? (int)$this->award->branch_id : null;
        }

        if ($this->award_id === null) {
            return null;
        }

        $award = TableRegistry::getTableLocator()->get('Awards.Awards')->find()
            ->select(['branch_id'])
            ->where(['id' => (int)$this->award_id])
            ->first();
        if ($award === null || $award->branch_id === null) {
            return null;
        }

        return (int)$award->branch_id;
    }
}
