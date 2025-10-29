<?php

/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\GatheringActivity> $gatheringActivities
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Gathering Activities';
$this->KMP->endBlock();
?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Gathering Activities
        </h3>
    </div>
    <div class="col text-end">
        <?php
        $gatheringActivitiesTable = \Cake\ORM\TableRegistry::getTableLocator()->get("GatheringActivities");
        $tempActivity = $gatheringActivitiesTable->newEmptyEntity();
        if ($user->checkCan("add", $tempActivity)) :
        ?>
            <?= $this->Html->link(
                ' Add Gathering Activity',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('name') ?></th>
                <th><?= $this->Paginator->sort('description') ?></th>
                <th class="actions"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gatheringActivities as $activity): ?>
                <tr>
                    <td><?= h($activity->name) ?></td>
                    <td><?= h($activity->description) ?></td>
                    <td class="actions">
                        <?= $this->Html->link('<i class="bi bi-binoculars-fill"></i>', ['action' => 'view', $activity->id], ['escape' => false, 'title' => __('View'), 'class' => 'btn btn-sm btn-outline-primary']) ?>
                        <?= $this->Html->link('<i class="bi bi-pencil-fill"></i>', ['action' => 'edit', $activity->id], ['escape' => false, 'title' => __('Edit'), 'class' => 'btn btn-sm btn-outline-secondary']) ?>
                        <?= $this->Form->postLink('<i class="bi bi-trash-fill"></i>', ['action' => 'delete', $activity->id], ['escape' => false, 'title' => __('Delete'), 'class' => 'btn btn-sm btn-outline-danger', 'confirm' => __('Are you sure you want to delete # {0}?', $activity->id)]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first('<< ' . __('first')) ?>
        <?= $this->Paginator->prev('< ' . __('previous')) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next(__('next') . ' >') ?>
        <?= $this->Paginator->last(__('last') . ' >>') ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
    </p>
</div>