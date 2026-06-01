<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\BestowalState $state
 * @var int $bestowalCount
 * @var \Awards\Model\Entity\BestowalState[] $allStates
 * @var array $transitionTargetIds
 * @var array $fieldTargetOptions
 * @var array $ruleTypeOptions
 * @var array<int, string> $recommendationStateOptions
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Bestowal State - ' . $state->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($state->name) ?>
<span class="badge bg-info"><?= h($state->bestowal_status->name) ?></span>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($state->is_system) : ?>
<span class="badge bg-warning text-dark"><i class="bi bi-lock-fill"></i> <?= __('System State') ?></span>
<?php else : ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php if ($bestowalCount === 0) {
    echo $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $state->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $state->name,
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    );
} else { ?>
    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#transferDeleteModal">Delete</button>
<?php } ?>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<dt><?= __('Status') ?></dt>
<dd><?= $this->Html->link(
    h($state->bestowal_status->name),
    ['controller' => 'BestowalStatuses', 'action' => 'view', $state->bestowal_status->id]
) ?></dd>
<dt><?= __('Sort Order') ?></dt>
<dd><?= h($state->sort_order) ?></dd>
<dt><?= __('Supports Gathering') ?></dt>
<dd><?= $this->KMP->bool($state->supports_gathering, $this->Html) ?></dd>
<dt><?= __('Locks Recommendations') ?></dt>
<dd><?= $this->KMP->bool($state->locks_recommendations, $this->Html) ?></dd>
<dt><?= __('Hidden') ?></dt>
<dd><?= $this->KMP->bool($state->is_hidden, $this->Html) ?></dd>
<dt><?= __('System State') ?></dt>
<dd><?= $this->KMP->bool($state->is_system, $this->Html) ?></dd>
<dt><?= __('Sync Recommendation State') ?></dt>
<dd><?php if ($state->sync_recommendation_state) {
    echo $this->Html->link(
        h($state->sync_recommendation_state->name),
        ['controller' => 'RecommendationStates', 'action' => 'view', $state->sync_recommendation_state->id],
    );
} else {
    echo '<span class="text-muted">—</span>';
} ?></dd>
<dt><?= __('Unwind Recommendation State') ?></dt>
<dd><?php if ($state->unwind_recommendation_state) {
    echo $this->Html->link(
        h($state->unwind_recommendation_state->name),
        ['controller' => 'RecommendationStates', 'action' => 'view', $state->unwind_recommendation_state->id],
    );
} else {
    echo '<span class="text-muted">—</span>';
} ?></dd>
<dt><?= __('Bestowals') ?></dt>
<dd><?= $bestowalCount ?></dd>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-field-rules-tab" data-bs-toggle="tab" data-bs-target="#nav-field-rules"
    type="button" role="tab" aria-controls="nav-field-rules" aria-selected="false"
    data-detail-tabs-target='tabBtn'
    data-tab-order="10"
    style="order: 10;"><?= __("Field Rules") ?> <span class="badge bg-secondary"><?= count($state->bestowal_state_field_rules) ?></span>
</button>
<button class="nav-link" id="nav-transitions-tab" data-bs-toggle="tab" data-bs-target="#nav-transitions"
    type="button" role="tab" aria-controls="nav-transitions" aria-selected="false"
    data-detail-tabs-target='tabBtn'
    data-tab-order="20"
    style="order: 20;"><?= __("Transitions") ?> <span class="badge bg-secondary"><?= count($state->outgoing_transitions) ?></span>
