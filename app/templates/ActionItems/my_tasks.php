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
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="todoIncludePastGatherings"
                            name="include_past" value="1" data-todo-target="includePastGatherings">
                        <label class="form-check-label" for="todoIncludePastGatherings">
                            <?= __('Include past gatherings') ?>
                        </label>
                    </div>
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
                    <div class="mt-3" data-todo-target="courtSlotGroup" hidden>
                        <label class="form-label" for="todoCourtSlot">
                            <?= __('Court Assignment') ?>
                        </label>
                        <select class="form-select" id="todoCourtSlot" name="gathering_scheduled_activity_id"
                            data-todo-target="courtSlotSelect" aria-describedby="todoCourtSlotHelp">
                            <option value=""><?= __('Choose a court assignment') ?></option>
                        </select>
                        <div class="form-text" id="todoCourtSlotHelp" data-todo-target="courtSlotHelp">
                            <?= __('Choose Roaming Court or an eligible scheduled court activity.') ?>
                        </div>
                        <div class="text-danger small" data-todo-target="courtSlotError" hidden>
                            <i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>
                            <?= __('Select Roaming Court or a scheduled court activity.') ?>
                        </div>
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
            const courtGroup = modal.querySelector('[data-todo-target="courtSlotGroup"]');
            const courtSelect = modal.querySelector('[data-todo-target="courtSlotSelect"]');
            const courtError = modal.querySelector('[data-todo-target="courtSlotError"]');
            const courtSlotRequired = section && !section.hidden
                && courtGroup && !courtGroup.hidden
                && courtSelect && courtSelect.value === '';
            if (courtSlotRequired) {
                event.preventDefault();
                event.stopImmediatePropagation();
                courtSelect.setAttribute('aria-invalid', 'true');
                courtSelect.focus();
                if (courtError) {
                    courtError.hidden = false;
                }
                return;
            }

            if (!section || section.hidden || courtGroup && !courtGroup.hidden || !hidden || hidden.value !== '') {
                if (input) {
                    input.removeAttribute('aria-invalid');
                }
                if (error) {
                    error.hidden = true;
                }
                if (courtSelect) {
                    courtSelect.removeAttribute('aria-invalid');
                }
                if (courtError) {
                    courtError.hidden = true;
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

    const includePast = modal.querySelector('[data-todo-target="includePastGatherings"]');
    if (includePast) {
        includePast.addEventListener('change', function () {
            const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
            if (!control || !control.dataset.baseUrl) {
                return;
            }
            resetGatheringControl();
            control.dataset.acUrlValue = buildSpecialFormLookupUrl(control.dataset.baseUrl);
        });
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
        const hasAutocomplete = field && field.type === 'autocomplete';
        const hasSelect = field && field.type === 'select';
        const hasField = hasAutocomplete || hasSelect;
        section.hidden = !hasField;
        section.querySelectorAll('input, select, textarea, button').forEach((control) => {
            control.disabled = !hasField;
        });
        const includePastControl = modal.querySelector('[data-todo-target="includePastGatherings"]');
        if (includePastControl) {
            includePastControl.checked = false;
            includePastControl.closest('.form-check')?.toggleAttribute('hidden', !hasAutocomplete);
            includePastControl.disabled = !hasAutocomplete;
        }
        toggleGatheringField(hasAutocomplete);
        toggleCourtSlotField(hasSelect, field || {});
        if (!hasField) {
            resetGatheringControl();
            resetCourtSlotControl();
            const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
            if (control) {
                delete control.dataset.baseUrl;
            }
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
                || '<?= __(
                    'Choose the gathering where this bestowal will be presented. ' .
                    'Use Include past gatherings to backdate scheduling.',
                ) ?>';
        }

        if (!hasAutocomplete) {
            return;
        }

        const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
        if (control && field.url) {
            control.dataset.baseUrl = field.url;
            control.dataset.acUrlValue = buildSpecialFormLookupUrl(field.url);
        }
        setGatheringSelection(field.selection || null);
    }

    function buildSpecialFormLookupUrl(rawUrl) {
        const includePastControl = modal.querySelector('[data-todo-target="includePastGatherings"]');
        if (!includePastControl || !includePastControl.checked) {
            return rawUrl;
        }

        const url = new URL(rawUrl, window.location.href);
        url.searchParams.set('include_past', '1');
        return url.toString();
    }

    function resetGatheringControl() {
        setGatheringSelection(null);
        const error = modal.querySelector('[data-todo-target="bestowalGatheringError"]');
        if (error) {
            error.hidden = true;
        }
    }

    function resetCourtSlotControl() {
        const select = modal.querySelector('[data-todo-target="courtSlotSelect"]');
        const error = modal.querySelector('[data-todo-target="courtSlotError"]');
        if (select) {
            select.value = '';
            select.removeAttribute('aria-invalid');
        }
        if (error) {
            error.hidden = true;
        }
    }

    function toggleGatheringField(visible) {
        const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
        const help = modal.querySelector('[data-todo-target="bestowalGatheringHelp"]');
        const group = control?.closest('.mb-3') || control;
        if (group) {
            group.hidden = !visible;
        }
        if (control) {
            control.querySelectorAll('input, select, textarea, button').forEach((field) => {
                field.disabled = !visible;
            });
        }
        if (help) {
            help.hidden = !visible;
        }
        if (!visible) {
            resetGatheringControl();
        }
    }

    function toggleCourtSlotField(visible, field) {
        const group = modal.querySelector('[data-todo-target="courtSlotGroup"]');
        const select = modal.querySelector('[data-todo-target="courtSlotSelect"]');
        const help = modal.querySelector('[data-todo-target="courtSlotHelp"]');
        if (group) {
            group.hidden = !visible;
        }
        if (!select) {
            return;
        }

        select.disabled = !visible;
        select.required = visible;
        if (!visible) {
            resetCourtSlotControl();
            return;
        }

        const options = field.options && typeof field.options === 'object' ? field.options : {};
        select.replaceChildren(new Option('<?= __('Choose a court assignment') ?>', ''));
        Object.entries(options).forEach(([value, label]) => {
            select.appendChild(new Option(String(label), String(value)));
        });
        select.value = field.value !== null && field.value !== undefined ? String(field.value) : '';
        select.removeAttribute('aria-invalid');
        if (help) {
            help.textContent = field.help
                || '<?= __('Choose Roaming Court or an eligible scheduled court activity.') ?>';
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
