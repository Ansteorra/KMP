<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var string $label
 * @var list<string> $steps
 * @var string $panelIdPrefix
 * @var string $controller
 */
?>
<nav aria-label="<?= h($label) ?>" class="mb-3" data-<?= h($controller) ?>-target="navigation" hidden>
    <ol class="list-unstyled d-flex flex-wrap gap-2 mb-0">
        <?php foreach ($steps as $index => $stepLabel) : ?>
            <?php $step = $index + 1; ?>
            <li>
                <button type="button" class="btn btn-outline-secondary" aria-controls="<?= h($panelIdPrefix . $step) ?>"
                    data-<?= h($controller) ?>-target="stepButton" data-action="<?= h($controller) ?>#goToStep"
                    data-step="<?= $step ?>" disabled>
                    <?= h(__('{0}. {1}', $step, $stepLabel)) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
