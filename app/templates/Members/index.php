<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $Members
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('New Member'), ['action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Member Authorization Types'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Member Authorization Type'), ['controller' => 'MemberAuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Pending Authorizations'), ['controller' => 'PendingAuthorizations', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Pending Authorization'), ['controller' => 'PendingAuthorizations', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Roles'), ['controller' => 'Roles', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Role'), ['controller' => 'Roles', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<table class="table table-striped">
    <thead>
    <tr>
        <th scope="col"><?= $this->Paginator->sort('id') ?></th>
        <th scope="col"><?= $this->Paginator->sort('last_updated') ?></th>
        <th scope="col"><?= $this->Paginator->sort('sca_name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('first_name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('middle_name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('last_name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('street_address') ?></th>
        <th scope="col"><?= $this->Paginator->sort('city') ?></th>
        <th scope="col"><?= $this->Paginator->sort('state') ?></th>
        <th scope="col"><?= $this->Paginator->sort('zip') ?></th>
        <th scope="col"><?= $this->Paginator->sort('phone_number') ?></th>
        <th scope="col"><?= $this->Paginator->sort('email_address') ?></th>
        <th scope="col"><?= $this->Paginator->sort('membership_number') ?></th>
        <th scope="col"><?= $this->Paginator->sort('membership_expires_on') ?></th>
        <th scope="col"><?= $this->Paginator->sort('branch_name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('parent_name') ?></th>
        <th scope="col"><?= $this->Paginator->sort('background_check_expires_on') ?></th>
        <th scope="col"><?= $this->Paginator->sort('hidden') ?></th>
        <th scope="col"><?= $this->Paginator->sort('password_token') ?></th>
        <th scope="col"><?= $this->Paginator->sort('password_token_expires_on') ?></th>
        <th scope="col"><?= $this->Paginator->sort('last_login') ?></th>
        <th scope="col"><?= $this->Paginator->sort('last_failed_login') ?></th>
        <th scope="col"><?= $this->Paginator->sort('failed_login_attempts') ?></th>
        <th scope="col"><?= $this->Paginator->sort('birth_month') ?></th>
        <th scope="col"><?= $this->Paginator->sort('birth_year') ?></th>
        <th scope="col"><?= $this->Paginator->sort('deleted_date') ?></th>
        <th scope="col" class="actions"><?= __('Actions') ?></th>
    </tr>
    </thead>
    <tbody>
        <?php foreach ($Members as $Member) : ?>
        <tr>
            <td><?= $this->Number->format($Member->id) ?></td>
            <td><?= h($Member->last_updated) ?></td>
            <td><?= h($Member->sca_name) ?></td>
            <td><?= h($Member->first_name) ?></td>
            <td><?= h($Member->middle_name) ?></td>
            <td><?= h($Member->last_name) ?></td>
            <td><?= h($Member->street_address) ?></td>
            <td><?= h($Member->city) ?></td>
            <td><?= h($Member->state) ?></td>
            <td><?= h($Member->zip) ?></td>
            <td><?= h($Member->phone_number) ?></td>
            <td><?= h($Member->email_address) ?></td>
            <td><?= $Member->membership_number === null ? '' : $this->Number->format($Member->membership_number) ?></td>
            <td><?= h($Member->membership_expires_on) ?></td>
            <td><?= h($Member->branch_name) ?></td>
            <td><?= h($Member->parent_name) ?></td>
            <td><?= h($Member->background_check_expires_on) ?></td>
            <td><?= h($Member->hidden) ?></td>
            <td><?= h($Member->password_token) ?></td>
            <td><?= h($Member->password_token_expires_on) ?></td>
            <td><?= h($Member->last_login) ?></td>
            <td><?= h($Member->last_failed_login) ?></td>
            <td><?= $Member->failed_login_attempts === null ? '' : $this->Number->format($Member->failed_login_attempts) ?></td>
            <td><?= $Member->birth_month === null ? '' : $this->Number->format($Member->birth_month) ?></td>
            <td><?= $Member->birth_year === null ? '' : $this->Number->format($Member->birth_year) ?></td>
            <td><?= h($Member->deleted_date) ?></td>
            <td class="actions">
                <?= $this->Html->link(__('View'), ['action' => 'view', $Member->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Html->link(__('Edit'), ['action' => 'edit', $Member->id], ['title' => __('Edit'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $Member->id], ['confirm' => __('Are you sure you want to delete # {0}?', $Member->id), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
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
