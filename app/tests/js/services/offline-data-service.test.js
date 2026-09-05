import { privateJson, projectCard } from '../../../assets/js/services/offline-data-service.js';
import vault from '../../../assets/js/services/offline-vault-service.js';
jest.mock('../../../assets/js/services/offline-vault-service.js', () => ({ __esModule: true, default: { clear: jest.fn().mockResolvedValue() } }));
test('card projection excludes raw entity and unreviewed plugin fields', () => {
    const result = projectCard({ member: { first_name: 'Card', additional_info: 'PRIVATE-NOTES', auth_version: 'SECRET', branch: { name: 'Branch', address: 'PII' } },
        plugin: { arbitrary: 'PRIVATE-PLUGIN', offline_sections: [{ title: 'Authorizations', items: [{ label: 'Activity', expires_on: '2099-01-01', secret: 'EXCLUDED' }] }] } });
    expect(result.first_name).toBe('Card'); expect(result.branch).toBe('Branch');
    expect(JSON.stringify(result)).not.toMatch(/PRIVATE|SECRET|EXCLUDED|PII/);
    expect(result.sections[0].items[0]).toEqual({ label: 'Activity', expires_on: '2099-01-01' });
});
test('an individual wrong-owner snapshot response clears the vault even if surrounding context checks match', async () => {
    global.fetch = jest.fn().mockResolvedValue({ ok: true, status: 200, headers: { get: key => ({ 'X-KMP-Offline-Owner': 'B', 'X-KMP-Offline-Epoch': 'B-epoch', 'Content-Type': 'application/json' })[key] } });
    await expect(privateJson('/members/view-mobile-card-json', { expectedContext: { owner: 'A', epoch: 'A-epoch' } })).rejects.toThrow('Account changed');
    expect(vault.clear).toHaveBeenCalled();
});
test('a login redirect cannot be accepted as a successful sync or snapshot', async () => {
    global.fetch = jest.fn().mockResolvedValue({ type: 'opaqueredirect', headers: { get: () => null } });
    await expect(privateJson('/offline/context')).rejects.toThrow('Sign in');
});
