import { savedMobileResponse } from './offline-response-service.js';
import vault from './offline-vault-service.js';
import { projectCard, projectEvent, projectRsvps, offlineRequestTime } from './offline-data-service.js';

const installed = Symbol('kmp.offline.fetch');
const MAX_RESPONSE_BYTES = 2 * 1024 * 1024;

/** Only the current member's approved display endpoints participate. */
function resourceFor(input, options) {
    const method = options.method || input?.method || 'GET';
    if (method.toUpperCase() !== 'GET') return null;
    const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, location.href);
    if (url.origin !== location.origin) return null;
    if (url.pathname === '/members/view-mobile-card-json' && !url.search) return 'card';
    if (url.pathname === '/gathering-attendances/my-rsvps' && !url.search) return 'rsvps';
    if (url.pathname !== '/gatherings/mobile-calendar-data') return null;
    if ([...url.searchParams.keys()].some(key => !['year', 'month'].includes(key))) return null;
    if (url.searchParams.getAll('year').length !== 1 || url.searchParams.getAll('month').length !== 1) return null;
    const year = Number(url.searchParams.get('year'));
    const month = Number(url.searchParams.get('month'));
    if (!Number.isInteger(year) || year < 2000 || year > 2200 || !Number.isInteger(month) || month < 1 || month > 12) return null;
    return `month:${year}-${month}`;
}

async function ticketFor(resource, startedAt) {
    const generation = vault.generation;
    const record = await vault.metadata();
    if (record?.wrapper.method !== 'trusted' || !await vault.openTrusted() || generation !== vault.generation) return null;
    return { resource, generation, id: record.id, owner: record.owner, epoch: record.epoch, startedAt };
}

async function stillCurrent(ticket) {
    if (ticket.generation !== vault.generation) return false;
    const record = await vault.metadata();
    return ticket.generation === vault.generation && record?.id === ticket.id && vault.activeId === ticket.id && vault.trusted;
}

async function copyResponse(response, ticket) {
    if (!ticket || !await stillCurrent(ticket)) return;
    if (response.headers.get('X-KMP-Offline-Clear') === '1') { await vault.clear(); return; }
    if (!response.ok || response.redirected || !response.headers.get('Content-Type')?.includes('application/json')) return;
    const owner = response.headers.get('X-KMP-Offline-Owner');
    const epoch = response.headers.get('X-KMP-Offline-Epoch');
    if (!owner || !epoch) return; // An unbound response is never an offline data source.
    if (owner !== ticket.owner || epoch !== ticket.epoch) { await vault.clear(); return; }
    if (Number(response.headers.get('Content-Length')) > MAX_RESPONSE_BYTES) throw new Error('Offline response too large.');
    const body = await response.clone().text();
    if (body.length > MAX_RESPONSE_BYTES) throw new Error('Offline response too large.');
    const json = JSON.parse(body);
    let value;
    if (ticket.resource === 'card') {
        if (!json.member || typeof json.member.first_name !== 'string') return;
        value = projectCard(json);
        // Photos are prepared by the background snapshot; JSON capture adds no network requests.
        value.photo = json.member.profile_photo_url ? undefined : null;
    } else if (ticket.resource === 'rsvps') {
        if (!json.success || !Array.isArray(json.data?.upcoming) || !Array.isArray(json.data?.past)) return;
        value = projectRsvps(json);
    } else {
        if (!json.success || !Array.isArray(json.data?.events)) return;
        value = json.data.events.map(projectEvent);
    }
    // CAS conflicts with another tab retry against the new encrypted record.
    for (let attempt = 0; attempt < 3; attempt++) {
        if (!await stillCurrent(ticket)) return;
        try {
            await vault.mutate(data => {
                if (ticket.generation !== vault.generation || vault.activeId !== ticket.id) throw new Error('Offline account changed.');
                data.resourceTimes ||= {};
                if ((data.resourceTimes[ticket.resource] || 0) > ticket.startedAt) return;
                if (ticket.resource === 'card') {
                    if (value.photo === undefined) value.photo = data.card?.photo || null;
                    data.card = value;
                } else if (ticket.resource === 'rsvps') data.rsvps = value;
                else {
                    data.months[ticket.resource.slice(6)] = value;
                    if ((data.resourceTimes.rsvps || 0) <= ticket.startedAt) {
                        const existing = new Map(data.rsvps.map(row => [row.gathering_id, row]));
                        const ids = new Set(value.map(row => row.gathering_id));
                        data.rsvps = data.rsvps.filter(row => !ids.has(row.gathering_id));
                        data.rsvps.push(...value.filter(row => row.user_attending).map(row => ({
                            ...row, ...existing.get(row.gathering_id), user_attending: true
                        })));
                        data.resourceTimes.rsvps = ticket.startedAt;
                    }
                    // Bound visited months while retaining the proactive current/next-month copies.
                    const now = new Date();
                    const keep = new Set([`${now.getFullYear()}-${now.getMonth() + 1}`]);
                    now.setDate(1); now.setMonth(now.getMonth() + 1);
                    keep.add(`${now.getFullYear()}-${now.getMonth() + 1}`);
                    const extra = Object.keys(data.months).filter(key => !keep.has(key))
                        .sort((a, b) => (data.resourceTimes[`month:${b}`] || 0) - (data.resourceTimes[`month:${a}`] || 0));
                    // The arriving month is the newest even before its timestamp is assigned.
                    keep.add(ticket.resource.slice(6));
                    extra.filter(key => !keep.has(key)).slice(3).forEach(key => {
                        delete data.months[key]; delete data.resourceTimes[`month:${key}`];
                    });
                }
                data.resourceTimes[ticket.resource] = ticket.startedAt;
            });
            return;
        } catch (error) { if (attempt === 2) throw error; }
    }
}

