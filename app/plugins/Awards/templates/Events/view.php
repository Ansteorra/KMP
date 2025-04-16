<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $event
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Award Rec Event - ' . $event->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($event->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php
$user = $this->request->getAttribute('identity');
if ($user->checkCan("edit", $event)) :
    // Only show edit button if the user has permission to edit the event
?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php
endif;

if ($user->checkCan("delete", $event) && empty($event->recommendations_to_give)) :
    // Only show delete button if there are no recommendations associated with the event
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
    );
endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __("Branch") ?></th>
    <td><?= h($event->branch->name) ?></td>
</tr>
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
<tr>
    <th scope="row"><?= __("Closed") ?></th>
    <td><?= h(($event->closed ? "Yes" : "No")) ?></td>
</tr>

<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<?php
if ($showAwards): ?>
<button class="nav-link" id="nav-scheduledAwards-tab" data-bs-toggle="tab" data-bs-target="#nav-scheduledAwards"
    type="button" role="tab" aria-controls="nav-scheduledAwards" aria-selected="false"
    data-detail-tabs-target='tabBtn'><?= __("Scheduled Awards") ?></button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<?php
if ($showAwards):
    $turboUrl = $this->URL->build(["controller" => "Recommendations", "action" => "Table", "plugin" => "Awards", "Event", "?" => ["event_id" => $event->id]]);
?>
<div class="related tab-pane fade m-3" id="nav-scheduledAwards" role="tabpanel"
    aria-labelledby="nav-scheduledAwards-tab" data-detail-tabs-target="tabContent">
    <turbo-frame id="tableView-frame" loading="lazy" data-turbo="true" src="<?= $turboUrl ?>">
    </turbo-frame>
</div>
<?php endif; ?>
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
echo $this->Modal->create("Edit Award Rec Event", [
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
    echo $this->Form->control("closed", ['switch' => true]);
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