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

<div class="row align-items-start mb-3">
    <div class="col">
        <h3><?= __('Bestowal To-Do Templates') ?></h3>
    </div>
    <div class="col text-end">
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
