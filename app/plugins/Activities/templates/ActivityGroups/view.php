<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard"); ?>

<div class="activityGroup view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
                <?= h($authorizationGroup->name) ?>
            </h3>
        </div>
        <div class="col text-end">
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
        </div>
    </div>
    <div class="related">
        <h4><?= __("Related Activities") ?> </h4>
        <?php if (!empty($authorizationGroup->activities)) { ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col"><?= h("name") ?></th>
                            <th scope="col" class="text-center"><?= h(
                                                                    "Duration (years)",
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
                        <?php foreach ($authorizationGroup->activities
                            as $activity) : ?>
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
        <?php } ?>
    </div>
</div>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Modal->create("Edit Authoriztion Group", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create($authorizationGroup, [
        "id" => "edit_entity",
        "url" => [
            "controller" => "ActivityGroups",
            "action" => "edit",
            $authorizationGroup->id,
        ],
    ]);
    echo $this->Form->control("name");
    echo $this->Form->end();
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
        "onclick" => '$("#edit_entity").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>

<?php //finish writing to modal block in layout
$this->KMP->endBlock(); ?>