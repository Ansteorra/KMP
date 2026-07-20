<?php

/**
 * Turbo Stream: flash + replace an edit frame with a new src (e.g. after validation error).
 *
 * @var \App\View\AppView $this
 * @var string $frameId
 * @var string $frameSrc
 * @var array|null $flashMessages
 */

$flashMessages = $flashMessages ?? [];
?>
<?= $this->element('turbo_stream_flash', ['flashMessages' => $flashMessages]) ?>
<turbo-stream action="replace" target="<?= h($frameId) ?>">
    <template>
        <turbo-frame id="<?= h($frameId) ?>" src="<?= h($frameSrc) ?>">
            <div class="text-center p-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden"><?= __('Loading...') ?></span>
                </div>
            </div>
        </turbo-frame>
    </template>
</turbo-stream>
