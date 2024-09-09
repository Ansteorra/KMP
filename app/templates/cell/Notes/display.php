<div class="accordion mb-3" id="notesAccordian">
    <?php if (!empty($notes)) : ?>
    <?php foreach ($notes as $note) : ?>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#note_<?= $note->id ?>" aria-expanded="false" aria-controls="note_<?= $note->id ?>">
                <?= h($note->subject) ?> : <?= h(
                                                        $note->created_on,
                                                    ) ?> - by <?= h($note->author->sca_name) ?>
                <?= $note->private
                            ? '<span class="mx-3 badge bg-secondary">Private</span>'
                            : "" ?>
            </button>
        </h2>
        <div id="note_<?= $note->id ?>" class="accordion-collapse collapse" data-bs-parent="#notesAccordian">
            <div class="accordion-body">
                <?= $this->Text->autoParagraph(
                            h($note->body),
                        ) ?>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($canCreate) : ?>
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#note_new" aria-expanded="false" aria-controls="#note_new">
                Add a Note
            </button>
        </h2>
        <div id="note_new" class="accordion-collapse collapse" data-bs-parent="#notesAccordian">
            <div class="accordion-body">
                <?= $this->Form->create($newNote, [
                        "url" => ["action" => "Add", 'controller' => 'Notes'],
                    ]) ?>
                <fieldset>
                    <legend><?= __("Add Note") ?></legend>
                    <?php
                        echo $this->Form->hidden("topic_id", ["value" => $topic_id]);
                        echo $this->Form->hidden("topic_model", ["value" => $topic_model]);
                        echo $this->Form->control("subject");
                        echo $viewPrivate
                            ? $this->Form->control("private", [
                                "type" => "checkbox",
                                "label" => "Private",
                            ])
                            : "";
                        echo $this->Form->control("body", [
                            "label" => "Note",
                        ]);
                        ?>
                </fieldset>
                <div class='text-end'><?= $this->Form->button(
                                                __("Submit"),
                                                ["class" => "btn-primary"],
                                            ) ?></div>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>