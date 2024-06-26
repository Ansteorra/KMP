<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Domain $domain
 */
?>
<?php

$this->extend("/layout/TwitterBootstrap/view_record");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Award Domain - ' . $domain->name;
$this->KMP->endBlock();

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($domain->name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<?php if (empty($domain->awards)) {
    echo $this->Form->postLink(
        __("Delete"),
        ["action" => "delete", $domain->id],
        [
            "confirm" => __(
                "Are you sure you want to delete {0}?",
                $domain->name,
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
<button class="nav-link" id="nav-relatedAwards-tab" data-bs-toggle="tab" data-bs-target="#nav-relatedAwards"
    type="button" role="tab" aria-controls="nav-relatedAwards" aria-selected="false"><?= __("Related Awards") ?>
</button>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade active m-3" id="nav-relatedAwards" role="tabpanel"
    aria-labelledby="nav-relatedAwards-tab">
    <?php if (!empty($domain->awards)) { ?>
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
                <?php foreach ($domain->awards
                        as $award) : ?>
                <tr>
                    <td><?= h($award->name) ?></td>
                    <td class="text-center">
                        <?= $award->hasValue('level') ? $this->Html->link($award->level->name, ['controller' => 'Levels', 'action' => 'view', $award->level->id]) : '' ?>
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
echo $this->Modal->create("Edit Award Domain", [
    "id" => "editModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->create($domain, [
        "id" => "edit_entity",
        "url" => [
            "controller" => "Domains",
            "action" => "edit",
            $domain->id,
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