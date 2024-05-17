<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationType[]|\Cake\Collection\CollectionInterface $authorizationTypes
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('New Authorization Type'), ['action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Martial Groups'), ['controller' => 'MartialGroups', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Martial Group'), ['controller' => 'MartialGroups', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Member Authorization Types'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Member Authorization Type'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Pending Authorizations'), ['controller' => 'PendingAuthorizations', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Pending Authorization'), ['controller' => 'PendingAuthorizations', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Permissions'), ['controller' => 'Permissions', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Permission'), ['controller' => 'Permissions', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<table class="table table-striped">
    <thead>
    <tr>
        <th scope="col"><?= $this->Paginator->sort('id') ?></th>
        <th scope="col"><?= $this->Paginator->sort('name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('length') ?></th>
        <th scope="col"><?= $this->Paginator->sort('martial_groups_id') ?></th>
        <th scope="col"><?= $this->Paginator->sort('minimum_age') ?></th>
        <th scope="col"><?= $this->Paginator->sort('maximum_age') ?></th>
        <th scope="col"><?= $this->Paginator->sort('num_required_authorizors') ?></th>
        <th scope="col" class="actions"><?= __('Actions') ?></th>
    </tr>
    </thead>
    <tbody>
        <?php foreach ($authorizationTypes as $authorizationType) : ?>
        <tr>
            <td><?= $this->Number->format($authorizationType->id) ?></td>
            <td><?= h($authorizationType->name) ?></td>
            <td><?= $this->Number->format($authorizationType->length) ?></td>
            <td><?= $authorizationType->hasValue('martial_group') ? $this->Html->link($authorizationType->martial_group->name, ['controller' => 'MartialGroups', 'action' => 'view', $authorizationType->martial_group->id]) : '' ?></td>
            <td><?= $authorizationType->minimum_age === null ? '' : $this->Number->format($authorizationType->minimum_age) ?></td>
            <td><?= $authorizationType->maximum_age === null ? '' : $this->Number->format($authorizationType->maximum_age) ?></td>
            <td><?= $this->Number->format($authorizationType->num_required_authorizors) ?></td>
            <td class="actions">
                <?= $this->Html->link(__('View'), ['action' => 'view', $authorizationType->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Html->link(__('Edit'), ['action' => 'edit', $authorizationType->id], ['title' => __('Edit'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $authorizationType->id], ['confirm' => __('Are you sure you want to delete # {0}?', $authorizationType->id), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<div class="paginator">
    <ul class="pagination">
        <?= $this->Paginator->first('«', ['label' => __('First')]) ?>
        <?= $this->Paginator->prev('‹', ['label' => __('Previous')]) ?>
        <?= $this->Paginator->numbers() ?>
        <?= $this->Paginator->next('›', ['label' => __('Next')]) ?>
        <?= $this->Paginator->last('»', ['label' => __('Last')]) ?>
    </ul>
    <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?></p>
</div>
