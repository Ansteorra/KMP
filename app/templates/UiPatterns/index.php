<?php
declare(strict_types=1);

$this->extend('/layout/ui_showcase');
$this->assign('title', __('UI pattern showcase'));
$showcaseUrl = $this->Url->build(['controller' => 'UiPatterns', 'action' => 'index']);
$keys = array_keys($patterns);
$nextPattern = $keys[(array_search($pattern, $keys, true) + 1) % count($keys)];
?>
<div class="ui-review-bar">
    <div><strong><?= __('Pattern review') ?></strong><span><?= h($patterns[$pattern]) ?></span></div>
    <div class="ui-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary"
            data-bs-toggle="modal" data-bs-target="#ui-review-dialog">
            <?= $this->Icon->render('adjustments-horizontal') ?> <?= __('Examples & states') ?>
        </button>
        <?= $this->Html->link(
            $this->Icon->render('arrow-right'),
            ['action' => 'index', '?' => [
                'pattern' => $nextPattern, 'state' => $showcaseState, 'record' => $recordKind,
            ]],
            ['class' => 'btn ui-icon-button', 'escape' => false,
                'aria-label' => __('Next example: {0}', $patterns[$nextPattern])],
        ) ?>
    </div>
</div>
<p class="ui-review-note">
    <?= __('Real workflows, fictional records.') ?>
    <?= __('Saved grid views persist for this showcase; other edits are simulated.') ?>
    <?= __('Application navigation opens the current app.') ?>
</p>
<div class="ui-feedback" role="status" aria-live="polite" aria-atomic="true" data-ui-patterns-target="feedback"></div>
<div class="ui-pattern-content" data-pattern="<?= h($pattern) ?>" data-example-state="<?= h($showcaseState) ?>">
    <?= $this->element('uiPatterns/' . $pattern, compact('showcaseState', 'recordKind', 'pattern', 'showcaseUrl')) ?>
</div>
<div class="modal fade" id="ui-review-dialog" tabindex="-1" aria-labelledby="ui-review-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"><div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title h5" id="ui-review-title"><?= __('Explore the shared patterns') ?></h2>
            <button class="btn-close" type="button" data-bs-dismiss="modal"
                aria-label="<?= __('Close examples') ?>"></button>
        </div>
        <form method="get" action="<?= h($showcaseUrl) ?>">
            <div class="modal-body">
                <label class="form-label" for="ui-review-pattern"><?= __('Workflow') ?></label>
                <select class="form-select mb-3" name="pattern" id="ui-review-pattern">
                    <?php foreach ($patterns as $key => $label) : ?>
                        <option value="<?= h($key) ?>" <?= $key === $pattern ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label" for="ui-review-record"><?= __('Record example') ?></label>
                <select class="form-select mb-3" name="record" id="ui-review-record" aria-describedby="ui-record-help">
                    <?php foreach ($recordKinds as $key => $label) : ?>
                        <option value="<?= h($key) ?>" <?= $key === $recordKind ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-text" id="ui-record-help"><?= __('Used when reviewing the Record workflow.') ?></p>
                <label class="form-label" for="ui-review-state"><?= __('Situation') ?></label>
                <select class="form-select" name="state" id="ui-review-state">
                    <?php foreach ($states as $key => $label) : ?>
                        <option value="<?= h($key) ?>" <?= $key === $showcaseState ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">
                    <?= __('Cancel') ?>
                </button>
                <button class="btn btn-primary" type="submit"><?= __('Open example') ?></button>
            </div>
        </form>
    </div></div>
</div>
