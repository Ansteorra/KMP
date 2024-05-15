<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Participant[]|\Cake\Collection\CollectionInterface $participants
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('New Participant'), ['action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Participant Authorization Types'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Participant Authorization Type'), ['controller' => 'ParticipantAuthorizationTypes', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
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
        <?php foreach ($participants as $participant) : ?>
        <tr>
            <td><?= $this->Number->format($participant->id) ?></td>
            <td><?= h($participant->last_updated) ?></td>
            <td><?= h($participant->sca_name) ?></td>
            <td><?= h($participant->first_name) ?></td>
            <td><?= h($participant->middle_name) ?></td>
            <td><?= h($participant->last_name) ?></td>
            <td><?= h($participant->street_address) ?></td>
            <td><?= h($participant->city) ?></td>
            <td><?= h($participant->state) ?></td>
            <td><?= h($participant->zip) ?></td>
            <td><?= h($participant->phone_number) ?></td>
            <td><?= h($participant->email_address) ?></td>
            <td><?= $participant->membership_number === null ? '' : $this->Number->format($participant->membership_number) ?></td>
            <td><?= h($participant->membership_expires_on) ?></td>
            <td><?= h($participant->branch_name) ?></td>
            <td><?= h($participant->parent_name) ?></td>
            <td><?= h($participant->background_check_expires_on) ?></td>
            <td><?= h($participant->hidden) ?></td>
            <td><?= h($participant->password_token) ?></td>
            <td><?= h($participant->password_token_expires_on) ?></td>
            <td><?= h($participant->last_login) ?></td>
            <td><?= h($participant->last_failed_login) ?></td>
            <td><?= $participant->failed_login_attempts === null ? '' : $this->Number->format($participant->failed_login_attempts) ?></td>
            <td><?= $participant->birth_month === null ? '' : $this->Number->format($participant->birth_month) ?></td>
            <td><?= $participant->birth_year === null ? '' : $this->Number->format($participant->birth_year) ?></td>
            <td><?= h($participant->deleted_date) ?></td>
            <td class="actions">
                <?= $this->Html->link(__('View'), ['action' => 'view', $participant->id], ['title' => __('View'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Html->link(__('Edit'), ['action' => 'edit', $participant->id], ['title' => __('Edit'), 'class' => 'btn btn-secondary']) ?>
                <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $participant->id], ['confirm' => __('Are you sure you want to delete # {0}?', $participant->id), 'title' => __('Delete'), 'class' => 'btn btn-danger']) ?>
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
