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
        <th scope="col"><?= $this->Paginator->sort('authorizations.member.sca_name', 'Requester') ?></th>
        <th scope="col"><?= $this->Paginator->sort('approver_id') ?></th>
        <th scope="col"><?= $this->Paginator->sort('authorization_token') ?></th>
        <th scope="col"><?= $this->Paginator->sort('requested_on') ?></th>
        <th scope="col"><?= $this->Paginator->sort('responded_on') ?></th>
        <th scope="col"><?= $this->Paginator->sort('approved') ?></th>
        <th scope="col"><?= $this->Paginator->sort('approver_notes') ?></th>
        <th scope="col" class="actions"><?= __('Actions') ?></th>
    </tr>
    </thead>
    <tbody>
        <?php foreach ($authorizationApprovals as $authorizationApproval) : ?>
        <tr>
            <td><?= h($authorizationApproval->authorizations->member->sca_name) ?></td>
            <td><?= h($authorizationApproval->approver->sca_name) ?></td>
            <td><?= h($authorizationApproval->requested_on) ?></td>
            <td><?= h($authorizationApproval->approved) ?></td>
            <td><?= h($authorizationApproval->approver_notes) ?></td>
            <td class="actions">
                <?= $this->Html->link(__('View'), ['action' => 'view', $authorizationApproval->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Html->link(__('Edit'), ['action' => 'edit', $authorizationApproval->id], ['title' => __('Edit'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $authorizationApproval->id], ['confirm' => __('Are you sure you want to delete # {0}?', $authorizationApproval->id), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
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
