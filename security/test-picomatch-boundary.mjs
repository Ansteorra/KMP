import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import test from 'node:test';
import { build, loadConfigFromFile } from '../app/node_modules/vite/dist/node/index.js';

test('the real Vite guard rejects picomatch in page and worker bundles', async () => {
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
            worker: loaded.config.worker,
            build: { write: false, rollupOptions: { input } },
        });
        await writeFile(input, 'console.log("benign browser entry");');
        await compile();
        for (const dependency of ['picomatch', 'anymatch/node_modules/picomatch/index.js']) {
            await writeFile(input, `import pm from ${JSON.stringify(dependency)}; console.log(pm('*.js'));
`);
            await assert.rejects(compile, /Picomatch is build\/test-only/);
        }
        for (const constructor of ['Worker', 'SharedWorker']) {
            await writeFile(input, `new ${constructor}(new URL('./worker.js', import.meta.url), { type: 'module' });`);
            await writeFile(join(scratch, 'worker.js'), 'self.postMessage("benign worker");');
            await compile();
            await writeFile(join(scratch, 'worker.js'), "import pm from 'picomatch'; self.postMessage(pm('*.js')('test.js'));");
            await assert.rejects(compile, /Picomatch is build\/test-only/);
        }
    } finally {
        await rm(scratch, { recursive: true, force: true });
    }
});
