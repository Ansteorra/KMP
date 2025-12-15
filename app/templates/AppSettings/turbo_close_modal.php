<?php

/**
 * Turbo Close Modal Template
 * 
 * Returns a turbo-stream response that refreshes the specified frame and displays flash messages.
 * Used after modal form submissions to close the modal and refresh the grid.
 * 
 * @var \App\View\AppView $this
 * @var string $refreshFrame The turbo-frame ID to refresh
 * @var array|null $flashMessages The flash messages to display
 */

$refreshFrame = $refreshFrame ?? 'app-settings-grid-table';
$flashMessages = $flashMessages ?? [];

// Build the refresh URL (current action with gridData suffix)
$refreshUrl = $this->Url->build(['action' => 'gridData']);
?>
<?= $this->element('turbo_stream_flash', ['flashMessages' => $flashMessages]) ?>
<turbo-stream action="replace" target="<?= h($refreshFrame) ?>">
    <template>
        <turbo-frame id="<?= h($refreshFrame) ?>" src="<?= h($refreshUrl) ?>">
            <div class="text-center p-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </turbo-frame>
    </template>
</turbo-stream>