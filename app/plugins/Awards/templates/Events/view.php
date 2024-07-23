<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $event
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Award Req Event - ' . $event->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($event->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php
echo $this->Form->postLink(
    __("Delete"),
    ["action" => "delete", $event->id],
    [
        "confirm" => __(
            "Are you sure you want to delete {0}?",
            $event->name,
        ),
        "title" => __("Delete"),
        "class" => "btn btn-danger btn-sm",
    ],
); ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __("Description") ?></th>
    <td><?= h($event->description) ?></td>
</tr>
<tr>
    <th scope="row"><?= __("Start Date") ?></th>
    <td><?= h(($event->start_date ? $event->start_date->toDateString() : "")) ?></td>
</tr>
<tr>
    <th scope="row"><?= __("End Date") ?></th>
    <td><?= h(($event->end_date ? $event->end_date->toDateString() : "")) ?></td>
</tr>

<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($event, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Events",
        "action" => "edit",
        $event->id,
    ],
]);
echo $this->Modal->create("Edit Award Req Event", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("description");
    echo $this->Form->control('branch_id', ['options' => $branches]);
    echo $this->Form->control("start_date", [
        "type" => "date",
        "label" => __("Start Date"),
    ]);
    echo $this->Form->control("end_date", [
        "type" => "date",
        "label" => __("End Date"),
    ]);
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end(); ?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>