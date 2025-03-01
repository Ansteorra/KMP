<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Authorization Queues';
$this->KMP->endBlock(); ?>
<h3>
    Authorization Queues
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <td colspan="2">
            <td colspan="4" class="text-end">
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
            <th scope="col"><?= $this->Paginator->sort(
                                "approver_name",
                                "Approver",
                            ) ?></th>
            <th scope="col"><?= $this->Paginator->sort(
                                "last_login",
                                "Last Login",
                            ) ?></th>
            <th scope="col"><?= $this->Paginator->sort("pending_count", "Pending") ?></th>
            <th scope="col"><?= $this->Paginator->sort("approved_count", "Approved") ?></th>
            <th scope="col"><?= $this->Paginator->sort("denied_count", "Denied") ?></th>
            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($authorizationApprovals as $authRollup) : ?>
            <tr>
                <td><?= h($authRollup->approver_name) ?></td>
                <td><?= h($authRollup->last_login) ?></td>
                <td><?= h($authRollup->pending_count) ?></td>
                <td><?= h($authRollup->approved_count) ?></td>
                <td><?= h($authRollup->denied_count) ?></td>
                <td class="actions text-end text-nowrap">
                    <?= $this->Html->link(
                        __(""),
                        ["action" => "view", $authRollup->approver->id],
                        ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                    ) ?>
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