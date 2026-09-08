import { Controller } from '@hotwired/stimulus';

/** Remember only an explicitly requested login identifier; app PINs are retired. */
class LoginDeviceAuthController extends Controller {
    static targets = ['email', 'rememberId', 'passwordForm'];

    connect() {
        localStorage.removeItem('kmp.quickLogin.config');
        localStorage.removeItem('kmp.quickLogin.deviceId');
        const email = localStorage.getItem('kmp.login.rememberedId') || '';
        this.emailTarget.value = email;
        this.rememberIdTarget.checked = !!email;
        this.onSubmit = () => {
            if (this.rememberIdTarget.checked) localStorage.setItem('kmp.login.rememberedId', this.emailTarget.value.trim());
            else localStorage.removeItem('kmp.login.rememberedId');
        };
        this.passwordFormTarget.addEventListener('submit', this.onSubmit);
    }

    disconnect() { this.passwordFormTarget.removeEventListener('submit', this.onSubmit); }
}
window.Controllers ||= {};
window.Controllers['login-device-auth'] = LoginDeviceAuthController;
export default LoginDeviceAuthController;
