<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting $appSetting
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

<?php $this->start('tb_actions'); ?>
<li><?= $this->Html->link(__('List App Settings'), ['action' => 'index'], ['class' => 'nav-link']) ?></li>
<?php $this->end(); ?>
<?php $this->assign('tb_sidebar', '<ul class="nav flex-column">' . $this->fetch('tb_actions') . '</ul>'); ?>

<div class="appSettings form content">
    <?= $this->Form->create($appSetting) ?>
    <fieldset>
        <legend><?= __('Add App Setting') ?></legend>
        <?php
            echo $this->Form->control('name');
            echo $this->Form->control('value');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
