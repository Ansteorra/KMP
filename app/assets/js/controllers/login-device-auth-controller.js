import { Controller } from '@hotwired/stimulus';

/** Email-first login without account lookup; remember identifiers only by opt-in. */
class LoginDeviceAuthController extends Controller {
    static targets = ['email', 'rememberId', 'passwordForm', 'password', 'passwordStep', 'continue', 'changeEmail'];
    static values = { retry: Boolean };

    connect() {
        let remembered = false;
        try {
            localStorage.removeItem('kmp.quickLogin.config');
            localStorage.removeItem('kmp.quickLogin.deviceId');
            const email = localStorage.getItem('kmp.login.rememberedId') || '';
            if (!this.emailTarget.value) this.emailTarget.value = email;
            this.rememberIdTarget.checked = !!email;
            remembered = !!email && this.emailTarget.value === email;
        } catch { /* Storage restrictions must not prevent password login. */ }
        this.showPassword((!!this.retryValue || remembered) && this.emailTarget.checkValidity());
    }

    submit(event) {
        if (!this.passwordVisible) {
            event.preventDefault();
            if (this.emailTarget.reportValidity()) this.showPassword(true);
            return;
        }
        this.dispatch('submit');
        try {
            if (this.rememberIdTarget.checked) localStorage.setItem('kmp.login.rememberedId', this.emailTarget.value.trim());
            else localStorage.removeItem('kmp.login.rememberedId');
        } catch { /* Remembering an identifier is optional. */ }
    }

    showPassword(visible) {
        this.passwordVisible = visible;
        this.passwordStepTarget.hidden = !visible;
        this.passwordTarget.disabled = !visible;
        this.continueTarget.hidden = visible;
        this.changeEmailTarget.hidden = !visible;
        this.emailTarget.readOnly = visible;
        if (visible) this.passwordTarget.focus();
    }

    back() {
        this.passwordTarget.value = '';
        this.showPassword(false);
        this.emailTarget.focus();
    }

    disconnect() { this.passwordTarget.value = ''; }
}
window.Controllers ||= {};
window.Controllers['login-device-auth'] = LoginDeviceAuthController;
export default LoginDeviceAuthController;
