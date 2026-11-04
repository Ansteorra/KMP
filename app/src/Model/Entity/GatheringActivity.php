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
 * @property int $display_order
 * @property string $activity_type
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
    public const TYPE_ORDER_CIRCLE = 'Order Circle';

    public const TYPE_GENERAL = 'General';

    public const ACTIVITY_TYPES = [
        self::TYPE_ORDER_CIRCLE,
        'Royal Progress',
        'Court',
        'Business',
        'Martial',
        'Arts & Sciences',
        'Youth',
        self::TYPE_GENERAL,
        'Other',
    ];

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'description' => true,
        'activity_type' => true,
        'display_order' => true,
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
