import WizardControllerBase from '../wizard-controller-base.js';

/** Guide gathering creation in the existing form and submit once from Review. */
class GatheringWizardController extends WizardControllerBase {
    static targets = ['externalFields', 'description', 'review'];

    static values = {
        errorStep: Number, empty: String, yes: String, no: String, endError: String, hostingGroupError: String,
    };

    connect() {
        this.element.noValidate = true;
        this.updateWebsite();
        const recoverError = !this.stepValue && this.errorStepValue > 0;
        this.showStep(this.stepValue || this.errorStepValue || 1, recoverError);
        if (recoverError) {
            this.panelTargets[this.stepValue - 1].querySelectorAll('details').forEach(details => {
                if (details.querySelector('.invalid-feedback, [aria-invalid="true"]')) details.open = true;
            });
        }
    }

    disconnect() {
        this.element.noValidate = false;
    }

    submit(event) {
        const review = this.panelTargets.length;
        if (this.stepValue < review) {
            event.preventDefault();
            this.next();
            return;
        }

        for (let step = 1; step < review; step += 1) {
            if (!this.validateStep(step)) {
                event.preventDefault();
                return;
            }
        }
    }

    updateWebsite() {
        const external = this.field('website_destination')?.value === 'external';
        this.externalFieldsTarget.hidden = !external;
        this.field('website_url').required = external;
    }

    onStepChange(_step, panel, isLast) {
        if (this.hasDescriptionTarget && panel.contains(this.descriptionTarget)) {
            this.descriptionTarget.dataset.controller = 'markdown-editor';
        }
        if (isLast) this.updateReview();
    }

    validateStep(step) {
        const panel = this.panelTargets[step - 1];
        const start = this.field('start_date');
        const end = this.field('end_date');
        end.setCustomValidity(start.value && end.value && end.value < start.value ? this.endErrorValue : '');
        const hostingGroup = panel.querySelector('[name="branch_name-Disp"]:enabled');
        if (hostingGroup) {
            const selectedBranch = panel.querySelector('[name="branch_id"]:enabled');
            hostingGroup.setCustomValidity(selectedBranch.value ? '' : this.hostingGroupErrorValue);
        }
        const invalid = [...panel.querySelectorAll('input, select, textarea')].find(field =>
            field.willValidate && !field.validity.valid
                && (!this.externalFieldsTarget.hidden || !this.externalFieldsTarget.contains(field)),
        );
        if (!invalid) return true;

        this.showStep(step, false);
        const details = invalid.closest('details');
        if (details) details.open = true;
        invalid.reportValidity();
        invalid.focus();
        return false;
    }

    field(name) {
        if (name === 'website_destination') {
            return this.element.querySelector('[name="website_destination"]:checked');
        }
        if (name === 'branch_id') {
            return this.element.querySelector('[data-gathering-form-target="branchAutocomplete"]:enabled [role="combobox"]')
                ?? this.element.querySelector('select[name="branch_id"]');
        }

        return this.element.querySelector(`[name="${name}"]:not([type="hidden"])`);
    }

    updateReview() {
        const dateTime = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
            dateStyle: 'medium', timeStyle: 'short', timeZone: 'UTC',
        });
        this.reviewTargets.forEach(target => {
            const name = target.dataset.field;
            const field = this.field(name);
            let value = field.selectedOptions?.[0]?.textContent.trim() || field.value.trim();
            if (name === 'website_destination') {
                value = field.value === 'external' ? this.field('website_url').value.trim() : field.labels[0].textContent.trim();
            }
            if (name === 'public_page_enabled') {
                const destination = this.field('website_destination').value;
                value = destination === 'kmp' || (destination === 'external' && field.checked)
                    ? this.yesValue : this.noValue;
            }
            if (name === 'start_date' || name === 'end_date') {
                value ||= this.field('start_date').value;
                if (value) value = dateTime.format(new Date(value + 'Z'));
            }
            target.textContent = value || this.emptyValue;
        });
    }
}

window.Controllers = window.Controllers || {};
window.Controllers['gathering-wizard'] = GatheringWizardController;

export default GatheringWizardController;
