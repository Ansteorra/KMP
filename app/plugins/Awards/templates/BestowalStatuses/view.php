<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\BestowalStatus $status
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Bestowal Status - ' . $status->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($status->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php if (empty($status->bestowal_states)) {
    echo $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $status->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $status->name,
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    );
} ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<dt><?= __('Sort Order') ?></dt>
<dd><?= h($status->sort_order) ?></dd>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-states-tab" data-bs-toggle="tab" data-bs-target="#nav-states"
    type="button" role="tab" aria-controls="nav-states" aria-selected="false"
    data-detail-tabs-target='tabBtn'><?= __("States") ?> <span class="badge bg-secondary"><?= count($status->bestowal_states) ?></span>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade active m-3" id="nav-states" role="tabpanel"
    aria-labelledby="nav-states-tab" data-detail-tabs-target="tabContent">
    <?php if (!empty($status->bestowal_states)) { ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col"><?= __('Name') ?></th>
                        <th scope="col" class="text-center"><?= __('Sort Order') ?></th>
                        <th scope="col" class="text-center"><?= __('Supports Gathering') ?></th>
                        <th scope="col" class="text-center"><?= __('Locks Recommendations') ?></th>
                        <th scope="col" class="text-center"><?= __('Hidden') ?></th>
                        <th scope="col" class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status->bestowal_states as $state) : ?>
                        <tr>
                            <td><?= h($state->name) ?></td>
                            <td class="text-center"><?= h($state->sort_order) ?></td>
                            <td class="text-center"><?= $this->KMP->bool($state->supports_gathering, $this->Html) ?></td>
                            <td class="text-center"><?= $this->KMP->bool($state->locks_recommendations, $this->Html) ?></td>
                            <td class="text-center"><?= $this->KMP->bool($state->is_hidden, $this->Html) ?></td>
                            <td class="actions text-end text-nowrap">
                                <?= $this->Html->link(
                                    __(""),
                                    ["controller" => "BestowalStates", "action" => "view", $state->id],
                                    ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <p><?= __('No states in this status.') ?></p>
    <?php } ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($status, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "BestowalStatuses",
        "action" => "edit",
        $status->id,
    ],
]);

echo $this->Modal->create("Edit Bestowal Status", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("sort_order", ['type' => 'number']);
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

<?php $this->KMP->endBlock(); ?>
