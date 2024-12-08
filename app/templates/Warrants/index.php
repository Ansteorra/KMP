<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $warrants
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Warrants';
$this->KMP->endBlock(); ?>
<h3>
    Warrants
</h3>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("sca_name") ?></th>
            <th scope="col"><?= $this->Paginator->sort("start_on") ?></th>
            <th scope="col"><?= $this->Paginator->sort("expires_on") ?></th>
            <th scope="col"><?= $this->Paginator->sort("status") ?></th>
            <th scope="col" class="actions"><?= __("Actions") ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($permissions as $permission) : ?>
        <tr>
            <td><?= h($permission->name) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->require_active_membership,
                                            $this->Html,
                                        ) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->require_active_background_check,
                                            $this->Html,
                                        ) ?></td>
            <td class="text-center"><?= h($permission->require_min_age) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->requires_warrant,
                                            $this->Html,
                                        ) ?></td>
            <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->is_super_user,
                                            $this->Html,
                                        ) ?></td>

            <td class="text-center"><?= $this->Kmp->bool(
                                            $permission->is_system,
                                            $this->Html,
                                        ) ?></td>
            <td class="actions">
                <?= $this->Html->link(
                        __("View"),
                        ["action" => "view", $permission->id],
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