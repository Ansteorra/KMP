import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const exceptions = new Set(['src/Services/IconCatalog.php', 'src/Command/IconsInventoryCommand.php', 'assets/js/services/icon-service.js']);
const failures = [];
function scan(directory) {
    for (const item of fs.readdirSync(path.join(root, directory), { withFileTypes: true })) {
        const file = `${directory}/${item.name}`;
        if (item.isDirectory()) {
            if (!['Migrations', 'Seeds', 'tests', 'node_modules', 'vendor'].includes(item.name)) scan(file);
        } else if (/\.(php|js|css)$/.test(file) && !exceptions.has(file)
            && !/\/(?:bootstrap(?:\.min)?|normalize\.min|milligram\.min)\.css$/.test(file)) {
            const source = fs.readFileSync(path.join(root, file), 'utf8');
            if (/\bbi-[a-z0-9-]+\b|\bi\.bi\b|bootstrap-icons/.test(source)) failures.push(file);
        }
    }
}
for (const area of ['src', 'templates', 'assets', 'plugins']) scan(area);
const catalog = JSON.parse(fs.readFileSync(path.join(root, 'resources/icons/catalog.json')));
// Validate literal declarative uses too: static names render before JavaScript runs.
for (const area of ['src', 'templates', 'assets', 'plugins']) {
    const check = directory => {
        for (const item of fs.readdirSync(path.join(root, directory), { withFileTypes: true })) {
            if (item.isDirectory()) {
                if (!['Migrations', 'Seeds', 'tests', 'node_modules', 'vendor'].includes(item.name)) check(`${directory}/${item.name}`);
            } else if (/\.(php|js)$/.test(item.name)) {
                const file = `${directory}/${item.name}`;
                const source = fs.readFileSync(path.join(root, file), 'utf8');
                for (const match of source.matchAll(/<kmp-icon\s+name=["']([a-z0-9-]+)["']/g)) {
                    if (!catalog.entries[match[1]]) failures.push(`${file}: unregistered ${match[1]}`);
                }
            }
        }
    };
    check(area);
}
if (failures.length) throw new Error(`Use the KMP icon contract:\n${failures.join('\n')}`);
console.log('No direct Bootstrap Icons consumers; literal catalog IDs are registered.');
