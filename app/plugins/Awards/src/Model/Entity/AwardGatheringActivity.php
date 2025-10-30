<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;

/**
 * AwardGatheringActivity Entity
 *
 * Join entity for the many-to-many relationship between Awards and GatheringActivities.
 * Represents the association between an award and a gathering activity during which
 * the award can be given out.
 *
 * @property int $id
 * @property int $award_id
 * @property int $gathering_activity_id
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \Awards\Model\Entity\Award $award
 * @property \App\Model\Entity\GatheringActivity $gathering_activity
 */
class AwardGatheringActivity extends Entity
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
        'award_id' => true,
        'gathering_activity_id' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'award' => true,
        'gathering_activity' => true,
    ];
}
