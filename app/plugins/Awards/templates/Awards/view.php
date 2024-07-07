<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $award
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Award Domain - ' . $award->name;
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
]);
echo $this->Modal->create("Edit Award", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control('name');
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
    ]),
]);
echo $this->Form->end();
?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>