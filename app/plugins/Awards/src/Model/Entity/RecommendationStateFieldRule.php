<?php

declare(strict_types=1);

namespace Awards\Model\Entity;

use App\Model\Entity\BaseEntity;

/**
 * RecommendationStateFieldRule Entity - Field visibility/requirement rules per state.
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
 * @property \Awards\Model\Entity\RecommendationState $recommendation_state
 */
class RecommendationStateFieldRule extends BaseEntity
{
    /**
     * Valid field targets that can be used in state rules.
     * Keys are the Stimulus target identifiers, values are human-readable labels.
     */
    public const FIELD_TARGET_OPTIONS = [
        'domainTarget' => 'Domain',
        'awardTarget' => 'Award',
        'specialtyTarget' => 'Specialty',
        'scaMemberTarget' => 'SCA Member',
        'branchTarget' => 'Branch',
        'planToGiveBlockTarget' => 'Plan to Give Section',
        'planToGiveEventTarget' => 'Plan to Give Event',
        'givenBlockTarget' => 'Given Section',
        'givenDateTarget' => 'Given Date',
        'closeReasonTarget' => 'Close Reason',
        'closeReasonBlockTarget' => 'Close Reason Section',
        'courtAvailabilityTarget' => 'Court Availability',
        'callIntoCourtTarget' => 'Call Into Court',
        'close_reason' => 'Close Reason (Set Value)',
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
