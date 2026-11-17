import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import test from 'node:test';
import { build, loadConfigFromFile } from '../app/node_modules/vite/dist/node/index.js';

test('the real Vite guard rejects direct and nested picomatch browser imports', async () => {
    const app = fileURLToPath(new URL('../app/', import.meta.url));
    const loaded = await loadConfigFromFile(
        { command: 'build', mode: 'production' }, join(app, 'vite.config.js'), app,
    );
    const guard = loaded.config.plugins.find(plugin => plugin.name === 'kmp-no-runtime-picomatch');
    assert.ok(guard);
    const scratch = await mkdtemp(join(app, '.picomatch-boundary-'));
    try {
        const input = join(scratch, 'entry.js');
        const compile = () => build({
            configFile: false, root: app, logLevel: 'silent', plugins: [guard],
            build: { write: false, rollupOptions: { input } },
        });
        await writeFile(input, 'console.log("benign browser entry");');
        await compile();
        for (const dependency of ['picomatch', 'anymatch/node_modules/picomatch/index.js']) {
            await writeFile(input, `import pm from ${JSON.stringify(dependency)}; console.log(pm('*.js'));
`);
            await assert.rejects(compile, /Picomatch is build\/test-only/);
        }
    } finally {
        await rm(scratch, { recursive: true, force: true });
    }
});
