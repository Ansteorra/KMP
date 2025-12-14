<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $Members
 */
?>
<?php
if (!$isTurboFrame) {
    $this->extend("/layout/TwitterBootstrap/dashboard");

    echo $this->KMP->startBlock("title");
    echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Members';
    $this->KMP->endBlock();
}

?>
<?php if (!$isTurboFrame) : ?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Members
        </h3>
    </div>
    <div class="col text-end">
        <?php
            if ($user->checkCan("add", "Members")) :
            ?>
        <?= $this->Html->link(
                    ' Add Member',
                    ['action' => 'add'],
                    ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top']
                ) ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<turbo-frame id="membersList" data-turbo='true'>
    <table class="table table-striped">
        <thead>
            <tr>
                <td colspan="6">
                <td colspan="2" class="text-end">
                    <form class="form-inline">

                        <div class="input-group">
                            <div class="input-group-text" id="btnSearch"><span class='bi bi-search'></span></div>
                            <input type="text" name="search" class="form-control" placeholder="Search..."
                                value="<?= $search ?>" aria-describedby="btnSearch" aria-label="Search">
                        </div>
                    </form>
                </td>
            </tr>
            <tr>
                <th scope="col"><?= $this->Paginator->sort("sca_name") ?></th>
                <th scope="col">
                    <?= $this->Paginator->sort("Branches.name", "Branch") ?>
                </th>
                <th scope="col"><?= $this->Paginator->sort("first_name") ?></th>
                <th scope="col"><?= $this->Paginator->sort("last_name") ?></th>
                <th scope="col"><?= $this->Paginator->sort("email_address") ?></th>
                <th scope="col"><?= $this->Paginator->sort("status") ?></th>
                <th scope="col"><?= $this->Paginator->sort("last_login") ?></th>
                <th scope="col" class="actions"></th>
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
                <td><?= h($Member->status) ?></td>
                <td><?= $Member->last_login ? $this->Timezone->format($Member->last_login, $Member, null, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT) : '' ?>
                </td>
                <td class="actions text-end text-nowrap" data-turbo='false'>
                    <?= $this->Html->link(
                            __(""),
                            ["action" => "view", $Member->id],
                            ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                        ) ?>
                    <?php if ($user->isSuperUser()) { ?>
                    <?= $this->Form->postLink(
                                __("Delete"),
                                ["action" => "delete", $Member->id],
                                [
                                    "confirm" => __(
                                        "Are you sure you want to delete # {0}?",
                                        $Member->sca_name,
                                    ),
                                    "title" => __("Delete"),
                                    "class" => "btn btn-danger btn-sm",
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
</turbo-frame>