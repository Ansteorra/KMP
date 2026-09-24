/** Exercise browser-check setup/teardown failures without opening a browser or touching the database. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const support = path.resolve(__dirname, '../../ui/support');
const fixtureSource = fs.readFileSync(path.join(support, 'offline-app-browser-check.cjs'), 'utf8');
const scripts = ['gathering-schedule-browser-check.cjs', 'public-gathering-attendance-browser-check.cjs'];

function harness(filename, source = fixtureSource, failure = 'launch') {
    const fixture = { id: 123, name: 'Synthetic fixture', memberId: 456, email: 'fixture@example.test' };
    const runPhpJson = jest.fn(() => fixture);
    const close = jest.fn(async () => { if (failure === 'close') throw new Error('close failed'); });
    const launch = jest.fn(async () => {
        if (failure === 'launch') throw new Error('launch failed');
        return { close, newContext: async () => { throw new Error('stop before application access'); } };
    });
    const module = { exports: {} };
    const require = name => {
        if (name === 'node:assert/strict') return assert;
        if (name === 'node:fs') return { readFileSync: () => source };
        if (name === 'playwright') return { chromium: { launch } };
        if (name === '@playwright/test') return { expect: jest.fn() };
        if (name === './ui-helpers.cjs') return { runPhpJson };
        throw new Error(`Unexpected dependency ${name}`);
    };
    require.resolve = name => name;
    require.main = module;
    const sandbox = { require, module, console: { error: jest.fn() }, process: { exitCode: 0 } };
    const script = fs.readFileSync(path.join(support, filename), 'utf8').replace('module.exports = { verifySchedule };', '');
    return { run: () => vm.runInNewContext(script, sandbox), runPhpJson, launch, close, fixture, sandbox };
}

describe.each(scripts)('%s fixture safety', filename => {
    test('reports a missing tenant fixture before database setup', () => {
        const check = harness(filename, 'fixture declaration moved');
        expect(check.run).toThrow('tenantFixture not found');
        expect(check.runPhpJson).not.toHaveBeenCalled();
    });
    const patchTargets = filename.startsWith('gathering-schedule')
        ? ["'created_by' => $admin->id, 'location'", "return ['id' => $gathering->id, 'name'", "$attendance->deleteAll(['gathering_id' => $gathering->id]);"]
        : ["'name' => $name, 'branch_id'", "return ['id' => $gathering->id"];
    test.each(patchTargets)('reports missing patch target %s before database setup', target => {
        const check = harness(filename, fixtureSource.replace(target, 'changed upstream fixture'));
        expect(check.run).toThrow('tenantFixture patch target not found');
        expect(check.runPhpJson).not.toHaveBeenCalled();
    });
    test.each(['launch', 'close'])('cleans synthetic records when Chromium %s fails', async failure => {
        const check = harness(filename, fixtureSource, failure);
        await check.run();
        expect(check.runPhpJson).toHaveBeenCalledTimes(2);
        expect(check.runPhpJson.mock.calls[1][1]).toEqual({
            cleanup: check.fixture.id, name: check.fixture.name,
            memberId: check.fixture.memberId, email: check.fixture.email,
        });
        expect(check.sandbox.process.exitCode).toBe(1);
        expect(check.close).toHaveBeenCalledTimes(failure === 'launch' ? 0 : 1);
    });
});
