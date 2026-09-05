import { Controller } from '@hotwired/stimulus';
import vault, { purgeLegacyOfflineStorage } from '../services/offline-vault-service.js';
import { currentOfflineContext, refreshOfflineSnapshot } from '../services/offline-data-service.js';
import rsvps from '../services/rsvp-cache-service.js';

/** Accessible foreground enrollment, unlock, refresh and RSVP sync for the public offline shell. */
class OfflineVaultController extends Controller {
    static targets = ['status', 'enroll', 'newPassphrase', 'locked', 'deviceUnlock', 'passphraseForm', 'passphrase',
        'unlocked', 'verified', 'card', 'rsvps', 'events', 'forget'];

    async connect() {
        this.connected = true;
        this.lastActivity = Date.now();
        this.onState = () => this.render().catch(() => this.message('Unable to read offline storage.'));
        this.onActivity = () => { this.lastActivity = Date.now(); };
        this.onVisibility = () => { if (document.hidden && !this.devicePrompt) vault.lock(); };
        window.addEventListener('kmp:offline-state', this.onState);
        window.addEventListener('pointerdown', this.onActivity);
        window.addEventListener('keydown', this.onActivity);
        document.addEventListener('visibilitychange', this.onVisibility);
        this.timer = setInterval(() => {
            if (vault.key && Date.now() - this.lastActivity > 300000) vault.lock();
            vault.metadata().catch(() => {});
        }, 15000);
        try {
            await purgeLegacyOfflineStorage();
            if ('serviceWorker' in navigator && navigator.onLine) {
                navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' }).catch(() => {});
            }
            await rsvps.init();
            await this.render();
        } catch (error) { this.message(error.message); }
    }

    disconnect() {
        this.connected = false;
        clearInterval(this.timer);
        window.removeEventListener('kmp:offline-state', this.onState);
        window.removeEventListener('pointerdown', this.onActivity);
        window.removeEventListener('keydown', this.onActivity);
        document.removeEventListener('visibilitychange', this.onVisibility);
        vault.lock();
    }

    message(text) { if (this.connected) this.statusTarget.textContent = text; }

    async run(operation) {
        if (this.busy) return;
        this.busy = true;
        this.element.setAttribute('aria-busy', 'true');
        try { await operation(); } catch (error) { this.message(error.message || 'The operation did not complete. Please retry.'); }
        finally {
            this.busy = false;
            this.element.removeAttribute('aria-busy');
            this.newPassphraseTarget.value = ''; this.passphraseTarget.value = '';
            this.lastActivity = Date.now();
            if (document.hidden) vault.lock();
        }
    }

    async prepareShell() {
        if (!('serviceWorker' in navigator)) throw new Error('This browser does not support offline access.');
        const registration = await navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' });
        const worker = registration.installing || registration.waiting || registration.active;
        if (worker?.state !== 'activated') await new Promise((resolve, reject) => {
            const timer = setTimeout(() => { worker?.removeEventListener('statechange', changed); reject(new Error('Offline update is not ready. Reconnect and reload.')); }, 60000);
            const changed = () => {
                if (worker.state === 'activated') { clearTimeout(timer); worker.removeEventListener('statechange', changed); resolve(); }
                if (worker.state === 'redundant') { clearTimeout(timer); worker.removeEventListener('statechange', changed); reject(new Error('Offline update failed. Reload online.')); }
            };
            worker?.addEventListener('statechange', changed);
            changed();
        });
        await new Promise((resolve, reject) => {
            const channel = new MessageChannel();
            const timer = setTimeout(() => { channel.port1.close(); reject(new Error('Offline preparation timed out. Reload online.')); }, 60000);
            channel.port1.onmessage = event => {
                clearTimeout(timer); channel.port1.close();
                if (event.data?.ready) resolve(); else reject(new Error('Offline assets could not be saved. Reconnect and retry.'));
            };
            registration.active.postMessage({ type: 'PREPARE_OFFLINE' }, [channel.port2]);
        });
    }

