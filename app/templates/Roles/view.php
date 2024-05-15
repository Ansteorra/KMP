<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Role $role
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('Edit Role'), ['action' => 'edit', $role->id], ['class' => 'nav-link']) ?></li>
<li><?= $this->Form->postLink(__('Delete Role'), ['action' => 'delete', $role->id], ['confirm' => __('Are you sure you want to delete # {0}?', $role->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Roles'), ['action' => 'index'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('New Role'), ['action' => 'add'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('List Participants'), ['controller' => 'Participants', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Participant'), ['controller' => 'Participants', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List Permissions'), ['controller' => 'Permissions', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Permission'), ['controller' => 'Permissions', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="roles view large-9 medium-8 columns content">
    <h3><?= h($role->name) ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Name') ?></th>
                <td><?= h($role->name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Id') ?></th>
                <td><?= $this->Number->format($role->id) ?></td>
            </tr>
        </table>
    </div>
    <div class="related">
        <h4><?= __('Related Participants') ?></h4>
        <?php if (!empty($role->participants)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Last Updated') ?></th>
                    <th scope="col"><?= __('Password') ?></th>
                    <th scope="col"><?= __('Sca Name') ?></th>
                    <th scope="col"><?= __('First Name') ?></th>
                    <th scope="col"><?= __('Middle Name') ?></th>
                    <th scope="col"><?= __('Last Name') ?></th>
                    <th scope="col"><?= __('Street Address') ?></th>
                    <th scope="col"><?= __('City') ?></th>
                    <th scope="col"><?= __('State') ?></th>
                    <th scope="col"><?= __('Zip') ?></th>
                    <th scope="col"><?= __('Phone Number') ?></th>
                    <th scope="col"><?= __('Email Address') ?></th>
                    <th scope="col"><?= __('Membership Number') ?></th>
                    <th scope="col"><?= __('Membership Expires On') ?></th>
                    <th scope="col"><?= __('Branch Name') ?></th>
                    <th scope="col"><?= __('Notes') ?></th>
                    <th scope="col"><?= __('Parent Name') ?></th>
                    <th scope="col"><?= __('Background Check Expires On') ?></th>
                    <th scope="col"><?= __('Hidden') ?></th>
                    <th scope="col"><?= __('Password Token') ?></th>
                    <th scope="col"><?= __('Password Token Expires On') ?></th>
                    <th scope="col"><?= __('Last Login') ?></th>
                    <th scope="col"><?= __('Last Failed Login') ?></th>
                    <th scope="col"><?= __('Failed Login Attempts') ?></th>
                    <th scope="col"><?= __('Birth Month') ?></th>
                    <th scope="col"><?= __('Birth Year') ?></th>
                    <th scope="col"><?= __('Deleted Date') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($role->participants as $participants): ?>
                <tr>
                    <td><?= h($participants->id) ?></td>
                    <td><?= h($participants->last_updated) ?></td>
                    <td><?= h($participants->password) ?></td>
                    <td><?= h($participants->sca_name) ?></td>
                    <td><?= h($participants->first_name) ?></td>
                    <td><?= h($participants->middle_name) ?></td>
                    <td><?= h($participants->last_name) ?></td>
                    <td><?= h($participants->street_address) ?></td>
                    <td><?= h($participants->city) ?></td>
                    <td><?= h($participants->state) ?></td>
                    <td><?= h($participants->zip) ?></td>
                    <td><?= h($participants->phone_number) ?></td>
                    <td><?= h($participants->email_address) ?></td>
                    <td><?= h($participants->membership_number) ?></td>
                    <td><?= h($participants->membership_expires_on) ?></td>
                    <td><?= h($participants->branch_name) ?></td>
                    <td><?= h($participants->notes) ?></td>
                    <td><?= h($participants->parent_name) ?></td>
                    <td><?= h($participants->background_check_expires_on) ?></td>
                    <td><?= h($participants->hidden) ?></td>
                    <td><?= h($participants->password_token) ?></td>
                    <td><?= h($participants->password_token_expires_on) ?></td>
                    <td><?= h($participants->last_login) ?></td>
                    <td><?= h($participants->last_failed_login) ?></td>
                    <td><?= h($participants->failed_login_attempts) ?></td>
                    <td><?= h($participants->birth_month) ?></td>
                    <td><?= h($participants->birth_year) ?></td>
                    <td><?= h($participants->deleted_date) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'Participants', 'action' => 'view', $participants->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Html->link(__('Edit'), ['controller' => 'Participants', 'action' => 'edit', $participants->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink( __('Delete'), ['controller' => 'Participants', 'action' => 'delete', $participants->id], ['confirm' => __('Are you sure you want to delete # {0}?', $participants->id), 'class' => 'btn btn-danger']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="related">
        <h4><?= __('Related Permissions') ?></h4>
        <?php if (!empty($role->permissions)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Id') ?></th>
                    <th scope="col"><?= __('Name') ?></th>
                    <th scope="col"><?= __('Authorization Type Id') ?></th>
                    <th scope="col"><?= __('System') ?></th>
                    <th scope="col"><?= __('Is Super User') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($role->permissions as $permissions): ?>
                <tr>
                    <td><?= h($permissions->id) ?></td>
                    <td><?= h($permissions->name) ?></td>
                    <td><?= h($permissions->authorization_type_id) ?></td>
                    <td><?= h($permissions->system) ?></td>
                    <td><?= h($permissions->is_super_user) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['controller' => 'Permissions', 'action' => 'view', $permissions->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Html->link(__('Edit'), ['controller' => 'Permissions', 'action' => 'edit', $permissions->id], ['class' => 'btn btn-secondary']) ?>
                        <?= $this->Form->postLink( __('Delete'), ['controller' => 'Permissions', 'action' => 'delete', $permissions->id], ['confirm' => __('Are you sure you want to delete # {0}?', $permissions->id), 'class' => 'btn btn-danger']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
