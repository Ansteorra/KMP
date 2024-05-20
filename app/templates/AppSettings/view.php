<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting $appSetting
 */
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard'); ?>

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
        </table>
    </div>
</div>
