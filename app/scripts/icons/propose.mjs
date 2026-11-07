// Advisory only: writes a candidate map. Builds NEVER adopt new source choices automatically.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { readSources } from './sources.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const icons = await readSources(root);
const tabler = new Map(icons.filter(icon => icon.source === 'tabler').map(icon => [icon.slug, icon]));
const preferredSubjects = {
    BodyArmorIcon: 'armored-combat', HorseSaddleIcon: 'equestrian', QuillWrite01Icon: 'scribal',
    CrownIcon: 'crown', AnvilIcon: 'metalwork', FencingIcon: 'fencing', FencingMaskIcon: 'fencing-mask',
};
const subjectAlternatives = new Set(['crown', 'anvil', 'bow', 'horse', 'sword', 'swords', 'shield', 'scroll', 'needle-thread', 'laurel-wreath', 'helmet', 'armor', 'feather', 'quill', 'flag', 'award']);
const controls = {
    search: 'search', 'search-01': 'search', 'search-02': 'search',
    add: 'plus', 'add-01': 'plus', 'add-02': 'plus', 'add-circle': 'circle-plus',
    remove: 'minus', 'minus-sign': 'minus', 'cancel-01': 'x', 'cancel-02': 'x', 'cancel-circle': 'circle-x',
    'delete-01': 'trash', 'delete-02': 'trash', 'delete-03': 'trash', 'delete-04': 'trash',
    edit: 'pencil', 'edit-01': 'pencil', 'edit-02': 'edit', 'pencil-edit-01': 'edit', 'pencil-edit-02': 'edit',
    'floppy-disk': 'device-floppy', 'menu-01': 'menu-2', 'menu-02': 'menu', 'menu-03': 'menu-3',
    'settings-01': 'settings', 'settings-02': 'adjustments', 'settings-03': 'adjustments-horizontal',
    'filter-horizontal': 'adjustments-horizontal', 'filter-vertical': 'adjustments',
    'filter': 'filter', 'filter-horizontal-01': 'adjustments-horizontal',
    'tick-01': 'check', 'tick-02': 'check', 'tick-03': 'checks', 'tick-04': 'check', 'tick-double-01': 'checks', 'tick-double-02': 'checks',
    'checkmark-circle-01': 'circle-check', 'checkmark-circle-02': 'circle-check',
    'alert-01': 'alert-triangle', 'alert-02': 'alert-circle', 'information-circle': 'info-circle',
    'help-circle': 'help-circle', 'user': 'user', 'user-group': 'users-group', 'user-multiple': 'users',
    'notification-01': 'bell', 'notification-02': 'bell', 'notification-03': 'bell-ringing',
    'calendar-01': 'calendar', 'calendar-02': 'calendar', 'calendar-03': 'calendar', 'calendar-04': 'calendar-month', 'calendar-05': 'calendar-week',
    'calendar-add-01': 'calendar-plus', 'calendar-add-02': 'calendar-plus',
    'calendar-check-01': 'calendar-check', 'calendar-check': 'calendar-check',
    'folder-01': 'folder', 'folder-02': 'folder', 'file-01': 'file', 'file-02': 'file',
    'upload-01': 'upload', 'upload-02': 'upload', 'download-01': 'download', 'download-02': 'download',
    'link-01': 'link', 'link-02': 'link', 'share-01': 'share', 'share-02': 'share',
};
for (const direction of ['left', 'right', 'up', 'down']) {
    controls[`arrow-${direction}-01`] = `chevron-${direction}`;
    controls[`arrow-${direction}-02`] = `arrow-${direction}`;
    controls[`arrow-${direction}-03`] = `arrow-bar-${direction}`;
    controls[`arrow-${direction}-04`] = `arrow-narrow-${direction}`;
    controls[`arrow-${direction}-05`] = `arrow-bar-${direction}`;
    controls[`arrow-${direction}-double`] = `chevrons-${direction}`;
}
const existingFile = path.join(root, 'resources/icons/selections.json');
const existing = fs.existsSync(existingFile) ? JSON.parse(fs.readFileSync(existingFile)) : {};
const selected = {};
const usedIds = new Set();
const geometry = new Map();
for (const icon of icons) {
    if (existing[icon.key]) {
        selected[icon.key] = existing[icon.key];
        usedIds.add(existing[icon.key].id);
        if (!geometry.has(icon.svg)) geometry.set(icon.svg, existing[icon.key].preferred);
        continue;
    }
    let id = icon.slug;
    let preferred = icon.key;
    let reason = 'Distinct outline concept retained';
    if (icon.source === 'kmp') {
        reason = 'Original KMP activity imagery retained';
    } else if (icon.source === 'tabler') {
        if (icon.slug === 'bow') id = 'archery';
        if (icon.slug === 'needle-thread') id = 'needlework';
        if (icon.slug === 'crown') id = 'crown-simple';
        if (icon.slug === 'anvil') id = 'anvil-simple';
    } else if (preferredSubjects[icon.name]) {
        id = preferredSubjects[icon.name]; reason = 'Preferred SCA subject imagery';
    } else {
        const common = controls[icon.slug] || (!subjectAlternatives.has(icon.slug) && tabler.has(icon.slug) ? icon.slug : null);
        if (common && tabler.has(common)) {
            preferred = tabler.get(common).key; id = selected[preferred].id;
            reason = 'Tabler preferred for interchangeable application concept';
        } else if (geometry.has(icon.svg)) {
            preferred = geometry.get(icon.svg); id = selected[preferred].id; reason = 'Identical normalized drawing';
        } else if (usedIds.has(id)) {
            id += '-rounded'; reason = 'Distinct rounded subject drawing retained';
        }
    }
    if (preferred === icon.key && usedIds.has(id)) throw new Error(`Review ID collision ${id}`);
    usedIds.add(id);
    selected[icon.key] = { id, preferred, reason };
    if (!geometry.has(icon.svg)) geometry.set(icon.svg, preferred);
}
fs.writeFileSync(path.join(root, 'resources/icons/selections-proposal.json'), JSON.stringify(selected, null, 2) + '\n');
console.log('Wrote selections-proposal.json. Review this candidate before replacing selections.json.');
