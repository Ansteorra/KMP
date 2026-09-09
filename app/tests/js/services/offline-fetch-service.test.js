import { installOfflineFetchCapture } from '../../../assets/js/services/offline-fetch-service.js';
import { savedMobileResponse } from '../../../assets/js/services/offline-response-service.js';
jest.mock('../../../assets/js/services/offline-response-service.js', () => ({ savedMobileResponse: jest.fn() }));
import vault from '../../../assets/js/services/offline-vault-service.js';
jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: {
    metadata: jest.fn(), openTrusted: jest.fn(), mutate: jest.fn(), clear: jest.fn()
} }));

const record = { id: 'trusted-id', owner: 'owner-a', epoch: 'epoch-a', wrapper: { method: 'trusted' } };
const card = name => ({ member: { first_name: name, additional_info: 'EXCLUDED', branch: { name: 'Branch' } } });
const response = (data, options = {}) => ({
    ok: true, redirected: false,
    headers: new Headers({ 'Content-Type': 'application/json', 'X-KMP-Offline-Owner': 'owner-a', 'X-KMP-Offline-Epoch': 'epoch-a', ...options.headers }),
    clone: () => ({ text: async () => JSON.stringify(data) }),
    json: async () => data,
    ...options,
});
let payload, target, failure;
beforeEach(() => {
    jest.resetAllMocks();
    savedMobileResponse.mockResolvedValue(null);
    document.head.innerHTML = '';
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    vault.generation = 1; vault.trusted = true; vault.activeId = record.id;
    vault.metadata.mockResolvedValue(record);
    vault.openTrusted.mockResolvedValue(true);
    payload = { card: { first_name: 'Old' }, months: {}, rsvps: [], pending: [{ id: 'waiting' }] };
    vault.mutate.mockImplementation(async change => change(payload));
    failure = jest.fn();
    target = { fetch: jest.fn().mockResolvedValue(response(card('Current'))) };
});
const install = () => installOfflineFetchCapture(target, failure);

test('an ordinary successful card GET saves the approved fields and leaves its response readable', async () => {
    install();
    const result = await target.fetch('/members/view-mobile-card-json');
    expect((await result.json()).member.first_name).toBe('Current');
    expect(payload.card.first_name).toBe('Current');
    expect(JSON.stringify(payload)).not.toContain('EXCLUDED');
    expect(payload.pending).toEqual([{ id: 'waiting' }]);
    expect(payload.resourceTimes.card).toEqual(expect.any(Number));
});

test('ordinary RSVP and calendar JSON responses refresh their own saved resources', async () => {
    target.fetch.mockResolvedValueOnce(response({ success: true, data: { upcoming: [{ attendance_id: 4,
        gathering: { id: 9, name: 'Practice' }, sharing: { kingdom: false }, note: 'My note' }], past: [] } }))
        .mockResolvedValueOnce(response({ success: true, data: { events: [{ id: 9, name: 'New practice', user_attending: true }] } }));
    install();
    await target.fetch('/gathering-attendances/my-rsvps');
    await target.fetch('/gatherings/mobile-calendar-data?year=2026&month=9');
    expect(payload.rsvps[0]).toMatchObject({ gathering_id: 9, public_note: 'My note' });
    expect(payload.months['2026-9'][0].name).toBe('New practice');
    expect(payload.card.first_name).toBe('Old');
});

test.each([
    ['/members/view/1', {}],
    ['https://another-tenant.test/members/view-mobile-card-json', {}],
    ['/members/view-mobile-card-json/2', {}],
    ['/members/view-mobile-card-json?member=2', {}],
    ['/gatherings/mobile-calendar-data?year=2026&month=13', {}],
    ['/gatherings/mobile-calendar-data?year=2026&month=9&month=10', {}],
    ['/gatherings/mobile-calendar-data?year=2026&month=9&branch=2', {}],
    ['/members/view-mobile-card-json', { method: 'POST' }],
    ['/members/view-mobile-card-json', { kmpOfflineCapture: false }],
])('does not capture unrelated, scoped or internal requests: %s %j', async (url, options) => {
    const original = target.fetch;
    install(); await target.fetch(url, options);
    expect(vault.metadata).not.toHaveBeenCalled();
    expect(vault.mutate).not.toHaveBeenCalled();
    expect(original.mock.calls[0][1]).not.toHaveProperty('kmpOfflineCapture');
});

test.each([null, { ...record, wrapper: { method: 'passphrase' } }])('never enrolls or captures without deliberate trust', async metadata => {
    vault.metadata.mockResolvedValue(metadata);
    install(); await target.fetch('/members/view-mobile-card-json');
    expect(vault.mutate).not.toHaveBeenCalled();
    expect(vault.openTrusted).not.toHaveBeenCalled();
});

test.each([
    { ok: false }, { redirected: true }, { headers: new Headers() },
])('failed, redirected and unbound responses leave saved information untouched: %j', async options => {
    target.fetch.mockResolvedValue(response(card('Rejected'), options));
    install(); await target.fetch('/members/view-mobile-card-json');
    expect(vault.mutate).not.toHaveBeenCalled();
    expect(vault.clear).not.toHaveBeenCalled();
});

