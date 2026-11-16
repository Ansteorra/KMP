<?php
declare(strict_types=1);

?>
<?= $this->element('turbo_stream_flash', compact('flashMessages')) ?>
<turbo-stream action="append" target="<?= h($notesListId) ?>">
    <template><?= $this->element('note', compact('note')) ?></template>
</turbo-stream>
