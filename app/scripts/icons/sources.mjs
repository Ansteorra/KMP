import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

export const sources = {
    tabler: { package: '@tabler/icons', version: '3.50.0', repository: 'https://github.com/tabler/tabler-icons', style: 'Outline', license: 'MIT', notice: 'licenses/tabler-MIT.txt' },
    hugeicons: { package: '@hugeicons/core-free-icons', version: '4.3.5', repository: 'https://github.com/hugeicons/hugeicons', style: 'Free Stroke Rounded', license: 'MIT', notice: 'licenses/hugeicons-MIT.txt' },
    kmp: { version: '1.2.1', repository: 'local:resources/icons/custom.json', style: 'Original Rounded Outline', license: 'LicenseRef-KMP', notice: 'licenses/kmp.txt' },
};

export const slug = name => name.replace(/Icon$/, '').replace(/([a-z\d])([A-Z])/g, '$1-$2')
    .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2').replace(/([a-z])(\d)/gi, '$1-$2').toLowerCase();

const attributes = new Set(['d', 'cx', 'cy', 'r', 'x', 'y', 'width', 'height', 'rx', 'ry', 'opacity', 'fill-rule', 'clip-rule', 'transform', 'points', 'x1', 'x2', 'y1', 'y2']);
const tags = new Set(['path', 'circle', 'rect', 'ellipse', 'line', 'polygon', 'polyline']);
const escape = value => String(value).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');

/** Accept drawing tuples, never raw SVG/HTML. All paint and stroke presentation is centralized here. */
export function normalize(nodes) {
    if (!Array.isArray(nodes) || !nodes.length) throw new Error('Empty icon');
    const body = nodes.map(([tag, input]) => {
        if (!tags.has(tag) || !input || typeof input !== 'object') throw new Error(`Unsafe SVG element ${tag}`);
        const attrs = {};
        for (let [key, value] of Object.entries(input)) {
            key = key.replace(/[A-Z]/g, letter => `-${letter.toLowerCase()}`);
            if (key === 'key') continue;
            if (['stroke-width', 'stroke-linecap', 'stroke-linejoin'].includes(key)) continue;
            if ((/^on/i.test(key) || /href/i.test(key) || /url\s*\(|[<>]/i.test(String(value)))) throw new Error(`Unsafe SVG attribute ${key}`);
            if (key === 'stroke' || key === 'fill') {
                if (!['none', 'currentColor'].includes(value)) throw new Error(`Unexpected SVG paint ${value}`);
            } else if (!attributes.has(key)) throw new Error(`Unsupported SVG attribute ${key}`);
            attrs[key] = value;
        }
        return `<${tag}${Object.keys(attrs).sort().map(key => ` ${key}="${escape(attrs[key])}"`).join('')}/>`;
    }).join('');
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${body}</svg>\n`;
}

/** Original KMP drawings use the same restricted tuples and normalization as upstream icons. */
export function readCustom(collection) {
    if (collection.schema !== 1 || collection.version !== sources.kmp.version || !collection.icons
        || typeof collection.icons !== 'object' || Array.isArray(collection.icons)) throw new Error('Invalid KMP icon collection');
    return Object.entries(collection.icons).sort(([a], [b]) => a.localeCompare(b, 'en')).map(([name, icon]) => {
        if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name) || !icon.description?.trim()) throw new Error(`Invalid KMP drawing ${name}`);
        return { key: `kmp:${name}`, source: 'kmp', name, slug: name, category: 'SCA activities', keywords: [], svg: normalize(icon.nodes) };
    });
}

export async function readSources(root) {
    for (const source of Object.values(sources).filter(source => source.package)) {
        const pkg = JSON.parse(fs.readFileSync(path.join(root, 'node_modules', source.package, 'package.json')));
        if (pkg.version !== source.version || pkg.license !== 'MIT') throw new Error(`Unapproved version/license: ${source.package}`);
    }
    const base = path.join(root, 'node_modules/@tabler/icons');
    const metadata = JSON.parse(fs.readFileSync(path.join(base, 'icons.json')));
    const tabler = JSON.parse(fs.readFileSync(path.join(base, 'tabler-nodes-outline.json')));
    const result = Object.keys(tabler).sort().map(name => ({
        key: `tabler:${name}`, source: 'tabler', name, slug: name,
        category: metadata[name]?.category || 'General', keywords: metadata[name]?.tags || [], svg: normalize(tabler[name]),
    }));
    const directory = path.join(root, 'node_modules/@hugeicons/core-free-icons/dist/esm');
    for (const file of fs.readdirSync(directory).filter(file => file.endsWith('Icon.js')).sort()) {
        const name = file.slice(0, -3);
        const nodes = (await import(pathToFileURL(path.join(directory, file)).href)).default;
        result.push({ key: `hugeicons:${name}`, source: 'hugeicons', name, slug: slug(name), category: 'General', keywords: [], svg: normalize(nodes) });
    }
    return result.concat(readCustom(JSON.parse(fs.readFileSync(path.join(root, 'resources/icons/custom.json')))));
}
