import vault from './offline-vault-service.js';

/** Requests used by the offline UI are fixed, same-origin, authenticated and never cached. */
export async function privateJson(path, options = {}) {
    const url = new URL(path, location.origin);
    if (url.origin !== location.origin) throw new Error('Invalid offline request.');
    const { expectedContext, ...requestOptions } = options;
    const response = await fetch(url.href, { ...requestOptions, credentials: 'same-origin', cache: 'no-store', redirect: 'manual',
        signal: requestOptions.signal || AbortSignal.timeout?.(30000),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...options.headers } });
    if (response.type === 'opaqueredirect' || response.redirected || response.status === 401 || response.status === 403 || response.headers.get('X-KMP-Offline-Clear') === '1') {
        await vault.clear();
        throw new Error('Sign in online to continue.');
    }
    if (expectedContext && (response.headers.get('X-KMP-Offline-Owner') !== expectedContext.owner || response.headers.get('X-KMP-Offline-Epoch') !== expectedContext.epoch)) {
        await vault.clear();
        throw new Error('Account changed during refresh. Sign in again.');
    }
    if (!response.ok || !response.headers.get('Content-Type')?.includes('application/json')) throw new Error('Unable to refresh. Sign in online and retry.');
    return response.json();
}

export async function currentOfflineContext() {
    const response = await privateJson('/offline/context');
    if (response.success !== true || !response.data?.owner || !response.data?.epoch) throw new Error('Sign in online to continue.');
    return vault.verifyContext(response.data);
}

const text = (value, max = 250) => typeof value === 'string' ? value.slice(0, max) : '';
/** Explicit projection: never persist raw entities or arbitrary plugin JSON. */
export function projectCard(response) {
    const member = response.member || {};
    const card = {
        first_name: text(member.first_name), last_name: text(member.last_name), sca_name: text(member.sca_name),
        branch: text(member.branch?.name), membership_number: text(member.membership_number),
        membership_expires_on: text(member.membership_expires_on), background_check_expires_on: text(member.background_check_expires_on),
        sections: []
    };
    // Plugins opt in with a portable text-only offline_sections DTO. Unknown plugin fields are excluded.
    for (const plugin of Object.values(response)) {
        if (!Array.isArray(plugin?.offline_sections)) continue;
        for (const section of plugin.offline_sections.slice(0, 30)) {
            if (!Array.isArray(section.items)) continue;
            card.sections.push({ title: text(section.title), items: section.items.slice(0, 200).map(item => ({
                label: text(item.label), expires_on: text(item.expires_on)
            })) });
        }
    }
    return card;
}

/** Only event display data and the current user's RSVP fields enter the vault. */
export function projectEvent(event) {
    return { gathering_id: Number(event.gathering_id ?? event.id), public_id: text(event.public_id), name: text(event.name),
        start_date: text(event.start_date), start_time: text(event.start_time), end_date: text(event.end_date),
        location: text(event.location, 1000), branch: text(event.branch), is_cancelled: event.is_cancelled === true,
        attendance_id: Number(event.attendance_id) || null, user_attending: !!event.user_attending,
        share_with_kingdom: !!event.share_with_kingdom, share_with_hosting_group: !!event.share_with_hosting_group,
        share_with_crown: !!event.share_with_crown, public_note: text(event.public_note, 4000) };
}

async function thumbnail(url, context) {
    if (!url) return null;
    const photoUrl = new URL(url, location.origin);
    if (photoUrl.origin !== location.origin || !photoUrl.pathname.endsWith('/members/mobile-card-photo')) return null;
    const response = await fetch(photoUrl.href, { cache: 'no-store', credentials: 'same-origin', redirect: 'error' });
    if (!response.ok) return null;
    if (response.headers.get('X-KMP-Offline-Owner') !== context.owner || response.headers.get('X-KMP-Offline-Epoch') !== context.epoch) {
        await vault.clear();
        throw new Error('Account changed during photo refresh.');
    }
    const blob = await response.blob();
    if (blob.size > 256000 || !['image/jpeg', 'image/png', 'image/webp'].includes(blob.type)) return null;
    const bitmap = await createImageBitmap(blob);
    try {
        if (bitmap.width * bitmap.height > 4000000) return null;
        const canvas = document.createElement('canvas');
        const scale = Math.min(1, 240 / Math.max(bitmap.width, bitmap.height));
        canvas.width = Math.round(bitmap.width * scale); canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.8);
    } finally { bitmap.close(); }
}

export async function refreshOfflineSnapshot() {
    const context = await currentOfflineContext();
    if (!vault.key) throw new Error('Unlock offline access first.');
    const cardResponse = await privateJson('/members/view-mobile-card-json', { expectedContext: context });
    const own = await privateJson('/gathering-attendances/my-rsvps', { expectedContext: context });
    if (!own.success) throw new Error('Unable to refresh RSVPs.');
    const card = projectCard(cardResponse);
    card.photo = await thumbnail(cardResponse.member?.profile_photo_url, context).catch(() => null);
    const months = {};
    for (let offset = 0; offset < 2; offset++) {
        const date = new Date(context.serverTime);
        date.setDate(1); date.setMonth(date.getMonth() + offset);
        const scope = `${date.getFullYear()}-${date.getMonth() + 1}`;
        const result = await privateJson(`/gatherings/mobile-calendar-data?year=${date.getFullYear()}&month=${date.getMonth() + 1}`, { expectedContext: context });
        if (!result.success || !Array.isArray(result.data?.events)) throw new Error('Unable to refresh the event list.');
        months[scope] = result.data.events.map(projectEvent);
    }
    const rsvps = [...(own.data.upcoming || []), ...(own.data.past || [])].map(item => projectEvent({ ...item.gathering,
        attendance_id: item.attendance_id, user_attending: true, public_note: item.note,
        share_with_kingdom: item.sharing.kingdom, share_with_hosting_group: item.sharing.hosting_group, share_with_crown: item.sharing.crown }));
    // Check again after collecting responses: another tab may have changed the login during the fetches.
    await currentOfflineContext();
    await vault.refresh(context, { card, months, rsvps });
}
