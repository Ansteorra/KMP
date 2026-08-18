<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $user
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Bestowal To-Do Templates';
$this->KMP->endBlock();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h3 class="mb-0"><?= __('Bestowal To-Do Templates') ?></h3>
    <div class="d-flex flex-wrap justify-content-end gap-2">
        <?php if ($user->checkCan('syncOpenBestowals', 'Awards.BestowalTodoTemplates')) : ?>
            <?= $this->Form->create(null, [
                'url' => ['action' => 'syncOpenBestowals'],
                'class' => 'm-0',
                'data-turbo-frame' => '_top',
            ]) ?>
            <?= $this->Form->button(__('Sync Open Bestowals Now'), [
                'class' => 'btn btn-outline-primary',
                'data-confirm-message' => __(
                    'Synchronize every open bestowal with its award\'s current to-do template? '
                    . 'To-dos may be added, updated, reopened, or cancelled. '
                    . 'Synchronization never marks a bestowal Given.',
                ),
                'data-confirm-title' => __('Synchronize open bestowals'),
                'data-confirm-label' => __('Sync Now'),
            ]) ?>
            <?= $this->Form->end() ?>
        <?php endif; ?>
        <?php if ($user->checkCan('add', 'Awards.BestowalTodoTemplates')) : ?>
            <?= $this->Html->link(
                __('Add To-Do Template'),
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'data-turbo-frame' => '_top'],
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.BestowalTodoTemplates.index.main',
    'frameId' => 'bestowal-todo-templates-grid',
    'dataUrl' => $this->Url->build(['action' => 'gridData']),
]) ?>
