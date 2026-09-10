import { Controller } from '@hotwired/stimulus';
import vault from '../services/offline-vault-service.js';
import { trustThisDevice, updateTrustedDevice, offlineStatus } from '../services/offline-runtime-service.js';

/** Device recovery only. Normal mobile templates and controllers own all application rendering. */
class OfflineVaultController extends Controller {
    static targets = ['status', 'content', 'enroll', 'locked', 'deviceUnlock', 'passphraseForm', 'passphrase', 'trustLegacy', 'device', 'signIn'];
    connect() {
        this.connected = true;
        this.onState = () => this.render().catch(() => this.message('Unable to open saved information.'));
        this.onProgress = () => this.resumeOnline();
        this.onOnline = () => { this.onState(); updateTrustedDevice(true).catch(() => {}); };
        window.addEventListener('kmp:offline-state', this.onState);
        window.addEventListener('kmp:offline-progress', this.onProgress);
        window.addEventListener('online', this.onOnline);
        window.addEventListener('offline', this.onState);
        this.timer = setInterval(this.onState, 15000);
        this.onState();
    }
    disconnect() {
        this.connected = false;
        clearInterval(this.timer);
        window.removeEventListener('kmp:offline-state', this.onState);
        window.removeEventListener('kmp:offline-progress', this.onProgress);
        window.removeEventListener('online', this.onOnline);
        window.removeEventListener('offline', this.onState);
        if (!vault.trusted) vault.lock();
    }
    message(text) {
        if (!this.connected) return;
        this.statusTarget.textContent = text;
        this.statusTarget.hidden = !text;
    }
    async render() {
        const record = await vault.metadata();
        if (!this.connected) return;
        const open = !!record && !!vault.key && record.expiresAt > Date.now();
        this.contentTarget.hidden = !open;
        if (!open && this.wasOpen) window.dispatchEvent(new CustomEvent('kmp:offline-revoked'));
        this.enrollTarget.hidden = !!record;
        this.lockedTarget.hidden = !record || !!vault.key || record.wrapper.method === 'trusted';
        this.deviceUnlockTarget.hidden = record?.wrapper.method !== 'device';
        this.passphraseFormTarget.hidden = record?.wrapper.method !== 'passphrase';
        this.trustLegacyTarget.hidden = !navigator.onLine || !open || vault.trusted;
        if (this.hasSignInTarget) this.signInTarget.hidden = !navigator.onLine;
        this.deviceTarget.hidden = !record;
        // The shared device panel owns connection/progress and modern unlock instructions.
        this.message(record && record.expiresAt <= Date.now()
            ? 'Connect and sign in to update your saved information. Waiting RSVPs are kept.'
            : record && !open && !record.wrapper.unlockMethod ? 'Opening your saved information…' : '');
        if (open && !this.wasOpen) window.dispatchEvent(new CustomEvent('kmp:offline-unlocked'));
        this.wasOpen = open;
    }
    resumeOnline() {
        if (!this.connected || this.busy || !vault.trusted || !navigator.onLine || !offlineStatus.ready) return;
        // The login controller follows the server's device-routing rules after local unlock.
        if (/^\/members\/login\/?$/i.test(location.pathname)) return;
        const destinations = { 'auth-card': '/members/view-mobile-card', rsvps: '/gathering-attendances/my-rsvps', events: '/gatherings/mobile-calendar' };
        const path = ['/offline', '/members/login'].includes(location.pathname)
            ? destinations[document.body.dataset.section] : location.pathname + location.search;
        if (Date.now() - Number(sessionStorage.getItem('kmp.offline.resume')) < 60000) return;
        sessionStorage.setItem('kmp.offline.resume', String(Date.now()));
        location.replace(path);
    }
    async run(operation) {
        if (this.busy) return;
        this.busy = true;
        try { await operation(); await this.render(); }
        catch (error) { this.message(error.message); }
        finally { this.busy = false; this.passphraseTarget.value = ''; }
    }
    async trust() { await this.run(() => trustThisDevice()); this.resumeOnline(); }
    async unlockDevice() { await this.run(() => vault.unlock()); }
    async unlockPassphrase(event) { event.preventDefault(); await this.run(() => vault.unlock(this.passphraseTarget.value)); }
    lock() { vault.signOut(); }
    async forget() {
        if (!await window.KMP_accessibility.confirm('Remove saved information and any unsent RSVPs from this device?')) return;
        await this.run(() => vault.clear());
        this.enrollTarget.querySelector('a')?.focus();
    }
}
window.Controllers ||= {};
window.Controllers['offline-vault'] = OfflineVaultController;
export default OfflineVaultController;
