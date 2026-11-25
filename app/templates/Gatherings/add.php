<?php
declare(strict_types=1);

/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Gathering $gathering */

$this->extend('/layout/TwitterBootstrap/dashboard');
$this->assign('title', __('Add Gathering'));

$steps = [__('Basics'), __('When and where'), __('Public event page'), __('Review')];
$fieldsByStep = [
    ['name', 'branch_id', 'gathering_type_id'],
    ['start_date', 'end_date', 'location', 'timezone', 'latitude', 'longitude'],
    [
        'website_destination', 'website_url', 'public_page_enabled', 'description',
        'preregister_url', 'preregister_closes_on',
    ],
];
$errors = $gathering->getErrors();
$errorStep = 0;
foreach ($fieldsByStep as $index => $fields) {
    if (array_intersect($fields, array_keys($errors))) {
        $errorStep = $index + 1;
        break;
    }
}
?>

<div class="gatherings form content">
    <h1 class="h3 mb-3"><?= $this->element('backButton') ?> <?= __('Add Gathering') ?></h1>

    <?= $this->Form->create($gathering, [
        'data-controller' => 'gathering-form gathering-wizard',
        'data-action' => 'submit->gathering-wizard#submit change->gathering-form#branchChanged',
        'data-gathering-form-types-by-branch-value' => json_encode($gatheringTypesByBranch),
        'data-gathering-wizard-error-step-value' => $errorStep,
        'data-gathering-wizard-empty-value' => __('Not supplied'),
        'data-gathering-wizard-yes-value' => __('Yes'),
        'data-gathering-wizard-no-value' => __('No'),
        'data-gathering-wizard-end-error-value' => __('End date and time must be on or after the start.'),
        'data-gathering-wizard-hosting-group-error-value' => __('Select a hosting group from the list.'),
    ]) ?>
    <?= $this->element('wizard/steps', [
        'label' => __('Gathering creation steps'),
        'steps' => $steps,
        'panelIdPrefix' => 'gathering-wizard-step-',
        'controller' => 'gathering-wizard',
    ]) ?>

    <section class="border rounded p-3 mb-3" id="gathering-wizard-step-1" aria-labelledby="gathering-basics"
        data-gathering-wizard-target="panel">
        <h2 id="gathering-basics" class="h5" tabindex="-1"><?= __('What are you planning?') ?></h2>
        <?= $this->Form->control('name', [
            'required' => true,
            'placeholder' => __('e.g., Spring Fighter Practice'),
        ]) ?>
        <div class="row">
            <div class="col-md-6" data-gathering-form-target="branch">
                <div data-gathering-form-target="branchSelect">
                    <?= $this->Form->control('branch_id', [
                        'options' => $branches,
                        'empty' => $lockBranch ? false : __('Select a hosting group'),
                        'required' => true,
                        'disabled' => $lockBranch,
                        'label' => __('Hosting group'),
                    ]) ?>
                </div>
                <?php if ($lockBranch) : ?>
                    <?= $this->Form->hidden('branch_id', ['value' => $gathering->branch_id]) ?>
                <?php elseif ($branchCount > 1) : ?>
                    <?php
                    $comboAttributes = [
                        'idPrefix' => 'gathering-host',
                        'data-action' => 'ready->gathering-form#activateBranchAutocomplete',
                    ];
                    $selectedBranch = $gathering->branch_id;
                    if (isset($branches[$selectedBranch])) {
                        $comboAttributes['data-ac-init-selection-value'] = json_encode([
                            'value' => $selectedBranch,
                            'text' => $branches[$selectedBranch],
                        ]);
                    }
                    ?>
                    <fieldset data-gathering-form-target="branchAutocomplete" disabled hidden>
                        <?= $this->KMP->comboBoxControl(
                            $this->Form,
                            'branch_name',
                            'branch_id',
                            $branches,
                            __('Hosting group'),
                            true,
                            false,
                            $comboAttributes,
                        ) ?>
                    </fieldset>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('gathering_type_id', [
                    'options' => $gatheringTypes,
                    'empty' => __('Select a gathering type'),
                    'required' => true,
                    'data-gathering-form-target' => 'gatheringTypeSelect',
                    'aria-describedby' => 'gathering-type-availability gathering-type-description'
                        . ($gathering->getError('gathering_type_id') ? ' gathering-type-id-error' : ''),
                    'aria-invalid' => $gathering->getError('gathering_type_id') ? 'true' : 'false',
                    'data-action' => 'change->gathering-form#gatheringTypeChanged',
                ]) ?>
                <p id="gathering-type-availability" class="form-text"
                    role="status" data-gathering-form-target="typeAvailability">
                    <?= __('The gathering type provides its default activities and waiver requirements.') ?>
                </p>
                <p id="gathering-type-description" class="form-text d-none"
                    data-gathering-form-target="gatheringTypeDescription"></p>
            </div>
        </div>
    </section>

    <section class="border rounded p-3 mb-3" id="gathering-wizard-step-2" aria-labelledby="gathering-when-where"
        data-gathering-wizard-target="panel">
        <h2 id="gathering-when-where" class="h5" tabindex="-1"><?= __('When and where will it happen?') ?></h2>
        <div class="row">
            <div class="col-md-6">
                <?= $this->Form->control('start_date', [
                    'type' => 'datetime-local',
                    'required' => true,
                    'data-gathering-form-target' => 'startDate',
                    'data-action' => 'change->gathering-form#startDateChanged',
                    'label' => __('Start Date & Time'),
                ]) ?>
            </div>
            <div class="col-md-6">
                <?= $this->Form->control('end_date', [
                    'type' => 'datetime-local',
                    'data-gathering-form-target' => 'endDate',
                    'data-action' => 'change->gathering-form#endDateChanged',
                    'label' => __('End Date & Time'),
                    'help' => __('Leave blank to use the start time.'),
                ]) ?>
            </div>
        </div>
        <?= $this->Form->control('timezone', [
            'type' => 'select',
            'options' => $this->Timezone->getTimezoneOptions(),
            'empty' => __('Use your timezone ({0})', $this->Timezone->getUserTimezone()),
            'label' => __('Event Timezone'),
            'help' => __('Choose the timezone where the gathering takes place.'),
        ]) ?>
        <?= $this->element('gatherings/location_field') ?>
    </section>

    <?= $this->element('gatherings/public_details_form', ['guided' => true]) ?>

    <section class="border rounded p-3 mb-3" id="gathering-wizard-step-4" aria-labelledby="gathering-review"
        data-gathering-wizard-target="panel" hidden>
        <h2 id="gathering-review" class="h5" tabindex="-1"><?= __('Review your gathering') ?></h2>
        <dl class="row">
            <?php
            $reviewFields = [
                'name' => __('Name'), 'branch_id' => __('Hosting group'), 'gathering_type_id' => __('Gathering type'),
                'start_date' => __('Start'), 'end_date' => __('End'), 'timezone' => __('Event timezone'),
                'location' => __('Location'), 'website_destination' => __('Calendar link'),
                'public_page_enabled' => __('KMP public page'),
                'description' => __('Description'), 'preregister_url' => __('Pre-registration'),
                'preregister_closes_on' => __('Pre-registration closes'),
            ];
            foreach ($reviewFields as $field => $label) :
                ?>
                <dt class="col-sm-4"><?= h($label) ?></dt>
                <dd class="col-sm-8 text-break text-wrap" data-gathering-wizard-target="review"
                    data-field="<?= h($field) ?>"></dd>
            <?php endforeach; ?>
        </dl>
        <p class="mb-0"><?= __('After creating the gathering, you can add staff, activities, and a schedule. '
            . 'Publishing it on the kingdom calendar is a separate step.') ?></p>
    </section>

    <?= $this->element('wizard/actions', [
        'controller' => 'gathering-wizard',
        'cancelUrl' => ['action' => 'index'],
        'submitLabel' => __('Create Gathering'),
        'submitOptions' => ['data-gathering-form-target' => 'submitButton'],
    ]) ?>
    <?= $this->Form->end() ?>
</div>