    async enrollDevice() { await this.enroll('device'); }
    async enrollPassphrase(event) { event.preventDefault(); await this.enroll('passphrase', this.newPassphraseTarget.value); }
    async enroll(method, passphrase = '') {
        await this.run(async () => {
            this.message('Setting up protected offline access…');
            const context = await currentOfflineContext();
            await this.prepareShell();
            this.devicePrompt = method === 'device';
            try { await vault.enroll(context, method, passphrase); } finally { this.devicePrompt = false; }
            if (document.hidden) { vault.lock(); throw new Error('Return to this page to unlock offline access.'); }
            await refreshOfflineSnapshot();
            await this.render();
            this.message('Offline data saved. Test device unlock in airplane mode before travelling.');
            this.unlockedTarget.querySelector('button')?.focus();
        });
    }
    async unlockDevice() { await this.unlock(); }
    async unlockPassphrase(event) { event.preventDefault(); await this.unlock(this.passphraseTarget.value); }
    async unlock(passphrase = '') {
        await this.run(async () => {
            this.devicePrompt = (await vault.metadata())?.wrapper.method === 'device';
            try { await vault.unlock(passphrase); } finally { this.devicePrompt = false; }
            if (document.hidden) { vault.lock(); throw new Error('Return to this page to unlock offline access.'); }
            await this.render();
            this.unlockedTarget.querySelector('button')?.focus();
        });
    }
    async refresh() {
        await this.run(async () => { await this.prepareShell(); await refreshOfflineSnapshot(); await this.render(); this.message('Offline data refreshed for up to seven days.'); });
    }
    async sync() {
        await this.run(async () => {
            const result = await rsvps.syncPendingRsvps();
            await this.render();
            this.message(result.skipped ? 'Unlock and connect online to sync.' : `${result.success} RSVPs synced. ${result.failed} could not sync; they remain pending.`);
        });
    }
    async lock() {
        vault.lock();
        await this.render();
        if (!this.lockedTarget.hidden) (this.deviceUnlockTarget.hidden ? this.passphraseTarget : this.deviceUnlockTarget).focus();
    }
    async forget() {
        const confirmed = await window.KMP_accessibility.confirm('Remove saved offline data and any pending RSVPs?');
        if (!confirmed) return;
        await this.run(async () => { await vault.clear(); await this.render(); this.message('Offline data removed.'); this.enrollTarget.querySelector('button')?.focus(); });
    }
    async cancelPending(event) {
        await this.run(async () => {
            await rsvps.removePendingRsvp(event.currentTarget.dataset.requestId);
            await this.render(); this.message('Pending RSVP removed.');
        });
    }
    async queue(event) {
        await this.run(async () => {
            await rsvps.queueOfflineRsvp({ gathering_id: Number(event.currentTarget.dataset.gatheringId) });
            await this.render(); this.message('Private RSVP queued. Unlock and sync when online.');
        });
    }

