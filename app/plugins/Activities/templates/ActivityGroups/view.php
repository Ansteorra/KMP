<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Activity Group - ' . $authorizationGroup->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($authorizationGroup->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php if (empty($authorizationGroup->activities)) {
    echo $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $authorizationGroup->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $authorizationGroup->name,
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    );
} ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-relatedActivities-tab" data-bs-toggle="tab" data-bs-target="#nav-relatedActivities"
    type="button" role="tab" aria-controls="nav-relatedActivities" aria-selected="false"
    data-detail-tabs-target='tabBtn'><?= __("Related Activities") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade active m-3" id="nav-relatedActivities" role="tabpanel"
    aria-labelledby="nav-relatedActivities-tab" data-detail-tabs-target="tabContent">
    <?php if (!empty($authorizationGroup->activities)) { ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col"><?= h("name") ?></th>
                        <th scope="col" class="text-center"><?= h(
                                                                "Duration (Months)",
                                                            ) ?></th>
                        <th scope="col" class="text-center"><?= h(
                                                                "minimum_age",
                                                            ) ?></th>
                        <th scope="col" class="text-center"><?= h(
                                                                "maximum_age",
                                                            ) ?></th>
                        <th scope="col" class="text-center"><?= h(
                                                                "# of Approvers",
                                                            ) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (
                        $authorizationGroup->activities
                        as $activity
                    ) : ?>
                        <tr>
                            <td><?= h($activity->name) ?></td>
                            <td class="text-center"><?= $this->Number->format(
                                                        $activity->term_length,
                                                    ) ?></td>
                            <td class="text-center"><?= $activity->minimum_age ===
                                                        null
                                                        ? ""
                                                        : $this->Number->format(
                                                            $activity->minimum_age,
                                                        ) ?></td>
                            <td class="text-center"><?= $activity->maximum_age ===
                                                        null
                                                        ? ""
                                                        : $this->Number->format(
                                                            $activity->maximum_age,
                                                        ) ?></td>
                            <td class="text-center"><?= $this->Number->format(
                                                        $activity->num_required_authorizors,
                                                    ) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <p>No Activities Assigned</p>
    <?php } ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($authorizationGroup, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "ActivityGroups",
        "action" => "edit",
        $authorizationGroup->id,
    ],
]);
echo $this->Modal->create("Edit Authoriztion Group", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
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
echo $this->Form->end();
?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>