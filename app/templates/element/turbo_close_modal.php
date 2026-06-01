<?php

/**
 * Turbo Stream: flash messages + replace a grid table turbo-frame.
 *
 * @var \App\View\AppView $this
 * @var string $refreshFrame Turbo-frame id (e.g. recommendations-grid-table)
 * @var string $refreshUrl gridData URL including preserved query params
 * @var array|null $flashMessages Session flash (already consumed)
 */

$refreshFrame = $refreshFrame ?? 'app-settings-grid-table';
$refreshUrl = $refreshUrl ?? $this->Url->build(['action' => 'gridData']);
$flashMessages = $flashMessages ?? [];
?>
<?= $this->element('turbo_stream_flash', ['flashMessages' => $flashMessages]) ?>
<turbo-stream action="replace" target="<?= h($refreshFrame) ?>">
    <template>
        <turbo-frame id="<?= h($refreshFrame) ?>" src="<?= h($refreshUrl) ?>">
            <div class="text-center p-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden"><?= __('Loading...') ?></span>
                </div>
            </div>
        </turbo-frame>
    </template>
</turbo-stream>
