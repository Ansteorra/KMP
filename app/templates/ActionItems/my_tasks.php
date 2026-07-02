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
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
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

                <div class="border rounded-3 bg-white shadow-sm p-3 mb-3" data-todo-target="specialForm" hidden>
                    <h6 class="fw-semibold mb-2" data-todo-target="specialTitle">
                        <?= __('Additional Information') ?>
                    </h6>
                    <p class="text-muted small mb-3" data-todo-target="specialDescription"></p>
                    <?= $this->KMP->autoCompleteControl(
                        $this->Form,
                        'bestowal_gathering_name',
                        'bestowal_gathering_id',
                        '#',
                        __('Bestowal Gathering'),
                        false,
                        false,
                        2,
                        [
                            'data-todo-target' => 'bestowalGatheringControl',
                            'data-ac-show-on-focus-value' => 'true',
                        ],
                    ) ?>
                    <div class="form-text" data-todo-target="bestowalGatheringHelp">
                        <?= __('Choose the gathering where this bestowal will be presented.') ?>
                    </div>
                    <div class="text-danger small" data-todo-target="bestowalGatheringError" hidden>
                        <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                        <?= __('Select the gathering where the bestowal will be presented.') ?>
                    </div>
                </div>

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
        const completionForm = parseCompletionForm(btn.dataset.todoCompletionForm || '{}');

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

        syncSpecialForm(mode === 'complete' ? completionForm : {});
    });

    const form = document.getElementById('todoCompleteForm');
    if (form) {
        form.addEventListener('submit', function (event) {
            const section = modal.querySelector('[data-todo-target="specialForm"]');
            const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
            const hidden = control?.querySelector('[data-ac-target="hidden"]');
            const input = control?.querySelector('[data-ac-target="input"]');
            const error = modal.querySelector('[data-todo-target="bestowalGatheringError"]');
            if (!section || section.hidden || !hidden || hidden.value !== '') {
                if (input) {
                    input.removeAttribute('aria-invalid');
                }
                if (error) {
                    error.hidden = true;
                }
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            if (input) {
                input.setAttribute('aria-invalid', 'true');
                input.focus();
            }
            if (error) {
                error.hidden = false;
            }
        }, true);
    }

    function parseCompletionForm(raw) {
        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function syncSpecialForm(completionForm) {
        const section = modal.querySelector('[data-todo-target="specialForm"]');
        if (!section) {
            return;
        }

        const field = Array.isArray(completionForm.fields) ? completionForm.fields[0] : null;
        const hasField = field && field.type === 'autocomplete';
        section.hidden = !hasField;
        section.querySelectorAll('input, select, textarea, button').forEach((control) => {
            control.disabled = !hasField;
        });
        if (!hasField) {
            resetGatheringControl();
            return;
        }

        const titleEl = modal.querySelector('[data-todo-target="specialTitle"]');
        if (titleEl) {
            titleEl.textContent = completionForm.title || '<?= __('Additional Information') ?>';
        }
        const descriptionEl = modal.querySelector('[data-todo-target="specialDescription"]');
        if (descriptionEl) {
            descriptionEl.textContent = completionForm.description || '';
        }
        const helpEl = modal.querySelector('[data-todo-target="bestowalGatheringHelp"]');
        if (helpEl) {
            helpEl.textContent = field.help
                || '<?= __('Choose the gathering where this bestowal will be presented.') ?>';
        }

        const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
        if (control && field.url) {
            control.dataset.acUrlValue = field.url;
        }
        setGatheringSelection(field.selection || null);
    }

    function resetGatheringControl() {
        setGatheringSelection(null);
        const error = modal.querySelector('[data-todo-target="bestowalGatheringError"]');
        if (error) {
            error.hidden = true;
        }
    }

    function setGatheringSelection(selection) {
        const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
        if (!control) {
            return;
        }
        const input = control.querySelector('[data-ac-target="input"]');
        const hidden = control.querySelector('[data-ac-target="hidden"]');
        const hiddenText = control.querySelector('[data-ac-target="hiddenText"]');
        const clearBtn = control.querySelector('[data-ac-target="clearBtn"]');
        const value = selection && selection.value ? String(selection.value) : '';
        const text = selection && selection.text ? String(selection.text) : '';
        if (input) {
            input.value = text;
            input.disabled = value !== '';
            input.required = !control.closest('[data-todo-target="specialForm"]')?.hidden;
            if (value !== '') {
                input.removeAttribute('aria-invalid');
            }
        }
        if (hidden) {
            hidden.value = value;
        }
        if (hiddenText) {
            hiddenText.value = text;
        }
        if (clearBtn) {
            clearBtn.disabled = value === '';
        }
    }
});
</script>
