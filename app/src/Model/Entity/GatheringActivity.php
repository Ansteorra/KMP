<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * GatheringActivity Entity
 *
 * Represents an activity at a gathering (e.g., Heavy Combat, Archery, A&S Display).
 * Activities can have associated waiver requirements.
 *
 * @property int $id
 * @property int $gathering_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_circle
 * @property string $icon_source
 * @property string $icon_name
 * @property string|null $icon_asset_key
 * @property string $foreground_color
 * @property string $background_color
 * @property array<\App\Model\Entity\Permission> $assignment_permissions
 * @property string|null $instructions
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 *
 * @property \App\Model\Entity\Gathering $gathering
 * @property \Waivers\Model\Entity\GatheringActivityWaiver[] $gathering_activity_waivers
 */
class GatheringActivity extends BaseEntity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'is_circle' => true,
        'icon_source' => true,
        'icon_name' => true,
        'foreground_color' => true,
        'background_color' => true,
        'icon_document_id' => false,
        'icon_asset_key' => false,
        'assignment_permissions' => false,
        'created' => true,
        'modified' => true,
    ];
}