test('a differently bound response invalidates the original trust instead of copying data', async () => {
    target.fetch.mockResolvedValue(response(card('Foreign'), { headers: new Headers({ 'Content-Type': 'application/json',
        'X-KMP-Offline-Owner': 'other-owner', 'X-KMP-Offline-Epoch': 'epoch-a' }) }));
    install(); await target.fetch('/members/view-mobile-card-json');
    expect(vault.clear).toHaveBeenCalledTimes(1);
    expect(vault.mutate).not.toHaveBeenCalled();
});

test('a response arriving after logout cannot repopulate storage', async () => {
    let finish;
    target.fetch.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    install(); const request = target.fetch('/members/view-mobile-card-json');
    await Promise.resolve(); await Promise.resolve();
    vault.generation++; vault.trusted = false;
    finish(response(card('Late'))); await request;
    expect(vault.mutate).not.toHaveBeenCalled();
});

test('a slow older response cannot overwrite a more recent request', async () => {
    const now = jest.spyOn(Date, 'now').mockReturnValue(1000);
    let old;
    target.fetch.mockImplementationOnce(() => new Promise(resolve => { old = resolve; }));
    install();
    const first = target.fetch('/members/view-mobile-card-json');
    now.mockReturnValue(2000);
    await target.fetch('/members/view-mobile-card-json');
    old(response(card('Stale'))); await first;
    expect(payload.card.first_name).toBe('Current');
    now.mockRestore();
});

test('storage failures are reported without breaking a successful online response', async () => {
    vault.mutate.mockRejectedValue(new Error('Quota exceeded'));
    install(); const result = await target.fetch('/members/view-mobile-card-json');
    expect((await result.json()).member.first_name).toBe('Current');
    expect(failure).toHaveBeenCalledTimes(1);
});


test('installing twice captures each response only once', async () => {
    install(); install();
    await target.fetch('/members/view-mobile-card-json');
    expect(vault.mutate).toHaveBeenCalledTimes(1);
});

test('a same-account trust replacement cannot receive a response from the old trust', async () => {
    let finish;
    target.fetch.mockImplementation(() => new Promise(resolve => { finish = resolve; }));
    install(); const request = target.fetch('/members/view-mobile-card-json');
    await Promise.resolve(); await Promise.resolve(); await Promise.resolve();
    vault.metadata.mockResolvedValue({ ...record, id: 'replacement-trust' });
    finish(response(card('Late'))); await request;
    expect(vault.mutate).not.toHaveBeenCalled();
});

test('captures do not change expiry and a failed first save retries without losing queued work', async () => {
    record.expiresAt = Date.now() + 1000;
    const expiry = record.expiresAt;
    vault.mutate.mockRejectedValueOnce(new Error('Changed concurrently'));
    install(); await target.fetch('/members/view-mobile-card-json');
    expect(record.expiresAt).toBe(expiry);
    expect(payload.pending).toEqual([{ id: 'waiting' }]);
    expect(payload.card.first_name).toBe('Current');
    expect(failure).not.toHaveBeenCalled();
});


test.each(['offline', 'public', 'failure', 'server error'])('%s supplies saved JSON through the ordinary fetch contract', async mode => {
    const saved = response(card('Saved'));
    savedMobileResponse.mockResolvedValue(saved);
    const original = target.fetch;
    if (mode === 'offline') Object.defineProperty(navigator, 'onLine', { configurable: true, value: false });
    if (mode === 'public') document.head.innerHTML = '<meta name="kmp-offline-shell" content="1">';
    if (mode === 'failure') original.mockRejectedValue(new Error('Network unavailable'));
    if (mode === 'server error') original.mockResolvedValue(response({}, { ok: false }));
    install();
    expect((await (await target.fetch('/members/view-mobile-card-json')).json()).member.first_name).toBe('Saved');
    expect(savedMobileResponse).toHaveBeenCalledWith('card');
    expect(vault.mutate).not.toHaveBeenCalled();
    if (['offline', 'public'].includes(mode)) expect(original).not.toHaveBeenCalled();
});

test('writes never become successful saved responses', async () => {
    savedMobileResponse.mockResolvedValue(response(card('Saved')));
    target.fetch.mockRejectedValue(new Error('Disconnected'));
    install();
    await expect(target.fetch('/members/view-mobile-card-json', { method: 'POST' })).rejects.toThrow('Disconnected');
    expect(savedMobileResponse).not.toHaveBeenCalled();
});

test('explicit invalidation clears saved data instead of using it as fallback', async () => {
    savedMobileResponse.mockResolvedValue(response(card('Saved')));
    target.fetch.mockResolvedValue(response({}, { ok: false, headers: new Headers({ 'X-KMP-Offline-Clear': '1' }) }));
    install();
    expect((await target.fetch('/members/view-mobile-card-json')).ok).toBe(false);
    expect(vault.clear).toHaveBeenCalledTimes(1);
    expect(savedMobileResponse).not.toHaveBeenCalled();
});
