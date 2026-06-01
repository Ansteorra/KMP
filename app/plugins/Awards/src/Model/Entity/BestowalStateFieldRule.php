<?php
declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * BestowalStateFieldRule Entity - Field visibility/requirement rules per bestowal state.
 *
 * @property int $id
 * @property int $state_id
 * @property string $field_target
 * @property string $rule_type
 * @property string|null $rule_value
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property int|null $created_by
 * @property int|null $modified_by
 *
 * @property \Awards\Model\Entity\BestowalState $bestowal_state
 */
class BestowalStateFieldRule extends BaseEntity
{
    /**
     * Valid field targets that can be used in bestowal state rules.
     */
    public const FIELD_TARGET_OPTIONS = [
        'gathering_id' => 'Gathering',
        'gathering_scheduled_activity_id' => 'Court session',
        'bestowed_at' => 'Bestowed At',
        'close_reason' => 'Close Reason',
        'noble_notes' => 'Noble Notes',
        'herald_notes' => 'Herald Notes',
        'call_into_court' => 'Call Into Court',
        'court_availability' => 'Court Availability',
        'person_to_notify' => 'Person to Notify',
    ];

    /**
     * Valid rule types.
     */
    public const RULE_TYPE_OPTIONS = [
        'Visible' => 'Visible',
        'Optional' => 'Optional (visible, not required)',
        'Required' => 'Required',
        'Disabled' => 'Disabled',
        'Set' => 'Set',
    ];

    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'state_id' => true,
        'field_target' => true,
        'rule_type' => true,
        'rule_value' => true,
        'created' => true,
        'modified' => true,
        'created_by' => true,
        'modified_by' => true,
    ];
}
