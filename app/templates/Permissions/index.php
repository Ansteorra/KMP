<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Permissions';
$this->KMP->endBlock(); ?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Permissions
        </h3>
    </div>
    <div class="col text-end">
        <?php
        if ($user->checkCan("add", "Permissions")) :
        ?>
            <?= $this->Html->link(
                ' Add Permission',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>
<table class="table table-striped">
    <thead>
        <tr>
            <th scope="col" colspan='1'></th>
            <th scope="col" colspan='4' class="text-center table-active">Requirements</th>
            <th scope="col" colspan='3'></th>
        </tr>
        <tr>
            <th scope="col"><?= $this->Paginator->sort("name") ?></th>
            <th scope="col"><?= $this->Paginator->sort("scoping_rule") ?></th>
            <th scope="col" class="text-center"><?= __("Membership") ?></th>
            <th scope="col" class="text-center"><?= __(
                                                    "Background Check",
                                                ) ?></th>
            <th scope="col" class="text-center"><?= __("Minimum Age") ?></th>
            <th scope="col" class="text-center"><?= __("Warrant") ?></th>
            <th scope="col" class="text-center"><?= __("Super User") ?></th>
            <th scope="col" class="text-center"><?= __("System") ?></th>
            <th scope="col" class="actions"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($permissions as $permission) : ?>
            <tr>
                <td><?= h($permission->name) ?></td>
                <td><?= h($permission->scoping_rule) ?></td>
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
                <td class="actions text-end text-nowrap">
                    <?= $this->Html->link(
                        __(""),
                        ["action" => "view", $permission->id],
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