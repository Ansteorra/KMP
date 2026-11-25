import { Controller } from '@hotwired/stimulus';

/** Shared navigation for guided forms. Subclasses validate fields and update the visible step. */
export default class WizardControllerBase extends Controller {
    static targets = ['navigation', 'panel', 'stepButton', 'previousButton', 'nextButton', 'submitButton'];
    static values = { step: Number, furthestStep: Number };

    next() {
        if (this.stepValue < this.panelTargets.length && this.validateStep(this.stepValue)) {
            this.showStep(this.stepValue + 1);
        }
    }

    previous() {
        this.showStep(this.stepValue - 1);
    }

    goToStep(event) {
        const requested = Number(event.currentTarget.dataset.step);
        if (requested > this.furthestStepValue || requested === this.stepValue) return;

        for (let step = this.stepValue; step < requested; step += 1) {
            if (!this.validateStep(step)) return;
        }
        this.showStep(requested);
    }

    showStep(step, focus = true) {
        const panels = this.panelTargets;
        const panel = panels[step - 1];
        if (!panel) return;

        this.stepValue = step;
        this.furthestStepValue = Math.max(this.furthestStepValue, step);
        this.navigationTarget.hidden = false;
        panels.forEach(target => { target.hidden = target !== panel; });
        this.stepButtonTargets.forEach((button, index) => {
            const current = index + 1 === step;
            button.disabled = index + 1 > this.furthestStepValue;
            button.classList.toggle('active', current);
            if (current) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
        });

        const isLast = step === panels.length;
        this.previousButtonTarget.hidden = step === 1;
        this.nextButtonTarget.hidden = isLast;
        this.submitButtonTarget.hidden = !isLast;
        this.onStepChange(step, panel, isLast);
        if (focus) panel.querySelector('h2').focus();
    }
}
