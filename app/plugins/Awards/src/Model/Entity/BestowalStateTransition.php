<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * BestowalStateTransition Entity - Valid bestowal state-to-state transition.
 *
 * @property int $id
 * @property int $from_state_id
 * @property int $to_state_id
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \Awards\Model\Entity\BestowalState $from_state
 * @property \Awards\Model\Entity\BestowalState $to_state
 */
class BestowalStateTransition extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'from_state_id' => true,
        'to_state_id' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
    ];
}
