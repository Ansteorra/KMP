import { createIcon, iconHtml, loadCatalog, loadIcon, lookupBucket, resolveIcon, searchCatalog, setIcon } from '../../../assets/js/services/icon-service.js';
import catalogData from '../../../resources/icons/catalog.json';
import runtime from '../../../resources/icons/runtime.json';

const aliasesById = {};
for (const [alias, id] of Object.entries(catalogData.aliases)) (aliasesById[id] ||= []).push(alias);
const catalog = Object.values(catalogData.entries).map(entry => ({...entry, aliases: aliasesById[entry.id] || []}));

test('exact aliases and compound legacy values resolve without treating keywords as identifiers', () => {
    expect(resolveIcon('bi-search').id).toBe('search');
    expect(resolveIcon('bi bi-search text-muted').id).toBe('search');
    expect(resolveIcon('bi-search bi-trash')).toBeNull();
    expect(resolveIcon('find')).toBeNull();
    expect(resolveIcon('heavy combat')).toBeNull();
});

test('DOM updates change the name and preserve utility classes and accessible names', () => {
    const icon = createIcon('search', {class:'text-danger me-2', label:'Search', size:20});
    expect(icon).toHaveAttribute('role', 'img');
    expect(icon).toHaveAttribute('aria-label', 'Search');
    expect(icon).not.toHaveAttribute('aria-hidden');
    setIcon(icon, 'bi-trash');
    expect(icon).toHaveAttribute('name', 'trash');
    expect(icon).toHaveClass('text-danger', 'me-2');
    expect(createIcon('check')).toHaveAttribute('aria-hidden', 'true');
    expect(iconHtml('search', {label:'Search "everything"'})).toContain('aria-label="Search &quot;everything&quot;"');
    expect(iconHtml('<svg onload="alert(1)">')).not.toContain('<svg');
});

test('uncommon saved identifiers fetch one local lookup shard, never an arbitrary URL or full catalog', async () => {
    const entry = Object.values(catalogData.entries).find(entry => !runtime.entries[entry.id]);
    global.fetch = jest.fn().mockResolvedValue({ok:true, json: async () => ({[entry.id]:{id:entry.id, asset:entry.asset}})});
    const resolved = await loadIcon(entry.id);
    expect(resolved.id).toBe(entry.id);
    expect(fetch).toHaveBeenCalledWith(`/kmp-icons/${runtime.revision}/lookup/${lookupBucket(entry.id)}.json`, {credentials:'omit'});
    expect(await loadIcon(entry.id)).toEqual(resolved);
    expect(fetch).toHaveBeenCalledTimes(1);
    const alias = 'bi-tablet-landscape';
    const legacy = catalogData.entries[catalogData.aliases[alias]];
    fetch.mockResolvedValueOnce({ok:true, json: async () => ({[alias]:{id:legacy.id, asset:legacy.asset}})});
    expect((await loadIcon(alias)).id).toBe('device-ipad-horizontal');
    expect(fetch).toHaveBeenLastCalledWith(`/kmp-icons/${runtime.revision}/lookup/${lookupBucket(alias)}.json`, {credentials:'omit'});
    const fallback = await loadIcon('https://example.test/evil.svg');
    expect(fallback.id).toBe('help-circle');
    expect(fetch).toHaveBeenCalledTimes(2);
});

test('one lazy catalog powers common, legacy, and SCA searches without alias duplicate results', async () => {
    global.fetch = jest.fn().mockResolvedValue({ok:true, json: async () => catalog});
    const first = loadCatalog();
    expect(loadCatalog()).toBe(first);
    const entries = await first;
    expect(fetch).toHaveBeenCalledTimes(1);
    for (const query of ['search', 'bi-search', 'heavy combat', 'rapier', 'equestrian', 'scribal', 'metalwork', 'heraldry', 'arts and sciences']) {
        expect(searchCatalog(entries, query).length).toBeGreaterThan(0);
    }
    const matches = searchCatalog(entries, 'bi-search');
    expect(matches.filter(entry => entry.id === 'search')).toHaveLength(1);
    expect(new Set(entries.map(entry => entry.id)).size).toBe(entries.length);
    expect(searchCatalog(entries, 'no-such-activity')).toHaveLength(0);
    for (const [query, id] of [
        ['kingdom courts', 'court-crown'], ['principality courts', 'court-coronet'],
        ['baronial courts', 'court-pearls'], ['guild meeting', 'round-table'],
        ['workshop', 'group-class'], ['communal feast', 'shared-feast'],
        ['group dance', 'dance-circle'], ['war camp', 'encampment'],
        ['medieval trumpets', 'trumpet-straight'], ['banner fanfare', 'trumpet-banner'],
        ['sounding horn', 'curved-horn'], ['herald trumpeter', 'herald-trumpeter'],
        ['town crier', 'herald-proclamation'],
        ['minstrel lute', 'lute'], ['harpist', 'harp'],
        ['vocal singing', 'singing'], ['campfire storytelling', 'bardic-circle'],
        ['chain collar', 'chain-necklace'], ['order of defence', 'master-of-defense'],
        ['pelican vulning itself', 'pelican-vulning'], ['Ansteorra', 'ansteorran-star'],
        ['golden lance', 'lance'], ['nesting weights', 'order-of-the-mark'],
        ['blades of merit', 'single-rapier'], ['centurion', 'phoenix'],
    ]) {
        expect(searchCatalog(entries, query).some(entry => entry.id === id)).toBe(true);
        expect(entries.find(entry => entry.id === id)).toMatchObject({ source: 'kmp', license: 'LicenseRef-KMP' });
        expect(resolveIcon(`kmp:${id}`).id).toBe(id);
    }
    expect(searchCatalog(entries, 'heralds').filter(entry => entry.source === 'kmp')).toHaveLength(5);
    for (const [query, id] of [['iris of merit', 'rainbow'], ['star of merit', 'ansteorran-star'], ['arc de or', 'archery']]) {
        expect(searchCatalog(entries, query).some(entry => entry.id === id)).toBe(true);
    }
    expect(searchCatalog(entries, 'bardic').filter(entry => entry.source === 'kmp').map(entry => entry.id).sort())
        .toEqual(['bardic-circle', 'harp', 'lute', 'singing']);
});
