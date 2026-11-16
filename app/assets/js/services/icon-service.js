import runtime from '../../../resources/icons/runtime.json';

const debug = typeof __KMP_ICON_DEBUG__ !== 'undefined' && __KMP_ICON_DEBUG__;
const base = `/kmp-icons/${runtime.revision}/`;
const fallback = runtime.entries['help-circle'];
const shards = new Map();
const warned = new Set();
const resolved = new Map(Object.entries(runtime.entries));
let catalogPromise;

function identifier(value) {
    const text = String(value ?? '').trim();
    if (text.length > 256) return '';
    if (/\s/.test(text)) {
        const tokens = text.split(/\s+/).filter(token => token.startsWith('bi-') && !/^bi-(?:2xs|xs|sm|lg|xl|2xl|[1-9]x|10x)$/.test(token));
        return tokens.length === 1 ? tokens[0] : '';
    }
    return text;
}

/** Synchronous exact resolution for common controls. Search keywords are never identifiers. */
export function resolveIcon(value) {
    const key = identifier(value);
    return resolved.get(key) || runtime.entries[runtime.aliases[key]] || null;
}

export function lookupBucket(value) {
    let number = 2166136261;
    for (let index = 0; index < value.length; index++) number = Math.imul(number ^ value.charCodeAt(index), 16777619);
    return (number >>> 0) % 64;
}

function diagnostic(value) {
    if (debug && !warned.has(value)) {
        warned.add(value);
        console.warn('Unknown KMP icon:', value);
    }
}

/** Resolve a saved/dynamic identifier without loading the searchable catalog. */
export async function loadIcon(value) {
    const key = identifier(value);
    const known = resolveIcon(key);
    if (known) return known;
    if (!/^[a-zA-Z0-9:-]+$/.test(key)) { diagnostic(value); return fallback; }
    const bucket = lookupBucket(key);
    if (!shards.has(bucket)) {
        const request = fetch(`${base}lookup/${bucket}.json`, { credentials: 'omit' })
            .then(response => { if (!response.ok) throw new Error('Icon lookup unavailable'); return response.json(); })
            .catch(error => { shards.delete(bucket); throw error; });
        shards.set(bucket, request);
    }
    try {
        const entries = await shards.get(bucket);
        const entry = entries[key];
        if (entry && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(entry.id)
            && entry.asset === `${entry.id}.${entry.asset.split('.')[1]}.svg`
            && /^[a-z0-9-]+\.[a-f0-9]{16}\.svg$/.test(entry.asset)) {
            resolved.set(key, entry);
            resolved.set(entry.id, entry);
            return entry;
        }
        diagnostic(value);
    } catch (error) {
        // A retry after reconnect must remain possible. The safe fallback remains visible offline.
        if (debug) console.warn('KMP icon lookup unavailable:', key, error.message);
    }
    return fallback;
}

/** Warm only the registered local drawings referenced by an offline snapshot, with bounded concurrency. */
export async function preloadIcons(values) {
    const pending = [...new Set(values)].filter(Boolean);
    await Promise.all(Array.from({ length: Math.min(4, pending.length) }, async () => {
        while (pending.length) {
            const entry = await loadIcon(pending.pop());
            try { await fetch(`${base}${entry.asset}`, { credentials: 'omit' }); }
            catch { /* The public shell's fallback stays available; private snapshot refresh can still succeed. */ }
        }
    }));
}

const escapeAttribute = value => String(value).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

/** Markup API for existing JavaScript template strings. Never accepts markup or an asset URL. */
export function iconHtml(value, options = {}) {
    const entry = resolveIcon(value);
    const name = entry?.id || identifier(value);
    const label = options.label;
    const size = options.size ? `--kmp-icon-size:${Math.max(8, Math.min(160, Number(options.size) || 20))}px;` : '';
    const style = `${size}--kmp-icon:url(&quot;${base}${(entry || fallback).asset}&quot;)`;
    return `<kmp-icon name="${escapeAttribute(name)}" class="${escapeAttribute(options.class || '')}" style="${style}" ${label ? `role="img" aria-label="${escapeAttribute(label)}"` : 'aria-hidden="true"'}></kmp-icon>`;
}

/** DOM API. The name attribute is the sole mutable icon state; utility classes stay intact. */
export function createIcon(value, options = {}) {
    const element = document.createElement('kmp-icon');
    if (options.class) element.className = options.class;
    if (options.size) element.style.setProperty('--kmp-icon-size', `${Math.max(8, Math.min(160, Number(options.size) || 20))}px`);
    if (options.label) { element.setAttribute('role', 'img'); element.setAttribute('aria-label', options.label); }
    else element.setAttribute('aria-hidden', 'true');
    setIcon(element, value);
    return element;
}

export function setIcon(element, value) {
    if (!element) return;
    const known = resolveIcon(value);
    element.setAttribute('name', known?.id || identifier(value));
    element.style.setProperty('--kmp-icon', `url("${base}${(known || fallback).asset}")`);
}

/** Full metadata is fetched only by a picker/search consumer, never by ordinary rendering. */
export function loadCatalog() {
    if (!catalogPromise) {
        catalogPromise = fetch(`${base}catalog.json`, { credentials: 'omit' })
            .then(response => { if (!response.ok) throw new Error('The icon catalog could not be loaded.'); return response.json(); })
            .then(entries => {
                for (const entry of entries) {
                    if (/^[a-z0-9-]+$/.test(entry.id) && /^[a-z0-9-]+\.[a-f0-9]{16}\.svg$/.test(entry.asset)
                        && entry.asset.startsWith(`${entry.id}.`)) {
                        const descriptor = { id: entry.id, asset: entry.asset };
                        resolved.set(entry.id, descriptor);
                        for (const alias of entry.aliases) resolved.set(alias, descriptor);
                    }
                }
                return entries;
            })
            .catch(error => { catalogPromise = null; throw error; });
    }
    return catalogPromise;
}

export function searchCatalog(catalog, query) {
    const terms = query.toLowerCase().trim().split(/\s+/).filter(Boolean);
    return catalog.filter(entry => {
        const haystack = [entry.id, entry.label, entry.category, entry.upstream, ...entry.keywords, ...entry.aliases].join(' ').toLowerCase();
        return terms.every(term => haystack.includes(term));
    });
}

if (typeof customElements !== 'undefined' && !customElements.get('kmp-icon')) {
    customElements.define('kmp-icon', class extends HTMLElement {
        static get observedAttributes() { return ['name']; }
        connectedCallback() {
            if (this.hasAttribute('aria-label') && !this.hasAttribute('role')) this.setAttribute('role', 'img');
            if (!this.hasAttribute('aria-label') && !this.hasAttribute('aria-hidden')) this.setAttribute('aria-hidden', 'true');
            this.updateIcon();
        }
        attributeChangedCallback() { this.updateIcon(); }
        async updateIcon() {
            const name = this.getAttribute('name');
            const entry = resolveIcon(name) || await loadIcon(name);
            if (this.getAttribute('name') === name) this.style.setProperty('--kmp-icon', `url("${base}${entry.asset}")`);
        }
    });
}

const icons = { resolveIcon, loadIcon, iconHtml, createIcon, setIcon, loadCatalog, searchCatalog, preloadIcons };
if (typeof window !== 'undefined') window.KMP_icons = icons;
export default icons;
