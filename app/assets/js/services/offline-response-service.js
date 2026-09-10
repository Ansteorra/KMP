import vault from './offline-vault-service.js';

/** Reconstruct the existing mobile API contracts from approved, encrypted display data. */
export async function savedMobileResponse(resource) {
    const generation = vault.generation;
    const initial = await vault.metadata();
    if (initial?.wrapper.method === 'trusted') { if (!await vault.openTrusted()) return null; }
    else if (!vault.key) return null;
    const key = vault.key;
    const data = await vault.read();
    const record = await vault.metadata();
    if (!record || !key || key !== vault.key || generation !== vault.generation) return null;
    let json;
    if (resource === 'card') {
        if (!data.card) return null;
        const card = data.card;
        const sections = Object.create(null);
        for (const section of card.sections || []) {
            const groups = Object.create(null);
            for (const item of section.items) {
                const parts = item.label.split(':');
                const group = parts.length > 1 ? parts.shift().trim() : '';
                const expires = item.expires_on ? new Date(item.expires_on + (item.expires_on.includes('T') ? '' : 'T23:59:59')) : null;
                const label = parts.join(':').trim() + (expires ? ` — ${expires < new Date() ? 'Expired' : 'Valid through'} ${expires.toLocaleDateString()}` : '');
                (groups[group] ||= []).push(label);
            }
            if (section.items.length) sections[section.title.replace(' (last verified)', '')] = groups;
        }
        json = { member: { ...card, branch: { name: card.branch }, profile_photo_url: card.photo || null }, saved: sections };
    } else if (resource === 'rsvps') {
        const upcoming = [], past = [];
        const rows = [...data.rsvps];
        for (const pending of data.pending) {
            const event = Object.values(data.months).flat().find(row => row.gathering_id === pending.gathering_id);
            if (event && !rows.some(row => row.gathering_id === pending.gathering_id)) rows.push({ ...event, share_with_kingdom: pending.share_with_kingdom === true,
                share_with_hosting_group: pending.share_with_hosting_group === true,
                share_with_crown: pending.share_with_crown === true, pending_id: pending.id });
        }
        rows.forEach(row => {
            const entry = { attendance_id: row.attendance_id, pending_id: row.pending_id,
                gathering: { ...row, id: row.gathering_id }, note: row.public_note,
                sharing: { kingdom: row.share_with_kingdom, hosting_group: row.share_with_hosting_group, crown: row.share_with_crown } };
            (new Date(row.end_date + 'T23:59:59') < new Date() ? past : upcoming).push(entry);
        });
        json = { success: true, data: { upcoming, past } };
    } else {
        const events = data.months[resource.slice(6)];
        if (!events) return null;
        json = { success: true, data: { events: events.map(row => ({ ...row, id: row.gathering_id,
            user_attending: data.rsvps.some(rsvp => rsvp.gathering_id === row.gathering_id),
            pending_id: data.pending.find(pending => pending.gathering_id === row.gathering_id)?.id || null })) } };
    }
    return new Response(JSON.stringify(json), { headers: { 'Content-Type': 'application/json',
        'X-KMP-Offline-Saved': '1', 'X-KMP-Offline-Verified': String(data.resourceTimes?.[resource] || record.verifiedAt) } });
}
