<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * Award Entity - Individual award configuration with hierarchical organization.
 *
 * Awards are organized by Domain (category), Level (precedence), and Branch (scope).
 *
 * @property int $id
 * @property string $name
 * @property string|null $abbreviation
 * @property string|null $specialties
 * @property string|null $description
 * @property string|null $insignia
 * @property string|null $badge
 * @property string|null $charter
 * @property int $domain_id
 * @property int $level_id
 * @property int $branch_id
 * @property bool $is_active
 * @property bool $disabled
 * @property string $disabled_label
 * @property \Cake\I18n\DateTime|null $open_date
 * @property \Cake\I18n\DateTime|null $close_date
 * @property \Cake\I18n\DateTime|null $modified
 * @property \Cake\I18n\DateTime $created
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Cake\I18n\DateTime|null $deleted
 *
 * @property \Awards\Model\Entity\Domain $awards_domain
 * @property \Awards\Model\Entity\Level $awards_level
 * @property \App\Model\Entity\Branch $branch
 * @property \App\Model\Entity\GatheringActivity[] $gathering_activities
 */
class Award extends BaseEntity
{
    /**
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
        'is_active' => true,
        'modified' => true,
        'created' => true,
        'created_by' => true,
        'modified_by' => true,
        'deleted' => true,
        'awards_domain' => true,
        'awards_level' => true,
        'branch' => true,
        'gathering_activities' => true,
    ];

    /**
     * Get the domain name for grid display
     *
     * @return string|null
     */
    protected function _getDomainName(): ?string
    {
        return $this->domain?->name ?? null;
    }

    /**
     * Get the level name for grid display
     *
     * @return string|null
     */
    protected function _getLevelName(): ?string
    {
        return $this->level?->name ?? null;
    }

    /**
     * Get the branch name for grid display
     *
     * @return string|null
     */
    protected function _getBranchName(): ?string
    {
        return $this->branch?->name ?? null;
    }

    /**
     * Get disabled status for grid and form display.
     *
     * @return bool
     */
    protected function _getDisabled(): bool
    {
        return !((bool)($this->is_active ?? true));
    }

    /**
     * Get disabled status label for grid display.
     *
     * @return string
     */
    protected function _getDisabledLabel(): string
    {
        return $this->disabled ? (string)__('Yes') : (string)__('No');
    }
}
