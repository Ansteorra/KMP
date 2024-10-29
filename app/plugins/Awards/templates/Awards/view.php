<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $award
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Award Domain - ' . $award->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($award->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php if (empty($award->recommendations)) {
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
    <td><?= $award->hasValue('branch') ? $this->Html->link($award->branch->name, ['plugin' => null, 'controller' => 'Branches', 'action' => 'view', $award->branch->id]) : '' ?>
    </td>
</tr>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
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

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>