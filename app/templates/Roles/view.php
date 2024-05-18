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
<li><?= $this->Html->link(__('List Members'), ['controller' => 'Members', 'action' => 'index'], ['class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('New Member'), ['controller' => 'Members', 'action' => 'add'], ['class' => 'nav-link']) ?></li>
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
        <h4><?= __('Related Members') ?></h4>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">Add To Group</button></h4>
        <?php if (!empty($role->member_roles)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <tr>
                    <th scope="col"><?= __('Sca Name') ?></th>
                    <th scope="col"><?= __('Assignment Date') ?></th>
                    <th scope="col"><?= __('Expire Date') ?></th>
                    <th scope="col"><?= __('Approved By') ?></th>
                    <th scope="col" class="actions"><?= __('Actions') ?></th>
                </tr>
                <?php foreach ($role->member_roles as $assignee): ?>
                <tr>
                    <td><?= h($assignee->member->sca_name) ?>   <?= h($assignee->id) ?></td>
                    <td><?= h($assignee->start_on) ?></td>
                    <td><?= h($assignee->ended_on) ?></td>
                    <td><?= h($assignee->authorized_by->sca_name) ?></td>
                    <td class="actions">
                        <?= $this->Form->postLink( __('Deactivate'), ['controller' => 'MemberRoles', 'action' => 'deactivate', $assignee->id], ['confirm' => __('Are you sure you want to deactivate for {0}?', $assignee->member->sca_name), 'class' => 'btn btn-danger']) ?>
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


<?php 
    $this->start('modals');
    echo $this->Modal->create("Add Member to Role", ['id' => 'addMemberModal', 'close' => true]) ;
?>
    <fieldset>
        <?php
         echo $this->Form->create(null, ['id' => 'add_member__form', 'url' => ['controller' => 'MemberRoles', 'action' => 'quickAdd']]);
            echo $this->Form->control('sca_name', ['type' => 'text', 'label' => 'SCA Name', 'id'=> 'add_member__sca_name']);
            echo $this->Form->control('role_id', ['type' => 'hidden', 'value' => $role->id, 'id' => 'add_member__role_id']);
            echo $this->Form->control('member_id', ['type' => 'hidden', 'id' => 'add_member__member_id']);
         echo $this->Form->end()
                ?>
    </fieldset>
<?php
    echo $this->Modal->end([
        $this->Form->button('Submit',['class' => 'btn btn-primary', 'id' => 'add_member__submit', 'disabled' => 'disabled']),
        $this->Form->button('Close', ['data-bs-dismiss' => 'modal'])
    ]);
  $this->end(); 
?>

<?php
    //$this->append('css', $this->Html->css(['app/autocomplete.css']));
    //$this->append('script', $this->Html->script('https://code.jquery.com/ui/1.13.3/jquery-ui.min.js', ["crossorigin"=>"anonymous","integrity" => "sha256-sw0iNNXmOJbQhYFuC9OF2kOlD5KQKe1y5lfBn4C9Sjg="]));
    $this->append('script', $this->Html->script(['app/autocomplete.js']));
    $this->append('script', $this->Html->script(['app/roles/view.js']));
 ?>

