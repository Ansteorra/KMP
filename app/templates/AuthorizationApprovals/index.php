<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AuthorizationApproval[]|\Cake\Collection\CollectionInterface $authorizationApprovals
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('New Authorization Approval'), ['action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Authorization'), ['controller' => 'Authorizations', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Authorization'), ['controller' => 'Authorizations', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Approver'), ['controller' => 'Members', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Approver'), ['controller' => 'Members', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<table class="table table-striped">
    <thead>
    <tr>
        <th scope="col"><?= $this->Paginator->sort('approver_name', 'Approver') ?></th>
        <th scope="col"><?= $this->Paginator->sort('last_login', 'Last Login') ?></th>
        <th scope="col"><?= $this->Paginator->sort('Pending') ?></th>
        <th scope="col"><?= $this->Paginator->sort('Approved') ?></th>
        <th scope="col"><?= $this->Paginator->sort('Denied') ?></th>
        <th scope="col" class="actions"><?= __('Actions') ?></th>
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
                <?= $this->Html->link(__('View'), ['action' => 'view', $authRollup->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
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
