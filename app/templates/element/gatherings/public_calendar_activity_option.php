<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var string $name
 * @var string|int $value
 * @var string $label
 * @var bool $selected
 * @var \App\Model\Entity\GatheringActivity|null $activity
 * @var string|null $icon
 */
?>
<label class="kc-activity-choice" data-public-calendar-target="activityOption">
    <input class="btn-check" type="checkbox" name="<?= h($name) ?>" value="<?= h($value) ?>"
        data-action="change->public-calendar#updateCount" <?= $selected ? 'checked' : '' ?>>
    <span class="kc-choice-content">
        <span class="kc-choice-icon" aria-hidden="true">
            <?php if (isset($activity)) : ?>
                <?= $this->GatheringActivity->badge($activity, true) ?>
            <?php else : ?>
                <?= $this->Icon->render($icon, ['size' => 20]) ?>
            <?php endif; ?>
        </span>
        <span class="kc-choice-name"><?= h($label) ?></span>
        <?= $this->Icon->render('check', ['class' => 'kc-choice-selected', 'size' => 16]) ?>
    </span>
</label>
