<?php
declare(strict_types=1);

/** @var \App\Model\Entity\Note $note */
?>
<div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#note_<?= $note->id ?>" aria-expanded="false" aria-controls="note_<?= $note->id ?>">
            <?= h($note->subject) ?> : <?= $this->Timezone->format($note->created_on) ?>
            - by <?= h($note->author->sca_name) ?>
            <?php if ($note->private) : ?>
                <span class="mx-3 badge bg-secondary">Private</span>
            <?php endif; ?>
        </button>
    </h2>
    <div id="note_<?= $note->id ?>" class="accordion-collapse collapse" data-bs-parent="#notesAccordian">
        <div class="accordion-body"><?= $this->Text->autoParagraph(h($note->body)) ?></div>
    </div>
</div>
