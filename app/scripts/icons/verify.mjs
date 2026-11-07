import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { normalize, readCustom } from './sources.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const files = ['catalog.json', 'runtime.json', 'resolver.php', 'report.json', 'licenses/hugeicons-MIT.txt', 'licenses/tabler-MIT.txt', 'licenses/kmp.txt'];
const before = files.map(file => fs.readFileSync(path.join(root, 'resources/icons', file)));
const css = fs.readFileSync(path.join(root, 'assets/css/icons-generated.css'));
execFileSync(process.execPath, ['scripts/icons/generate.mjs'], { cwd: root, stdio: 'inherit' });
files.forEach((file, index) => assert.deepEqual(fs.readFileSync(path.join(root, 'resources/icons', file)), before[index], `Stale generated file: ${file}`));
assert.deepEqual(fs.readFileSync(path.join(root, 'assets/css/icons-generated.css')), css, 'Stale generated CSS');
for (const nodes of [ [['script', {d:'x'}]], [['path', {d:'M0 0', onload:'alert(1)'}]], [['path', {fill:'url(https://evil.test/x)'}]], [['use', {href:'#x'}]] ]) {
    assert.throws(() => normalize(nodes));
}
const svg = normalize([['path', {d:'M0 0L24 24', strokeWidth:3, stroke:'currentColor', fill:'currentColor', opacity:'.5'}]]);
assert.match(svg, /stroke-width="1.5"/);
assert.doesNotMatch(svg, /stroke-width="3"/);
assert.match(svg, /fill="currentColor"/);
assert.match(svg, /opacity=".5"/);
const custom = JSON.parse(fs.readFileSync(path.join(root, 'resources/icons/custom.json')));
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'resources/icons/catalog.json')));
const customIcons = readCustom(custom);
assert.equal(new Set(customIcons.map(icon => icon.svg)).size, customIcons.length, 'Custom subjects must have distinct drawings');
for (const icon of customIcons) {
    const id = manifest.aliases[icon.key];
    const entry = manifest.entries[id];
    assert.equal(entry.source, 'kmp');
    assert.equal(entry.license, 'LicenseRef-KMP');
    assert.equal(fs.readFileSync(path.join(root, 'webroot/kmp-icons', manifest.revision, entry.asset), 'utf8'), icon.svg);
}
assert.throws(() => readCustom({ ...custom, version: 'unreviewed' }));
assert.throws(() => readCustom({ ...custom, icons: { '../escape': { description: 'Invalid path', nodes: [['path', {d:'M0 0'}]] } } }));
assert.throws(() => readCustom({ ...custom, icons: { 'unsafe': { description: 'Executable content', nodes: [['script', {}]] } } }));
console.log('Icon normalization/security and deterministic regeneration passed.');
