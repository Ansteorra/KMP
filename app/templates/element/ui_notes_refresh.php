<?php
declare(strict_types=1);

?>
<turbo-stream action="update" method="morph" target="<?= h($notesListId) ?>">
    <template><?= $this->element('notes_list', compact('notes')) ?></template>
</turbo-stream>