</button>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade active m-3" id="nav-field-rules" role="tabpanel"
    aria-labelledby="nav-field-rules-tab" data-detail-tabs-target="tabContent"
    data-tab-order="10"
    style="order: 10;">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h5 class="mb-0"><?= __('Field Rules') ?></h5>
            <small class="text-muted"><?= __('Configure how form fields behave when a bestowal is in this state.') ?></small>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldRuleModal">
            <i class="bi bi-plus-circle"></i> <?= __('Add Rule') ?>
        </button>
    </div>

    <?php if (!empty($state->bestowal_state_field_rules)) { ?>
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th><?= __('Field Target') ?></th>
                        <th><?= __('Rule Type') ?></th>
                        <th><?= __('Value') ?></th>
                        <th class="actions text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($state->bestowal_state_field_rules as $rule) : ?>
                        <tr>
                            <td><?= h($fieldTargetOptions[$rule->field_target] ?? $rule->field_target) ?></td>
                            <td>
                                <?php
                                $badgeClass = match ($rule->rule_type) {
                                    'Required' => 'bg-danger',
                                    'Optional' => 'bg-info text-dark',
                                    'Visible' => 'bg-success',
                                    'Disabled' => 'bg-warning text-dark',
                                    'Set' => 'bg-primary',
                                    default => 'bg-secondary',
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= h($rule->rule_type) ?></span>
                            </td>
                            <td><?= $rule->rule_value ? h($rule->rule_value) : '<span class="text-muted">—</span>' ?></td>
                            <td class="actions text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-secondary bi bi-pencil-fill"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editFieldRuleModal-<?= $rule->id ?>"
                                    title="<?= __('Edit') ?>"></button>
                                <?= $this->Form->postLink(
                                    __(""),
                                    ["action" => "deleteFieldRule", $rule->id],
                                    [
                                        "confirm" => __("Remove this field rule?"),
                                        "title" => __("Remove"),
                                        "class" => "btn-sm btn btn-outline-danger bi bi-trash-fill",
                                    ],
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <p class="text-muted"><?= __('No field rules configured for this state.') ?></p>
    <?php } ?>
</div>

<div class="related tab-pane fade m-3" id="nav-transitions" role="tabpanel"
    aria-labelledby="nav-transitions-tab" data-detail-tabs-target="tabContent"
    data-tab-order="20"
    style="order: 20;">

    <h5><?= __('Allowed Transitions') ?></h5>
    <p class="text-muted"><?= __('Select which states a bestowal can move to from this state.') ?></p>

    <?= $this->Form->create(null, [
        'url' => ['action' => 'saveTransitions', $state->id],
    ]) ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th><?= __('Target State') ?></th>
                    <th><?= __('Status') ?></th>
                    <th class="text-center"><?= __('Allowed') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allStates as $targetState) : ?>
                    <?php if ($targetState->id === $state->id) {
                        continue;
                    } ?>
                    <tr>
                        <td><?= h($targetState->name) ?></td>
                        <td><span class="badge bg-secondary"><?= h($targetState->bestowal_status->name) ?></span></td>
                        <td class="text-center">
                            <?= $this->Form->checkbox('transition_targets[]', [
                                'value' => $targetState->id,
                                'checked' => isset($transitionTargetIds[$targetState->id]),
                                'hiddenField' => false,
                                'class' => 'form-check-input',
                            ]) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="text-end">
        <?= $this->Form->button(__('Save Transitions'), ['class' => 'btn btn-primary']) ?>
    </div>
    <?= $this->Form->end() ?>
</div>
<?php $this->KMP->endBlock() ?>

<?php
echo $this->KMP->startBlock("modals");

echo $this->Form->create($state, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "BestowalStates",
        "action" => "edit",
        $state->id,
    ],
]);

