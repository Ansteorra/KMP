<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;

/**
 * Award Entity
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $insignia
 * @property string|null $badge
 * @property string|null $charter
 * @property int $domain_id
 * @property int $level_id
 * @property int $branch_id
 * @property \Cake\I18n\DateTime|null $open_date
 * @property \Cake\I18n\DateTime|null $close_date
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \Awards\Model\Entity\AwardsDomain $awards_domain
 * @property \Awards\Model\Entity\AwardsLevel $awards_level
 * @property \Awards\Model\Entity\Branch $branch
 */
class Award extends Entity
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
        'name' => true,
        'specialties' => true,
        'abbreviation' => true,
        'description' => true,
        'insignia' => true,
        'badge' => true,
        'charter' => true,
        'domain_id' => true,
        'level_id' => true,
        'branch_id' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'awards_domain' => true,
        'awards_level' => true,
        'branch' => true,
    ];
}