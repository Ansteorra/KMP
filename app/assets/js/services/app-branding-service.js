/** Public branding embedded by the server in both live pages and cached mobile shells. */
export function shortSiteTitle() {
    return document.querySelector('meta[name="kmp-short-site-title"]')?.content?.trim() || 'this app';
}
