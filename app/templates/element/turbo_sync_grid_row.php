<?php

/**
 * Turbo Stream: flash + replace or remove a single Dataverse grid row.
 *
 * @var \App\View\AppView $this
 * @var string $rowDomId
 * @var string $rowHtml
 * @var array|null $flashMessages
 * @var string $streamAction replace|remove
 */

$flashMessages = $flashMessages ?? [];
$streamAction = $streamAction ?? 'replace';
?>
<?= $this->element('turbo_stream_flash', ['flashMessages' => $flashMessages]) ?>
<turbo-stream action="<?= h($streamAction) ?>" target="<?= h($rowDomId) ?>">
    <?php if ($streamAction === 'replace'): ?>
    <template>
        <?= $rowHtml ?>
    </template>
    <?php endif; ?>
</turbo-stream>
