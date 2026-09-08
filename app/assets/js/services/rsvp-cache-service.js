import vault from './offline-vault-service.js';
import { currentOfflineContext, privateJson, projectEvent } from './offline-data-service.js';

/** A fixed RSVP-only queue; every record stays inside the unlocked owner's encrypted vault. */
export class RsvpCacheService {
    constructor() {
        this.syncInProgress = false;
        this.isInitialized = false;
        this._handleOnline = () => { if (vault.key) this.syncPendingRsvps().catch(() => {}); };
    }
    async init() {
        if (this.isInitialized) return;
        this.isInitialized = true;
        window.addEventListener('online', this._handleOnline);
    }
    async cacheUserRsvps(events, scope = null) {
        if (!vault.key) return { saved: 0, errors: 0 };
        await vault.mutate(data => {
            const previous = scope ? (data.months[scope] || []).map(event => event.gathering_id) : [];
            if (scope) data.months[scope] = events.map(projectEvent);
            const ids = new Set([...previous, ...events.map(event => Number(event.id))]);
            data.rsvps = data.rsvps.filter(item => !ids.has(item.gathering_id));
            data.rsvps.push(...events.filter(event => event.user_attending).map(projectEvent));
        });
        return { saved: events.filter(event => event.user_attending).length, errors: 0 };
    }
    async getAllCachedRsvps() { return vault.key ? (await vault.read()).rsvps : []; }
    async getCachedRsvp(id) { return (await this.getAllCachedRsvps()).find(item => item.gathering_id === Number(id)) || null; }
    async updateCachedRsvp(id, changes) {
        if (!vault.key) return;
        await vault.mutate(data => {
            const existing = data.rsvps.find(item => item.gathering_id === Number(id));
            if (existing) Object.assign(existing, projectEvent({ ...existing, ...changes }));
        });
    }
    async removeCachedRsvp(id) {
        if (vault.key) await vault.mutate(data => { data.rsvps = data.rsvps.filter(item => item.gathering_id !== Number(id)); });
    }
    async queueOfflineRsvp(input) {
        const id = crypto.randomUUID();
        await vault.mutate(data => {
            const event = Object.values(data.months).flat().find(item => item.gathering_id === Number(input.gathering_id));
            if (!event || event.is_cancelled || new Date(event.end_date + 'T23:59:59') < new Date()) throw new Error('Refresh the event list online before queuing this RSVP.');
            if (data.pending.some(item => item.gathering_id === event.gathering_id)) return;
            if (data.pending.length >= 100) throw new Error('Sync pending RSVPs before adding more.');
            data.pending.push({ id, gathering_id: event.gathering_id, createdAt: Date.now() });
        });
        this.dispatchEvent('rsvp-queued');
        return id;
    }
    async getPendingRsvps() { return vault.key ? (await vault.read()).pending : []; }
    async getPendingCount() { return (await this.getPendingRsvps()).length; }
    async removePendingRsvp(id) { await vault.mutate(data => { data.pending = data.pending.filter(item => item.id !== id); }); }
    async syncPendingRsvps() {
        if (this.syncInProgress || !vault.key || !navigator.onLine) return { success: 0, failed: 0, skipped: true };
        this.syncInProgress = true;
        this.dispatchEvent('sync-started');
        let success = 0;
        let failed = 0;
        try {
            const context = await currentOfflineContext();
            for (const item of await this.getPendingRsvps()) {
                if (!vault.key) break;
                try {
                    const result = await privateJson('/gathering-attendances/mobile-rsvp', { method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': context.csrfToken },
                        body: JSON.stringify({ gathering_id: item.gathering_id, offline_request_id: item.id,
                            offline_owner: context.owner, offline_epoch: context.epoch }) });
                    if (result.success !== true) throw new Error('The RSVP was not accepted.');
                    await this.removePendingRsvp(item.id);
                    await vault.mutate(data => {
                        const event = Object.values(data.months).flat().find(event => event.gathering_id === item.gathering_id);
                        if (event && !data.rsvps.some(rsvp => rsvp.gathering_id === item.gathering_id)) data.rsvps.push({ ...event, user_attending: true });
                    });
                    success++;
                } catch { failed++; break; }
            }
            return { success, failed };
        } finally {
            this.syncInProgress = false;
            this.dispatchEvent('sync-complete', { success, failed });
        }
    }
    dispatchEvent(name, detail = {}) { window.dispatchEvent(new CustomEvent(`rsvp-cache:${name}`, { detail })); }
    async clearAll() { if (vault.key) await vault.mutate(data => { data.rsvps = []; data.pending = []; data.months = {}; }); }
    destroy() { window.removeEventListener('online', this._handleOnline); this.isInitialized = false; }
}
const service = new RsvpCacheService();
export default service;
window.RsvpCacheService = service;
