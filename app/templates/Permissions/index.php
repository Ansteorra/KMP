<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Permission[]|\Cake\Collection\CollectionInterface $permissions
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('New Permission'), ['action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Authorization Types'), ['controller' => 'AuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Authorization Type'), ['controller' => 'AuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Roles'), ['controller' => 'Roles', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Role'), ['controller' => 'Roles', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<table class="table table-striped">
    <thead>
    <tr>
        <th scope="col"><?= $this->Paginator->sort('id') ?></th>
        <th scope="col"><?= $this->Paginator->sort('name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('authorization_type_id') ?></th>
        <th scope="col"><?= $this->Paginator->sort('require_active_membership') ?></th>
        <th scope="col"><?= $this->Paginator->sort('require_active_background_check') ?></th>
        <th scope="col"><?= $this->Paginator->sort('require_min_age') ?></th>
        <th scope="col"><?= $this->Paginator->sort('system') ?></th>
        <th scope="col"><?= $this->Paginator->sort('is_super_user') ?></th>
        <th scope="col" class="actions"><?= __('Actions') ?></th>
    </tr>
    </thead>
    <tbody>
        <?php foreach ($permissions as $permission) : ?>
        <tr>
            <td><?= $this->Number->format($permission->id) ?></td>
            <td><?= h($permission->name) ?></td>
            <td><?= $permission->hasValue('authorization_type') ? $this->Html->link($permission->authorization_type->name, ['controller' => 'AuthorizationTypes', 'action' => 'view', $permission->authorization_type->id]) : '' ?></td>
            <td><?= h($permission->require_active_membership) ?></td>
            <td><?= h($permission->require_active_background_check) ?></td>
            <td><?= h($permission->require_min_age) ?></td>
            <td><?= h($permission->system) ?></td>
            <td><?= h($permission->is_super_user) ?></td>
            <td class="actions">
                <?= $this->Html->link(__('View'), ['action' => 'view', $permission->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Html->link(__('Edit'), ['action' => 'edit', $permission->id], ['title' => __('Edit'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $permission->id], ['confirm' => __('Are you sure you want to delete # {0}?', $permission->id), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
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