/** Supply approved saved responses when unavailable and capture live JSON on trusted devices. */
export function installOfflineFetchCapture(target = window, onSaveError = () => {}) {
    if (target.fetch[installed]) return;
    const original = target.fetch.bind(target);
    const wrapped = async (input, options = {}) => {
        const { kmpOfflineCapture = true, ...networkOptions } = options;
        let resource;
        try { resource = kmpOfflineCapture && resourceFor(input, networkOptions); } catch { /* Let fetch validate its own arguments. */ }
        if (!resource) return original(input, networkOptions);
        const fromSaved = async () => {
            const saved = await savedMobileResponse(resource).catch(() => null);
            if (saved) window.dispatchEvent(new CustomEvent('kmp:offline-source', { detail: { offline: true } }));
            return saved;
        };
        if (!navigator.onLine || document.querySelector('meta[name="kmp-offline-shell"]')) {
            const saved = await fromSaved();
            if (saved) return saved;
            if (!navigator.onLine) throw new Error('Connect and sign in to save this information.');
        }
        const ticket = ticketFor(resource, offlineRequestTime()).catch(() => null);
        const controller = new AbortController();
        let timer;
        try {
            const response = await Promise.race([
                original(input, { ...networkOptions, signal: networkOptions.signal || controller.signal }),
                new Promise((resolve, reject) => { timer = setTimeout(() => {
                    controller.abort(); reject(new Error('Connection timed out'));
                }, 4000); })
            ]);
            if (response.headers.get('X-KMP-Offline-Clear') === '1') {
                await vault.clear();
                return response;
            }
            if (!response.ok || response.redirected || response.type === 'opaqueredirect') {
                const saved = await fromSaved();
                if (saved) return saved;
                return response;
            }
            try { await copyResponse(response, await ticket); } catch { onSaveError(); }
            window.dispatchEvent(new CustomEvent('kmp:offline-source', { detail: { offline: false } }));
            return response;
        } catch (error) {
            if (networkOptions.signal?.aborted) throw error;
            const saved = await fromSaved();
            if (saved) return saved;
            throw error;
        } finally { clearTimeout(timer); }
    };
    wrapped[installed] = true;
    target.fetch = wrapped;
}
