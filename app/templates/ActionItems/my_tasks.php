<?php

/**
 * My To-Dos - Dataverse grid of the member's to-do action items.
 *
 * Mirrors the My Approvals grid: a lazy-loaded grid plus a shared completion
 * modal that marks a to-do complete (or reopens a completed one) via a Turbo
 * Stream that refreshes the grid in place.
 *
 * @var \App\View\AppView $this
 */

$completeUrl = $this->Url->build(['controller' => 'ActionItems', 'action' => 'complete']);
$reopenUrl = $this->Url->build(['controller' => 'ActionItems', 'action' => 'reopen']);
?>
<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': My To-Dos';
$this->KMP->endBlock(); ?>

<h3><i class="bi bi-check2-all me-2" aria-hidden="true"></i><?= __('My To-Dos') ?></h3>
<p class="text-muted">
    <?= __(
        'Checks assigned to you across the kingdom. Required checks must be done before an item can be finalized.',
    ) ?>
</p>

<?= $this->element('dv_grid', [
    'frameId' => 'action-items-grid',
    'dataUrl' => $this->Url->build(['controller' => 'ActionItems', 'action' => 'myTasksGridData']),
]) ?>

<!-- To-Do Completion Modal -->
<div class="modal fade" id="todoCompleteModal" tabindex="-1" aria-labelledby="todoCompleteModalLabel"
    aria-hidden="true" data-complete-url="<?= h($completeUrl) ?>" data-reopen-url="<?= h($reopenUrl) ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="todoCompleteModalLabel" data-todo-target="modalTitle">
                    <i class="bi bi-check2-square me-2" aria-hidden="true"></i><?= __('Complete To-Do') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
            </div>
            <?= $this->Form->create(null, [
                'url' => ['controller' => 'ActionItems', 'action' => 'complete'],
                'id' => 'todoCompleteForm',
                'data-turbo' => 'true',
                'data-controller' => 'turbo-modal',
                'data-action' => implode(' ', [
                    'submit->turbo-modal#submitAsTurboStream',
                    'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
                ]),
            ]) ?>
            <div class="modal-body bg-light-subtle">
                <input type="hidden" name="id" value="" data-todo-target="id">
                <?= $this->Form->hidden('page_context_url', ['value' => '']) ?>

                <p class="mb-3" data-todo-target="prompt">
                    <?= __('Mark this to-do complete?') ?>
                    <strong data-todo-target="title"></strong>
                </p>

                <div>
                    <label class="form-label" for="todoCompleteNote"><?= __('Note (optional)') ?></label>
                    <textarea class="form-control" id="todoCompleteNote" name="note" rows="3"
                        placeholder="<?= __('Add an optional note for the activity log...') ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-success" data-todo-target="submit">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= __('Mark Complete') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('todoCompleteModal');
    if (!modal) {
        return;
    }

    const labels = {
        complete: {
            title: <?= json_encode(__('Complete To-Do')) ?>,
            prompt: <?= json_encode(__('Mark this to-do complete?')) ?>,
            submit: <?= json_encode(__('Mark Complete')) ?>,
            submitClass: 'btn btn-success',
        },
        reopen: {
            title: <?= json_encode(__('Reopen To-Do')) ?>,
            prompt: <?= json_encode(__('Reopen this to-do?')) ?>,
            submit: <?= json_encode(__('Reopen')) ?>,
            submitClass: 'btn btn-secondary',
        },
    };

    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn) {
            return;
        }

        const mode = btn.dataset.todoMode === 'reopen' ? 'reopen' : 'complete';
        const id = btn.dataset.todoId || '';
        const title = btn.dataset.todoTitle || '';
        const config = labels[mode];

        const form = document.getElementById('todoCompleteForm');
        if (form) {
            form.action = mode === 'reopen' ? modal.dataset.reopenUrl : modal.dataset.completeUrl;
        }

        const idInput = modal.querySelector('[data-todo-target="id"]');
        if (idInput) {
            idInput.value = id;
        }

        const titleEl = modal.querySelector('[data-todo-target="title"]');
        if (titleEl) {
            titleEl.textContent = title;
        }

        const promptEl = modal.querySelector('[data-todo-target="prompt"]');
        if (promptEl) {
            promptEl.firstChild.textContent = config.prompt + ' ';
        }

        const modalTitle = modal.querySelector('[data-todo-target="modalTitle"]');
        if (modalTitle) {
            modalTitle.lastChild.textContent = config.title;
        }

        const submitBtn = modal.querySelector('[data-todo-target="submit"]');
        if (submitBtn) {
            submitBtn.className = config.submitClass;
            submitBtn.lastChild.textContent = config.submit;
        }
    });
});
</script>
