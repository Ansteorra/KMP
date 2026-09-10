import { Controller } from '@hotwired/stimulus';

/** One entry point for member security tasks, with explicit steps and consequences. */
class SecuritySettingsController extends Controller {
    static targets = ['panel', 'heading', 'password', 'confirmation', 'error', 'overview'];

    connect() {
        this.modal = this.element.closest('.modal');
        this.clear = () => this.element.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
        this.modal?.addEventListener('hide.bs.modal', this.clear);
    }

    deviceSetup(event) {
        this.overviewTargets.forEach(element => { element.hidden = event.detail.active; });
    }

    open(event) {
        this.show(event.currentTarget.dataset.step);
    }

    show(step) {
        if (step !== 'password-form') this.clear();
        this.panelTargets.forEach(panel => { panel.hidden = panel.dataset.step !== step; });
        this.panelTargets.find(panel => !panel.hidden)?.querySelector('h3')?.focus();
    }

    home() {
        this.show('home');
    }

    validatePassword(event) {
        this.errorTarget.textContent = '';
        this.confirmationTarget.removeAttribute('aria-invalid');
        if (this.passwordTarget.value !== this.confirmationTarget.value) {
            event.preventDefault();
            this.errorTarget.textContent = 'The passwords do not match. Enter the same new password in both boxes.';
            this.confirmationTarget.setAttribute('aria-invalid', 'true');
            this.confirmationTarget.focus();
        }
    }

    disconnect() {
        this.clear();
        this.modal?.removeEventListener('hide.bs.modal', this.clear);
    }
}
window.Controllers ||= {};
window.Controllers['security-settings'] = SecuritySettingsController;
export default SecuritySettingsController;
