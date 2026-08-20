<?php

/**
 * @var \App\View\AppView $this
 * @var bool $canAddTemplate
 */

$this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': Bestowal To-Do Templates';
$this->KMP->endBlock();
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h3 class="mb-0"><?= __('Bestowal To-Do Templates') ?></h3>
    <div class="d-flex flex-wrap justify-content-end gap-2">
        <?php if ($canAddTemplate) : ?>
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
