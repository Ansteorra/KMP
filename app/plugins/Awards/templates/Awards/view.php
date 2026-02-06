<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $award
 */
?>
<?php
$user = $this->request->getAttribute('identity');
$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Award Domain - ' . $award->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($award->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->checkCan("edit", $award)) : ?>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php endif; ?>
<?php if (empty($award->recommendations) && $user->checkCan("delete", $award)) {
    echo $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $award->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $award->name,
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    );
} ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __('Abbreviation') ?></th>
    <td> <?= h($award->abbreviation) ?></td>
</tr>
<?php if ($award->specialties) : ?>
    <tr>
        <th scope="row"><?= __('Specialties') ?></th>
        <td>
            <ul>
                <?php
                // parse the JSON to get the list of specialties
                foreach ($award->specialties as $specialty) : ?>
                    <li><?= h($specialty) ?></li>
                <?php endforeach; ?>
            </ul>
        </td>
    </tr>
<?php endif; ?>
<tr>
    <th scope="row"><?= __('Description') ?></th>
    <td> <?= $this->Text->autoParagraph(
                h($award->description),
            ) ?>

    </td>
</tr>
<tr>
    <th scope="row"><?= __('Insigna') ?></th>
    <td> <?= $this->Text->autoParagraph(
                h($award->insignia),
            ) ?>

    </td>
</tr>
<tr>
    <th scope="row"><?= __('Badge') ?></th>
    <td> <?= $this->Text->autoParagraph(
                h($award->badge),
            ) ?>

    </td>
</tr>
<tr>
    <th scope="row"><?= __('Charter') ?></th>
    <td> <?= $this->Text->autoParagraph(
                h($award->charter),
            ) ?>

    </td>
</tr>
<tr>
    <th scope="row"><?= __('Domain') ?></th>
    <td><?= $award->hasValue('domain') ? $this->Html->link($award->domain->name, ['controller' => 'Domains', 'action' => 'view', $award->domain->id]) : '' ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Level') ?></th>
    <td><?= $award->hasValue('level') ? $this->Html->link($award->level->name, ['controller' => 'Levels', 'action' => 'view', $award->level->id]) : '' ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Branch') ?></th>
    <td><?= $award->hasValue('branch') ? $this->Html->link($award->branch->name, ['plugin' => null, 'controller' => 'Branches', 'action' => 'view', $award->branch->public_id]) : '' ?>
    </td>
</tr>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<!-- Award Activities tab with ordering
     Order 10: Activities tab
     -->
<button class="nav-link" id="nav-activities-tab" data-bs-toggle="tab" data-bs-target="#nav-activities" type="button"
    role="tab" aria-controls="nav-activities" aria-selected="false" data-detail-tabs-target='tabBtn' data-tab-order="10"
    style="order: 10;">
    <?= __('Given Out During') ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<!-- Activities tab content panel with order matching tab button -->
<div class="related tab-pane fade m-3" id="nav-activities" role="tabpanel" aria-labelledby="nav-activities-tab"
    data-detail-tabs-target="tabContent" data-tab-order="10" style="order: 10;">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <?php if ($user->checkCan('edit', $award)) : ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="bi bi-plus-circle"></i> <?= __('Add Activity') ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($award->gathering_activities)) : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= __('Activity') ?></th>
                        <th><?= __('Description') ?></th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($award->gathering_activities as $activity) : ?>
                        <tr>
                            <td>
                                <?= h($activity->name) ?>
                            </td>
                            <td>
                                <?= h($activity->description) ?>
                            </td>
                            <td class="actions text-end text-nowrap">
                                <?php if ($user->checkCan('edit', $award)) : ?>
                                    <?= $this->Form->postLink(
                                        '<i class="bi bi-x-circle-fill"></i>',
                                        ['action' => 'remove-activity', $award->id, $activity->id],
                                        [
                                            'confirm' => __('Remove "{0}" from this award?', $activity->name),
                                            'escape' => false,
                                            'title' => __('Remove'),
                                            'class' => 'btn btn-sm btn-danger',
                                        ],
                                    ) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="alert alert-secondary">
            <i class="bi bi-info-circle"></i>
            <?= __('No activities have been added to this award yet.') ?>
            <?php if ($user->checkCan('edit', $award)) : ?>
                <?= __('Click "Add Activity" above to get started.') ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($award, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Awards",
        "action" => "edit",
        $award->id,
    ],
    'data-controller' => 'awards-award-form'
]);
echo $this->Modal->create("Edit Award", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control('name');
    echo $this->Form->control('abbreviation');
    $specialties = json_encode($award->specialties);
    if ($specialties === 'null') {
        $specialties = '[]';
    }
    echo $this->Form->hidden('specialties', ['value' => $specialties, 'id' => 'specialties', 'data-awards-award-form-target' => 'formValue']); ?>
    <div class="mb-3 form-group specialties">
        <label class="form-label" for="specialtyInput">Specialties</label>
        <div data-awards-award-form-target='displayList' class="mb-3"></div>
        <div class="input-group">
            <input type="text" data-awards-award-form-target="new" class="form-control" placeholder="Add Specialty">
            <button type="button" class="btn btn-primary btn-sm" data-action="awards-award-form#add">Add</button>
        </div>
    </div>
    <?php
    echo $this->Form->control('description');
    echo $this->Form->control('insignia');
    echo $this->Form->control('badge');
    echo $this->Form->control('charter');
    echo $this->Form->control('domain_id', ['options' => $awardsDomains]);
    echo $this->Form->control('level_id', ['options' => $awardsLevels]);
    echo $this->Form->control('branch_id', ['options' => $branches]);
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
?>

<?php
// Add Activity Modal
if ($user->checkCan('edit', $award)) {
    echo $this->Form->create(null, [
        'url' => [
            'controller' => 'Awards',
            'action' => 'add-activity',
            $award->id,
        ],
    ]);
    echo $this->Modal->create('Add Activity', [
        'id' => 'addActivityModal',
        'close' => true,
    ]);
?>
    <div class="mb-3">
        <label for="gathering_activity_id" class="form-label"><?= __('Select Activity') ?></label>
        <?= $this->Form->control('gathering_activity_id', [
            'options' => $availableActivities,
            'empty' => __('-- Select an activity --'),
            'class' => 'form-select',
            'label' => false,
            'required' => true,
        ]) ?>
        <div class="form-text">
            <?= __('Select a gathering activity that this award can be given out during.') ?>
        </div>
    </div>
<?php
    echo $this->Modal->end([
        $this->Form->button(__('Add Activity'), [
            'class' => 'btn btn-primary',
        ]),
        $this->Form->button(__('Close'), [
            'data-bs-dismiss' => 'modal',
            'type' => 'button',
            'class' => 'btn btn-secondary',
        ]),
    ]);
    echo $this->Form->end();
}
?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>