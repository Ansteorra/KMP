import { spawnSync } from 'node:child_process';

// Probe the host Node realm where jest-config loads deepmerge, outside jsdom.
function probe(source) {
    const child = spawnSync(process.execPath, ['-e', source], {
        cwd: process.cwd(),
        env: process.env,
        encoding: 'utf8',
        timeout: 10000,
    });
    expect({ status: child.status, stderr: child.stderr, error: child.error }).toEqual({
        status: 0, stderr: '', error: undefined,
    });
}

describe('GHSA-gcqx-w48m-w2h4 runtime mitigation', () => {
    test.each([
        ['null-prototype defaults', 'Object.create(null)', '{"__proto__":{"isAdmin":true}}'],
        ['null placeholder', '({session:null})', '{"session":{"__proto__":{"isAdmin":true}}}'],
        ['primitive placeholder', '({session:0})', '{"session":{"__proto__":{"isAdmin":true}}}'],
        ['undefined placeholder', '({session:undefined})', '{"session":{"__proto__":{"isAdmin":true}}}'],
    ])('inherited worker options reject %s poisoning', (_name, target, payload) => {
        probe([
            'const assert = require("node:assert/strict");',
            'const merge = require("deepmerge");',
            'const target = ' + target + ';',
            'const source = JSON.parse(' + JSON.stringify(payload) + ');',
            'assert.throws(() => merge(target, source), {code:"ERR_PROTO_ACCESS"});',
            'assert.equal(({}).isAdmin, undefined);',
        ].join('\n'));
    });

    test('rejects copying an own prototype key from the merge target', () => {
        const payload = '{"__proto__":{"isAdmin":true}}';
        probe([
            'const assert = require("node:assert/strict");',
            'const merge = require("deepmerge");',
            'const target = JSON.parse(' + JSON.stringify(payload) + ');',
            'assert.throws(() => merge(target, {}), {code:"ERR_PROTO_ACCESS"});',
        ].join('\n'));
    });

    test('preserves ordinary nested, array, and merge-all behavior', () => {
        probe([
            'const assert = require("node:assert/strict");',
            'const merge = require("deepmerge");',
            'const defaults = {nested:{left:1}, values:[1]};',
            'const merged = merge(defaults, {nested:{right:2}, values:[2]});',
            'assert.deepEqual(merged, {nested:{left:1,right:2},values:[1,2]});',
            'assert.deepEqual(defaults, {nested:{left:1},values:[1]});',
            'assert.deepEqual(merge.all([{a:1},{b:2}]), {a:1,b:2});',
            'assert.equal(Object.getPrototypeOf(merged), Object.prototype);',
        ].join('\n'));
    });
});