echo $this->Modal->create("Edit Bestowal State", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    $statusOptions = [];
    foreach ($allStates as $s) {
        if (!isset($statusOptions[$s->bestowal_status->id])) {
            $statusOptions[$s->bestowal_status->id] = $s->bestowal_status->name;
        }
    }
    echo $this->Form->control("name");
    echo $this->Form->control("status_id", ['options' => $statusOptions]);
    echo $this->Form->control("sort_order", ['type' => 'number']);
    echo $this->Form->control("supports_gathering", ['type' => 'checkbox', 'switch' => true, 'label' => __('Supports Gathering Assignment')]);
    echo $this->Form->control("locks_recommendations", ['type' => 'checkbox', 'switch' => true, 'label' => __('Locks Linked Recommendations')]);
    echo $this->Form->control("is_hidden", ['type' => 'checkbox', 'switch' => true, 'label' => __('Hidden')]);
    echo $this->Form->control('sync_recommendation_state_id', [
        'label' => __('Sync Recommendation State'),
        'options' => $recommendationStateOptions,
        'empty' => '-- None --',
    ]);
    echo $this->Form->control('unwind_recommendation_state_id', [
        'label' => __('Unwind Recommendation State'),
        'options' => $recommendationStateOptions,
        'empty' => '-- None --',
    ]);
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit"
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();

echo $this->Form->create(null, [
    "id" => "add_field_rule_form",
    "url" => [
        "controller" => "BestowalStates",
        "action" => "addFieldRule",
        $state->id,
    ],
]);
echo $this->Modal->create(__("Add Field Rule"), [
    "id" => "addFieldRuleModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("field_target", [
        'label' => __('Field Target'),
        'type' => 'select',
        'options' => $fieldTargetOptions,
        'empty' => '-- ' . __('Select Field') . ' --',
        'required' => true,
    ]);
    echo $this->Form->control("rule_type", [
        'label' => __('Rule Type'),
        'type' => 'select',
        'options' => $ruleTypeOptions,
        'required' => true,
    ]);
    echo $this->Form->control("rule_value", [
        'label' => __('Value (for Set rules)'),
        'required' => false,
    ]);
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button(__("Add Rule"), [
        "class" => "btn btn-primary",
    ]),
    $this->Form->button(__("Close"), [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();

foreach ($state->bestowal_state_field_rules as $rule) {
    echo $this->Form->create($rule, [
        "id" => "edit_field_rule_form_" . $rule->id,
        "url" => [
            "controller" => "BestowalStates",
            "action" => "editFieldRule",
            $rule->id,
        ],
    ]);
    echo $this->Modal->create(__("Edit Field Rule"), [
        "id" => "editFieldRuleModal-" . $rule->id,
        "close" => true,
    ]);
    ?>
    <fieldset>
        <?php
        echo $this->Form->control("field_target", [
            'label' => __('Field Target'),
            'type' => 'select',
            'options' => $fieldTargetOptions,
            'required' => true,
        ]);
        echo $this->Form->control("rule_type", [
            'label' => __('Rule Type'),
            'type' => 'select',
            'options' => $ruleTypeOptions,
            'required' => true,
        ]);
        echo $this->Form->control("rule_value", [
            'label' => __('Value (for Set rules)'),
            'required' => false,
        ]);
        ?>
    </fieldset>
    <?php echo $this->Modal->end([
        $this->Form->button(__("Save"), [
            "class" => "btn btn-primary",
        ]),
        $this->Form->button(__("Close"), [
            "data-bs-dismiss" => "modal",
            "type" => "button",
        ]),
    ]);
    echo $this->Form->end();
}

if ($bestowalCount > 0) {
    echo $this->Form->create(null, [
        "id" => "transfer_delete_form",
        "url" => [
            "controller" => "BestowalStates",
            "action" => "delete",
            $state->id,
        ],
    ]);

    echo $this->Modal->create("Transfer & Delete State", [
        "id" => "transferDeleteModal",
        "close" => true,
    ]);
    ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= __('This state has {0} bestowal(s). They must be transferred to another state before deletion.', $bestowalCount) ?>
    </div>
    <fieldset>
        <?php
        $targetOptions = [];
        foreach ($allStates as $s) {
            if ($s->id !== $state->id) {
                $targetOptions[$s->id] = $s->name . ' (' . $s->bestowal_status->name . ')';
            }
        }
        echo $this->Form->control('target_state_id', [
            'label' => __('Transfer bestowals to:'),
            'options' => $targetOptions,
            'empty' => '-- Select Target State --',
            'required' => true,
        ]);
        ?>
    </fieldset>
    <?php echo $this->Modal->end([
        $this->Form->button("Transfer & Delete", [
            "class" => "btn btn-danger",
        ]),
        $this->Form->button("Cancel", [
            "data-bs-dismiss" => "modal",
            "type" => "button",
        ]),
    ]);
    echo $this->Form->end();
}

$this->KMP->endBlock(); ?>
