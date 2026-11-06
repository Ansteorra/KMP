import { Controller } from '@hotwired/stimulus';
import { createControllerScope } from '../services/controller-scope-service.js';

/** Configure the dialog on every visit; release listeners and delayed work on exit. */
class TodoCompletionModalController extends Controller {
    static values = {
        bindings: Object
    };
    connect() {
        this.lifecycle = createControllerScope();
        const { listen } = this.lifecycle;
        const bindings = this.bindingsValue;
        const modal = document.getElementById('todoCompleteModal');
        if (!modal) {
            return;
        }
        const labels = {
            complete: {
                title: bindings.completeToDo,
                prompt: bindings.markThisToDoComplete,
                submit: bindings.markComplete,
                submitClass: 'btn btn-success'
            },
            reopen: {
                title: bindings.reopenToDo,
                prompt: bindings.reopenThisToDo,
                submit: bindings.reopen,
                submitClass: 'btn btn-secondary'
            }
        };
        listen(modal, 'show.bs.modal', function (event) {
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
                titleEl.textContent = btn.dataset.todoConfirmation ? '' : title;
                titleEl.hidden = !!btn.dataset.todoConfirmation;
            }
            const promptEl = modal.querySelector('[data-todo-target="prompt"]');
            if (promptEl) {
                promptEl.firstChild.textContent = (btn.dataset.todoConfirmation || config.prompt) + ' ';
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
            listen(form, 'submit', function (event) {
                const section = modal.querySelector('[data-todo-target="specialForm"]');
                const control = modal.querySelector('[data-todo-target="bestowalGatheringControl"]');
                const hidden = control?.querySelector('[data-ac-target="hidden"]');
                const input = control?.querySelector('[data-ac-target="input"]');
                const error = modal.querySelector('[data-todo-target="bestowalGatheringError"]');
                const courtGroup = modal.querySelector('[data-todo-target="courtSlotGroup"]');
                const courtSelect = modal.querySelector('[data-todo-target="courtSlotSelect"]');
                const courtError = modal.querySelector('[data-todo-target="courtSlotError"]');
                const courtSlotRequired = section && !section.hidden && courtGroup && !courtGroup.hidden && courtSelect && courtSelect.value === '';
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
                if (!section || section.hidden || input?.disabled || courtGroup && !courtGroup.hidden || !hidden || hidden.value !== '') {
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
            listen(includePast, 'change', function () {
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
            const fields = Array.isArray(completionForm.fields) ? completionForm.fields : [];
            const outcome = fields.find(field => field.purpose === 'completion_outcome');
            const outcomeGroup = modal.querySelector('[data-todo-target="completionOutcomeGroup"]');
            const outcomeSelect = modal.querySelector('[data-todo-target="completionOutcomeSelect"]');
            outcomeGroup.hidden = !outcome;
            outcomeSelect.disabled = !outcome;
            outcomeSelect.required = !!outcome;
            outcomeSelect.replaceChildren(new Option(bindings.chooseACompletionOutcome, ''));
            if (outcome) {
                outcomeSelect.name = outcome.name;
                Object.entries(outcome.options || {}).forEach(([value, label]) => {
                    outcomeSelect.appendChild(new Option(String(label), String(value)));
                });
                modal.querySelector('[data-todo-target="completionOutcomeLabel"]').textContent = outcome.label;
                modal.querySelector('[data-todo-target="completionOutcomeHelp"]').textContent = outcome.help || '';
            }
            const field = fields.find(field => field.purpose !== 'completion_outcome') || null;
            const hasAutocomplete = field && field.type === 'autocomplete';
            const hasSelect = field && field.type === 'select';
            const hasField = hasAutocomplete || hasSelect;
            const blockedReason = completionForm.payload?.blockedReason || '';
            const submit = modal.querySelector('[data-todo-target="submit"]');
            if (submit) {
                submit.disabled = !!blockedReason;
            }
            section.hidden = !hasField && !blockedReason;
            const titleEl = modal.querySelector('[data-todo-target="specialTitle"]');
            if (titleEl) {
                titleEl.textContent = completionForm.title || bindings.additionalInformation;
            }
            const descriptionEl = modal.querySelector('[data-todo-target="specialDescription"]');
            if (descriptionEl) {
                descriptionEl.textContent = blockedReason || completionForm.description || '';
            }
            section.querySelectorAll('input, select, textarea, button').forEach(control => {
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
            const helpEl = modal.querySelector('[data-todo-target="bestowalGatheringHelp"]');
            if (helpEl) {
                helpEl.textContent = field.help || bindings.chooseTheGatheringWhereThisBestowalWill;
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
                control.querySelectorAll('input, select, textarea, button').forEach(field => {
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
            const entries = Array.isArray(field.optionOrder) ? field.optionOrder.map(value => [String(value), options[value]]) : Object.entries(options);
            select.replaceChildren(new Option(bindings.chooseACourtAssignment, ''));
            entries.forEach(([value, label]) => {
                select.appendChild(new Option(String(label), String(value)));
            });
            select.value = field.value !== null && field.value !== undefined ? String(field.value) : '';
            select.removeAttribute('aria-invalid');
            if (help) {
                help.textContent = field.help || (bindings.markThisToDoComplete + "0").replaceAll("KMP_BIND_10", () => bindings.chooseRoamingCourtOrAnEligibleScheduled);
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
            const group = control.closest('.mb-3') || control;
            const visible = !control.closest('[data-todo-target="specialForm"]')?.hidden && !group.hidden;
            const value = selection && selection.value ? String(selection.value) : '';
            const text = selection && selection.text ? String(selection.text) : '';
            if (input) {
                input.value = text;
                input.disabled = !visible || value !== '';
                input.required = visible;
                if (value !== '') {
                    input.removeAttribute('aria-invalid');
                }
            }
            if (hidden) {
                hidden.disabled = !visible;
                hidden.value = value;
            }
            if (hiddenText) {
                hiddenText.disabled = !visible;
                hiddenText.value = text;
            }
            if (clearBtn) {
                clearBtn.disabled = !visible || value === '';
            }
        }
    }
    disconnect() {
        this.lifecycle?.dispose();
    }
}
window.Controllers ??= {};
window.Controllers['todo-completion-modal'] = TodoCompletionModalController;
export default TodoCompletionModalController;
