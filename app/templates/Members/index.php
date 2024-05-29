<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $Members
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");
$user = $this->request->getAttribute("identity");
?>
<h3>
    Members
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <td colspan="6">
            <td colspan="2" class="text-end">
                <form class="form-inline">

                    <div class="input-group">
                        <div class="input-group-text" id="btnSearch"><span class='bi bi-search'></span></div>
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= $search ?>" aria-describedby="btnSearch" aria-label="Search">
                    </div>
                </form>
            </td>
        </tr>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("sca_name") ?></th>
            <th scope="col">
                <?php if ($sort === "Branches.name" && $direction === "asc") {
                    echo $this->Html->link("Branch", [
                        "controller" => "Members",
                        "?" => ["sort" => "Branches.name", "direction" => "desc"],
                        "class" => "asc",
                    ]);
                } elseif ($sort === "Branches.name" && $direction === "desc") {
                    echo $this->Html->link("Branch", [
                        "controller" => "Members",
                        "?" => ["sort" => "Branches.name", "direction" => "asc"],
                        "class" => "desc",
                    ]);
                } else {
                    echo $this->Html->link("Branch", [
                        "controller" => "Members",
                        "?" => ["sort" => "Branches.name", "direction" => "asc"],
                        "class" => "",
                    ]);
                } ?>
            </th>
            <th scope="col"><?= $this->Paginator->sort("first_name") ?></th>
            <th scope="col"><?= $this->Paginator->sort("last_name") ?></th>
            <th scope="col"><?= $this->Paginator->sort("email_address") ?></th>
            <th scope="col"><?= $this->Paginator->sort("hidden") ?></th>
            <th scope="col"><?= $this->Paginator->sort("last_login") ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($Members as $Member) : ?>
            <tr>
                <td><?= h($Member->sca_name) ?></td>
                <td><?= h($Member->branch->name) ?></td>
                <td><?= h($Member->first_name) ?></td>
                <td><?= h($Member->last_name) ?></td>
                <td><?= h($Member->email_address) ?></td>
                <td><?= h($Member->hidden) ?></td>
                <td><?= h($Member->last_login) ?></td>
                <td class="actions">
                    <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $Member->id],
                        ["title" => __("View"), "class" => "btn btn-secondary"],
                    ) ?>
                    <?php if ($user->isSuperUser()) { ?>
                        <?= $this->Form->postLink(
                            __("Delete"),
                            ["action" => "delete", $Member->id],
                            [
                                "confirm" => __(
                                    "Are you sure you want to delete # {0}?",
                                    $Member->id,
                                ),
                                "title" => __("Delete"),
                                "class" => "btn btn-danger",
                            ],
                        ) ?>
                    <?php } ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first("«", ["label" => __("First")]) ?>
        <?= $this->Paginator->prev("‹", [
            "label" => __("Previous"),
        ]) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next("›", ["label" => __("Next")]) ?>
        <?= $this->Paginator->last("»", ["label" => __("Last")]) ?>
    </ul>
    <p><?= $this->Paginator->counter(
            __(
                "Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total",
            ),
        ) ?></p>
</div>