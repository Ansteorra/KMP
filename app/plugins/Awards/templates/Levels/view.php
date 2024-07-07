<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $level
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Award Level - ' . $level->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($level->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php if (empty($level->awards)) {
    echo $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $level->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $level->name,
            ),
            "title" => __("Delete"),
            "class" => "btn btn-danger btn-sm",
        ],
    );
} ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("recordDetails") ?>
<tr>
    <th scope="row"><?= __("Progression Order") ?></th>
    <td><?= h($level->progression_order) ?></td>
</tr>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-relatedAwards-tab" data-bs-toggle="tab" data-bs-target="#nav-relatedAwards"
    type="button" role="tab" aria-controls="nav-relatedAwards" aria-selected="false"><?= __("Related Awards") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade active m-3" id="nav-relatedAwards" role="tabpanel"
    aria-labelledby="nav-relatedAwards-tab">
    <?php if (!empty($level->awards)) { ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col"><?= h("name") ?></th>
                    <th scope="col" class="text-center"><?= h(
                                                                "Domain",
                                                            ) ?></th>
                    <th scope="col" class="text-center"><?= h(
                                                                "Branch",
                                                            ) ?></th>
                    <th scope="col" class="actions"><?= __("Actions") ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($level->awards
                        as $award) : ?>
                <tr>
                    <td><?= h($award->name) ?></td>
                    <td class="text-center">
                        <?= $award->hasValue('domain') ? $this->Html->link($award->domain->name, ['controller' => 'Domains', 'action' => 'view', $award->domain->id]) : '' ?>
                    </td>
                    <td class="text-center">
                        <?= $award->hasValue('branch') ? $this->Html->link($award->branch->name, ['plugin' => null, 'controller' => 'Branches', 'action' => 'view', $award->branch->id]) : '' ?>
                    </td>
                    <td class="actions">
                        <?= $this->Html->link(
                                    __("View"),
                                    ["controller" => "Awards", "action" => "view", $award->id],
                                    ["title" => __("View"), "class" => "btn btn-secondary"],
                                ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php } else { ?>
    <p>No Awards</p>
    <?php } ?>
</div>
<?php $this->KMP->endBlock() ?>
<?php
echo $this->KMP->startBlock("modals");
echo $this->Form->create($level, [
    "id" => "edit_entity",
    "url" => [
        "controller" => "Levels",
        "action" => "edit",
        $level->id,
    ],
]);
echo $this->Modal->create("Edit Award Level", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("progression_order");
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