<?php

declare(strict_types=1);

/**
 * Cumulative field rules for bestowal workflow states.
 *
 * Optional milestones show fields without requiring them; Required milestones
 * require fields on that state and all later main-path states. Required
 * supersedes Optional for the same field_target. Cancelled is separate.
 */

/** @var list<string> Main happy-path states in workflow order */
const BESTOWAL_LINEAR_PROGRESSION = [
    'Created',
    'Gathering Assigned',
    'Scroll Notified',
    'Scroll Ready',
    'Court Pending',
    'Court Scheduled',
    'Ready for Court',
    'Given',
];

/** @var list<string> */
const BESTOWAL_COURT_SCHEDULING_ONLY_STATES = [
    'Announced Not Given',
];

const BESTOWAL_CANCELLED_STATE = 'Cancelled';

/**
 * Fields that become visible (but optional) at each milestone.
 *
 * @return array<string, list<array{field_target: string, rule_type: string, rule_value: null}>>
 */
function bestowalOptionalFieldMilestones(): array
{
    return [
        'Court Scheduled' => [
            [
                'field_target' => 'gathering_id',
                'rule_type' => 'Optional',
                'rule_value' => null,
            ],
            [
                'field_target' => 'gathering_scheduled_activity_id',
                'rule_type' => 'Optional',
                'rule_value' => null,
            ],
        ],
    ];
}

/**
 * Fields that become required at each milestone.
 *
 * @return array<string, list<array{field_target: string, rule_type: string, rule_value: null}>>
 */
function bestowalRequiredFieldMilestones(): array
{
    return [
        'Given' => [
            [
                'field_target' => 'gathering_id',
                'rule_type' => 'Required',
                'rule_value' => null,
            ],
            [
                'field_target' => 'gathering_scheduled_activity_id',
                'rule_type' => 'Required',
                'rule_value' => null,
            ],
            [
                'field_target' => 'bestowed_at',
                'rule_type' => 'Required',
                'rule_value' => null,
            ],
        ],
    ];
}

/**
 * @return list<array{field_target: string, rule_type: string, rule_value: null}>
 */
function bestowalCancelledRequiredRules(): array
{
    return [
        [
            'field_target' => 'close_reason',
            'rule_type' => 'Required',
            'rule_value' => null,
        ],
    ];
}

/**
 * Merge optional and required milestones along a state list.
 *
 * Required fields replace optional entries with the same field_target.
 *
 * @param list<string> $stateNames
 * @param array<string, list<array{field_target: string, rule_type: string, rule_value: null}>> $optionalMilestones
 * @param array<string, list<array{field_target: string, rule_type: string, rule_value: null}>> $requiredMilestones
 * @return array<string, list<array{field_target: string, rule_type: string, rule_value: null}>>
 */
function bestowalCumulativeFieldRulesForStates(
    array $stateNames,
    array $optionalMilestones,
    array $requiredMilestones,
): array {
    $optionalCumulative = [];
    $requiredCumulative = [];
    $rulesByState = [];

    foreach ($stateNames as $stateName) {
        if (isset($optionalMilestones[$stateName])) {
            foreach ($optionalMilestones[$stateName] as $rule) {
                $optionalCumulative[$rule['field_target']] = $rule;
            }
        }
        if (isset($requiredMilestones[$stateName])) {
            foreach ($requiredMilestones[$stateName] as $rule) {
                $requiredCumulative[$rule['field_target']] = $rule;
                unset($optionalCumulative[$rule['field_target']]);
            }
        }
        $rulesByState[$stateName] = array_merge(
            array_values($optionalCumulative),
            array_values($requiredCumulative),
        );
    }

    return $rulesByState;
}

/**
 * @param array<string, int> $stateIdMap state name => id
 * @param \Migrations\Table $rulesTable phinx table wrapper
 * @param string $createdAt timestamp for created column
 * @return void
 */
function insertBestowalCumulativeFieldRules(array $stateIdMap, $rulesTable, string $createdAt): void
{
    $linearRules = bestowalCumulativeFieldRulesForStates(
        BESTOWAL_LINEAR_PROGRESSION,
        bestowalOptionalFieldMilestones(),
        bestowalRequiredFieldMilestones(),
    );

    $courtSchedulingOnly = bestowalCumulativeFieldRulesForStates(
        ['Court Scheduled'],
        bestowalOptionalFieldMilestones(),
        [],
    );
    $courtSchedulingRules = $courtSchedulingOnly['Court Scheduled'] ?? [];

    foreach (BESTOWAL_LINEAR_PROGRESSION as $stateName) {
        if (!isset($stateIdMap[$stateName], $linearRules[$stateName])) {
            continue;
        }
        foreach ($linearRules[$stateName] as $rule) {
            $rulesTable->insert([
                'state_id' => $stateIdMap[$stateName],
                'field_target' => $rule['field_target'],
                'rule_type' => $rule['rule_type'],
                'rule_value' => $rule['rule_value'],
                'created' => $createdAt,
            ]);
        }
    }

    foreach (BESTOWAL_COURT_SCHEDULING_ONLY_STATES as $stateName) {
        if (!isset($stateIdMap[$stateName])) {
            continue;
        }
        foreach ($courtSchedulingRules as $rule) {
            $rulesTable->insert([
                'state_id' => $stateIdMap[$stateName],
                'field_target' => $rule['field_target'],
                'rule_type' => $rule['rule_type'],
                'rule_value' => $rule['rule_value'],
                'created' => $createdAt,
            ]);
        }
    }

    if (isset($stateIdMap[BESTOWAL_CANCELLED_STATE])) {
        foreach (bestowalCancelledRequiredRules() as $rule) {
            $rulesTable->insert([
                'state_id' => $stateIdMap[BESTOWAL_CANCELLED_STATE],
                'field_target' => $rule['field_target'],
                'rule_type' => $rule['rule_type'],
                'rule_value' => $rule['rule_value'],
                'created' => $createdAt,
            ]);
        }
    }
}
