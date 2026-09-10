import { savedMobileResponse } from '../../../assets/js/services/offline-response-service.js';
import vault from '../../../assets/js/services/offline-vault-service.js';
jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: {
    key: {}, generation: 0, metadata: jest.fn(), openTrusted: jest.fn(), read: jest.fn()
} }));
let data;
beforeEach(() => {
    global.Response = class {
        constructor(body, options) { this.body = body; this.headers = new Headers(options.headers); }
        async json() { return JSON.parse(this.body); }
    };
    vault.key = {}; vault.generation = 0;
    vault.metadata.mockResolvedValue({ wrapper: { method: 'trusted' }, verifiedAt: 100 });
    vault.openTrusted.mockResolvedValue(true);
    data = { card: { first_name: 'Ada', branch: 'Branch', sections: [{ title: 'Can Authorize (last verified)', items: [{ label: 'Armored: Combat' }] }] },
        months: { '2026-9': [{ gathering_id: 9, name: 'Practice', start_date: '2099-09-01', end_date: '2099-09-02' }] },
        rsvps: [], pending: [{ id: 'waiting', gathering_id: 9 }], resourceTimes: { card: 200 } };
    vault.read.mockImplementation(async () => data);
});
test('saved card JSON feeds the existing member and plugin renderers', async () => {
    const response = await savedMobileResponse('card');
    const json = await response.json();
    expect(json.member.branch).toEqual({ name: 'Branch' });
    expect(json.saved['Can Authorize']).toEqual({ Armored: ['Combat'] });
    expect(response.headers.get('X-KMP-Offline-Verified')).toBe('200');
});
test('the same pending request appears in both normal RSVP and calendar data contracts', async () => {
    data.pending[0].share_with_crown = true;
    const rsvps = await (await savedMobileResponse('rsvps')).json();
    const calendar = await (await savedMobileResponse('month:2026-9')).json();
    expect(rsvps.data.upcoming[0].pending_id).toBe('waiting');
    expect(rsvps.data.upcoming[0].sharing).toEqual({ kingdom: false, hosting_group: false, crown: true });
    expect(rsvps.data.upcoming[0].gathering.id).toBe(9);
    expect(calendar.data.events[0].pending_id).toBe('waiting');
    expect(calendar.data.events[0].id).toBe(9);
});
test('uncached months never become a misleading empty calendar', async () => {
    expect(await savedMobileResponse('month:2026-10')).toBeNull();
});
test('expired or removed copies cannot produce a saved API response', async () => {
    vault.read.mockRejectedValueOnce(new Error('Expired'));
    await expect(savedMobileResponse('card')).rejects.toThrow('Expired');
    vault.openTrusted.mockResolvedValueOnce(false);
    expect(await savedMobileResponse('card')).toBeNull();
});
test('a logout during decryption discards the response', async () => {
    vault.read.mockImplementationOnce(async () => { vault.generation++; return data; });
    expect(await savedMobileResponse('card')).toBeNull();
});
