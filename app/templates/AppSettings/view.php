<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting $appSetting
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('Edit App Setting'), ['action' => 'edit', $appSetting->id], ['class' => 'nav-link']) ?></li>
<li><?= $this->Form->postLink(__('Delete App Setting'), ['action' => 'delete', $appSetting->id], ['confirm' => __('Are you sure you want to delete # {0}?', $appSetting->id), 'class' => 'nav-link']) ?></li>
<li><?= $this->Html->link(__('List App Settings'), ['action' => 'index'], ['class' => 'nav-link']) ?> </li>
<li><?= $this->Html->link(__('New App Setting'), ['action' => 'add'], ['class' => 'nav-link']) ?> </li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="appSettings view large-9 medium-8 columns content">
    <h3><?= h($appSetting->name) ?></h3>
    <div class="table-responsive">
        <table class="table table-striped">
            <tr>
                <th scope="row"><?= __('Name') ?></th>
                <td><?= h($appSetting->name) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Value') ?></th>
                <td><?= h($appSetting->value) ?></td>
            </tr>
            <tr>
                <th scope="row"><?= __('Id') ?></th>
                <td><?= $this->Number->format($appSetting->id) ?></td>
            </tr>
        </table>
    </div>
</div>
