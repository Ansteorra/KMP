import { shortSiteTitle } from '../../../assets/js/services/app-branding-service.js';

afterEach(() => { document.head.innerHTML = ''; });
test('reads the public setting as text, including punctuation and markup-like characters', () => {
    const meta = document.createElement('meta');
    meta.name = 'kmp-short-site-title';
    meta.content = '  Guild & <Friends>  ';
    document.head.append(meta);
    expect(shortSiteTitle()).toBe('Guild & <Friends>');
    meta.content = 'AMP';
    expect(shortSiteTitle()).toBe('AMP');
});
test('older cached shells without branding use a neutral fallback', () => {
    document.head.innerHTML = '';
    expect(shortSiteTitle()).toBe('this app');
});