    async render() {
        const renderId = Symbol();
        this.renderId = renderId;
        const metadata = await vault.metadata();
        if (!this.connected || this.renderId !== renderId) return;
        const unlocked = !!metadata && !!vault.key;
        this.enrollTarget.hidden = !!metadata;
        this.lockedTarget.hidden = !metadata || unlocked;
        this.unlockedTarget.hidden = !unlocked;
        this.forgetTarget.hidden = !metadata;
        this.cardTarget.replaceChildren(); this.rsvpsTarget.replaceChildren(); this.eventsTarget.replaceChildren();
        if (!metadata) {
            this.message(sessionStorage.getItem('kmp.offline.migrated') === '1'
                ? 'The security update removed old offline data and unsynced RSVPs. Sign in online to save protected copies.'
                : 'Sign in online, then choose how to protect offline access.');
            return;
        }
        this.deviceUnlockTarget.hidden = metadata.wrapper.method !== 'device';
        this.passphraseFormTarget.hidden = metadata.wrapper.method !== 'passphrase';
        if (!unlocked) { this.message('Saved information is locked.'); return; }
        const data = await vault.read();
        if (!this.connected || !vault.key || this.renderId !== renderId) return;
        this.verifiedTarget.textContent = `Last verified ${new Date(metadata.verifiedAt).toLocaleString()}. Offline access ends ${new Date(metadata.expiresAt).toLocaleString()}.`;
        this.renderCard(data.card);
        data.rsvps.forEach(item => {
            const entry = this.eventEntry(item);
            if (item.public_note) this.appendText(entry, 'p', item.public_note);
            const sharing = [item.share_with_kingdom && 'Kingdom', item.share_with_hosting_group && 'hosting group', item.share_with_crown && 'Crown'].filter(Boolean);
            this.appendText(entry, 'p', sharing.length ? `Shared with ${sharing.join(', ')}` : 'Private RSVP');
            this.rsvpsTarget.append(entry);
        });
        if (!data.rsvps.length) this.appendText(this.rsvpsTarget, 'p', 'No saved RSVPs.');
        const events = [...new Map(Object.values(data.months).flat().map(item => [item.gathering_id, item])).values()];
        for (const item of events) {
            if (new Date(item.end_date + 'T23:59:59') < new Date()) continue;
            const entry = this.eventEntry(item);
            const pending = data.pending.some(row => row.gathering_id === item.gathering_id);
            const attending = data.rsvps.some(row => row.gathering_id === item.gathering_id);
            if (pending || attending || item.is_cancelled) this.appendText(entry, 'p', pending ? 'RSVP pending sync' : item.is_cancelled ? 'Cancelled' : 'Attending');
            else {
                const button = this.appendText(entry, 'button', `Queue private RSVP for ${item.name}`);
                button.type = 'button'; button.className = 'btn btn-outline-primary';
                button.dataset.action = 'offline-vault#queue'; button.dataset.gatheringId = item.gathering_id;
            }
            if (pending) {
                const cancel = this.appendText(entry, 'button', `Remove pending RSVP for ${item.name}`);
                cancel.type = 'button'; cancel.className = 'btn btn-outline-secondary';
                cancel.dataset.action = 'offline-vault#cancelPending';
                cancel.dataset.requestId = data.pending.find(row => row.gathering_id === item.gathering_id).id;
            }
            this.eventsTarget.append(entry);
        }
        this.message(`Offline access unlocked. ${data.pending.length} RSVPs pending sync.`);
    }

    appendText(parent, tag, value) {
        const element = document.createElement(tag); element.textContent = value; parent.append(element); return element;
    }
    eventEntry(item) {
        const entry = document.createElement('article'); entry.className = 'border-bottom py-3';
        this.appendText(entry, 'h3', item.name).className = 'h5';
        this.appendText(entry, 'p', `${item.start_date} ${item.start_time || ''} – ${item.end_date} · ${item.branch || ''} · ${item.location || ''}`);
        return entry;
    }
    renderCard(card) {
        if (!card) { this.appendText(this.cardTarget, 'p', 'Refresh online to save your card.'); return; }
        if (card.photo?.startsWith('data:image/jpeg;base64,')) {
            const photo = document.createElement('img'); photo.src = card.photo; photo.alt = 'Profile photo'; photo.width = 160; photo.className = 'img-thumbnail'; this.cardTarget.append(photo);
        }
        const list = document.createElement('dl'); this.cardTarget.append(list);
        const expiry = value => !value ? 'Not on file' : `${new Date(value.includes('T') ? value : value + 'T23:59:59') < new Date() ? 'Expired' : 'Valid through'} ${value}`;
        for (const [label, value] of [['Legal name', `${card.first_name} ${card.last_name}`], ['Society name', card.sca_name], ['Branch', card.branch],
            ['Membership', `${card.membership_number || 'Not on file'} · ${expiry(card.membership_expires_on)}`], ['Background check', expiry(card.background_check_expires_on)]]) {
            this.appendText(list, 'dt', label); this.appendText(list, 'dd', value);
        }
        for (const section of card.sections) {
            this.appendText(this.cardTarget, 'h3', section.title).className = 'h5';
            const items = document.createElement('ul'); this.cardTarget.append(items);
            section.items.forEach(item => this.appendText(items, 'li', item.label + (item.expires_on ? ` · ${expiry(item.expires_on)}` : '')));
        }
    }
}
window.Controllers ||= {};
window.Controllers['offline-vault'] = OfflineVaultController;
export default OfflineVaultController;
