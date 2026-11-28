<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var string $controller
 * @var array|string $cancelUrl
 * @var string $submitLabel
 * @var array $submitOptions
 */
?>
<div class="d-flex flex-wrap gap-2 mt-3">
    <?= $this->Html->link(__('Cancel'), $cancelUrl, ['class' => 'btn btn-outline-secondary me-auto']) ?>
    <button type="button" class="btn btn-outline-secondary" data-action="<?= h($controller) ?>#previous"
        data-<?= h($controller) ?>-target="previousButton" hidden><?= __('Previous step') ?></button>
    <button type="button" class="btn btn-primary" data-action="<?= h($controller) ?>#next"
        data-<?= h($controller) ?>-target="nextButton" hidden><?= __('Continue') ?></button>
    <?= $this->Form->button($submitLabel, [
        'class' => 'btn btn-primary',
        'data-' . $controller . '-target' => 'submitButton',
    ] + ($submitOptions ?? [])) ?>
</div>
