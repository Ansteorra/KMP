<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var array<array{value: string, label: string|null}> $royalProgress
 */
?>
<?php if ($royalProgress !== []) : ?>
    <?php
    $titles = array_filter(array_column($royalProgress, 'label'));
    $label = $titles ? __('Royal Progress: {0}', implode(', ', $titles)) : __('Royal Progress');
    ?>
    <span class="badge bg-warning text-dark" data-royal-progress-badge
        role="img" title="<?= h($label) ?>" aria-label="<?= h($label) ?>">
        <kmp-icon name="crown" aria-hidden="true"></kmp-icon>
    </span>
<?php endif; ?>
