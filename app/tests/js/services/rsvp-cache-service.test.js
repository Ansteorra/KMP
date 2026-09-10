import vault from '../../../assets/js/services/offline-vault-service.js';
import { currentOfflineContext, privateJson } from '../../../assets/js/services/offline-data-service.js';
import { RsvpCacheService } from '../../../assets/js/services/rsvp-cache-service.js';
jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: { key: {}, read: jest.fn(), mutate: jest.fn() } }));
jest.mock('../../../assets/js/services/offline-data-service.js', () => ({ currentOfflineContext: jest.fn(), privateJson: jest.fn(), projectEvent: item => ({ ...item, gathering_id: item.id }) }));
let data;
let service;
beforeEach(() => {
    jest.clearAllMocks(); vault.key = {};
    data = { card: { can_share_rsvp_with_kingdom: true }, months: { '2026-9': [{ gathering_id: 123, name: 'Event', end_date: '2099-01-01' }] }, rsvps: [], pending: [] };
    vault.read.mockImplementation(async () => data);
    vault.mutate.mockImplementation(async change => change(data));
    currentOfflineContext.mockResolvedValue({ owner: 'A', epoch: 'A-epoch', csrfToken: 'CURRENT-CSRF' });
    privateJson.mockResolvedValue({ success: true });
    service = new RsvpCacheService();
    Object.defineProperty(global.crypto, 'randomUUID', { configurable: true, value: () => 'synthetic-request-id' });
});
test('queue retains only explicit visibility choices and excludes notes', async () => {
    await service.queueOfflineRsvp({ gathering_id: 123, public_note: 'PRIVATE-MARKER', share_with_crown: true });
    expect(data.pending).toHaveLength(1);
    expect(data.pending[0]).toMatchObject({ share_with_kingdom: false, share_with_hosting_group: false, share_with_crown: true });
    expect(JSON.stringify(data.pending)).not.toContain('PRIVATE-MARKER');
});
test('every replay carries the verified actor, current CSRF, and stable request id', async () => {
    await service.queueOfflineRsvp({ gathering_id: 123 });
    await service.syncPendingRsvps();
    const [url, request] = privateJson.mock.calls[0];
    expect(url).toBe('/gathering-attendances/mobile-rsvp');
    expect(request.headers['X-CSRF-Token']).toBe('CURRENT-CSRF');
    expect(JSON.parse(request.body)).toEqual({ gathering_id: 123, offline_request_id: 'synthetic-request-id', offline_owner: 'A', offline_epoch: 'A-epoch', share_with_kingdom: false, share_with_hosting_group: false, share_with_crown: false });
    expect(data.pending).toHaveLength(0);
});
test('account mismatch prevents replay; failed or non-success responses retain pending actions', async () => {
    await service.queueOfflineRsvp({ gathering_id: 123 });
    currentOfflineContext.mockRejectedValueOnce(new Error('Account changed'));
    await expect(service.syncPendingRsvps()).rejects.toThrow('Account changed');
    expect(privateJson).not.toHaveBeenCalled(); expect(data.pending).toHaveLength(1);
    privateJson.mockResolvedValueOnce({ success: false });
    await service.syncPendingRsvps(); expect(data.pending).toHaveLength(1);
    privateJson.mockRejectedValueOnce(new Error('Login redirect'));
    await service.syncPendingRsvps(); expect(data.pending).toHaveLength(1);
});
test('empty month replaces only its snapshot and a locked vault never syncs', async () => {
    data.months['2026-10'] = [{ gathering_id: 456 }];
    data.rsvps = [{ gathering_id: 123 }, { gathering_id: 456 }];
    await service.cacheUserRsvps([], '2026-9');
    expect(data.months['2026-9']).toEqual([]); expect(data.months['2026-10']).toHaveLength(1);
    expect(data.rsvps).toEqual([{ gathering_id: 456 }]);
    vault.key = null; await service.syncPendingRsvps(); expect(privateJson).not.toHaveBeenCalled();
});


test('visibility survives a failed send and is replayed unchanged', async () => {
    await service.queueOfflineRsvp({ gathering_id: 123, share_with_kingdom: true, share_with_hosting_group: true });
    privateJson.mockRejectedValueOnce(new Error('Disconnected'));
    await service.syncPendingRsvps();
    expect(data.pending).toHaveLength(1);
    await service.syncPendingRsvps();
    const first = JSON.parse(privateJson.mock.calls[0][1].body);
    const retry = JSON.parse(privateJson.mock.calls[1][1].body);
    expect(first).toEqual(retry);
    expect(retry).toMatchObject({ share_with_kingdom: true, share_with_hosting_group: true, share_with_crown: false });
});

test.each([false, undefined])('missing or restricted Kingdom eligibility stays private: %s', async allowed => {
    data.card.can_share_rsvp_with_kingdom = allowed;
    await service.queueOfflineRsvp({ gathering_id: 123, share_with_kingdom: true, share_with_hosting_group: true, share_with_crown: 'true' });
    expect(data.pending[0]).toMatchObject({ share_with_kingdom: false, share_with_hosting_group: true, share_with_crown: false });
});

test('legacy entries without visibility replay privately', async () => {
    data.pending.push({ id: 'legacy', gathering_id: 123 });
    await service.syncPendingRsvps();
    expect(JSON.parse(privateJson.mock.calls[0][1].body)).toMatchObject({ share_with_kingdom: false, share_with_hosting_group: false, share_with_crown: false });
});


test('an account transition while opening the queue cannot save the previous users choices', async () => {
    vault.read.mockImplementationOnce(async () => { vault.key = {}; return data; });
    await expect(service.queueOfflineRsvp({ gathering_id: 123, share_with_kingdom: true })).rejects.toThrow('sign-in changed');
    expect(data.pending).toHaveLength(0);
});
