<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * GridViewPreference Entity - Stores member-specific grid default selections
 *
 * Each record links a member + grid key to the preferred grid view (which may be
 * either a system-provided view or the member's own saved configuration). This
 * allows users to adopt system views as their personal default without altering
 * the underlying system definition.
 *
 * @property int $id
 * @property int $member_id
 * @property string $grid_key
 * @property int|null $grid_view_id
 * @property string|null $grid_view_key
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \App\Model\Entity\Member $member
 * @property \App\Model\Entity\GridView|null $grid_view
 */
class GridViewPreference extends Entity
{
    /**
     * Mass assignment configuration.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'member_id' => true,
        'grid_key' => true,
        'grid_view_id' => true,
        'grid_view_key' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
        'member' => true,
        'grid_view' => true,
    ];
}
