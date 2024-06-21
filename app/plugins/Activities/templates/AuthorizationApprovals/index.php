<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Authorization Queues';
$this->KMP->endBlock(); ?>
<h3>
    Authorization Queues
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort(
                                "approver_name",
                                "Approver",
                            ) ?></th>
            <th scope="col"><?= $this->Paginator->sort(
                                "last_login",
                                "Last Login",
                            ) ?></th>
            <th scope="col"><?= $this->Paginator->sort("Pending") ?></th>
            <th scope="col"><?= $this->Paginator->sort("Approved") ?></th>
            <th scope="col"><?= $this->Paginator->sort("Denied") ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($authorizationApprovals as $authRollup) : ?>
        <tr>
            <td><?= h($authRollup->approver_name) ?></td>
            <td><?= h($authRollup->last_login) ?></td>
            <td><?= h($authRollup->pending) ?></td>
            <td><?= h($authRollup->approved) ?></td>
            <td><?= h($authRollup->denied) ?></td>
            <td class="actions">
                <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $authRollup->approver->id],
                        ["title" => __("View"), "class" => "btn btn-secondary"],
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