<?php
declare(strict_types=1);

namespace App\Model\Entity;

/**
 * ActionItemLog Entity - append-only audit of action item status changes.
 *
 * @property int $id
 * @property int $action_item_id
 * @property string $from_status
 * @property string $to_status
 * @property string|null $note
 * @property \Cake\I18n\DateTime|null $created
 * @property int|null $created_by
 *
 * @property \App\Model\Entity\ActionItem $action_item
 */
class ActionItemLog extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'action_item_id' => true,
        'from_status' => true,
        'to_status' => true,
        'note' => true,
        'created' => true,
        'created_by' => true,
        'action_item' => true,
    ];
}
