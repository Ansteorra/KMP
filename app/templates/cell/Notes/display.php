<?php
declare(strict_types=1);

$notesListId = 'notes-list-' . md5($entity_type . ':' . $entity_id);
?>
<div class="accordion mb-3" id="notesAccordian" data-controller="notes">
    <div id="<?= h($notesListId) ?>" data-notes-target="list" data-refresh-on="notes">
        <?= $this->element('notes_list', compact('notes')) ?>
    </div>
    <?php if ($canCreate) : ?>
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#note_new" aria-expanded="false" aria-controls="note_new">
                    Add a Note
                </button>
            </h2>
            <div id="note_new" class="accordion-collapse collapse" data-bs-parent="#notesAccordian">
                <div class="accordion-body">
                    <?= $this->Form->create($newNote, [
                        'url' => ['action' => 'add', 'controller' => 'Notes', 'plugin' => null],
                        'data-controller' => 'turbo-modal',
                        'data-turbo' => 'true',
                        'data-action' => 'submit->turbo-modal#submitAsTurboStream turbo-modal:saved->notes#saved',
                    ]) ?>
                    <fieldset>
                        <legend><?= __('Add Note') ?></legend>
                        <?php
                        echo $this->Form->hidden('entity_id', ['value' => $entity_id]);
                        echo $this->Form->hidden('entity_type', ['value' => $entity_type]);
                        echo $this->Form->control('subject');
                        echo $viewPrivate
                            ? $this->Form->control('private', [
                                'type' => 'checkbox',
                                'label' => 'Private',
                            ])
                            : '';
                        echo $this->Form->control('body', [
                            'label' => 'Note',
                        ]);
                        ?>
                    </fieldset>
                    <div class="text-end">
                        <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
                    </div>
                    <?= $this->Form->end() ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
