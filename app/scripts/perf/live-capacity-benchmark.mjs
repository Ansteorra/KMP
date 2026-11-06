#!/usr/bin/env node
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { createHash, randomUUID } from 'node:crypto';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { pathToFileURL } from 'node:url';
import { JSDOM } from 'jsdom';
import { maintainLiveConnection } from '../../assets/js/services/live-connection-service.js';

const run = promisify(execFile);
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const digest = value => createHash('sha256').update(value).digest('hex');
const percentile = values => values.length ? [...values].sort((a, b) => a - b)[Math.ceil(values.length * .95) - 1] : null;

/** Validate the actual release workload; shortened runs are diagnostics, never passing evidence. */
export function validateManifest(manifest) {
    if (manifest.synthetic !== true || !/^[a-f0-9]{40}$/.test(manifest.commit || '')) throw Error('Synthetic candidate manifest required.');
    if (!Array.isArray(manifest.sessions) || manifest.sessions.length < 500) throw Error('At least 500 accounts required.');
    const identities = new Set();
    for (const session of manifest.sessions) {
        if (!/^[a-z0-9-]+$/.test(session.tenant) || !session.email || !Array.isArray(session.origins) || session.origins.length < 2) {
            throw Error('Each account requires a tenant, email and two web replica origins.');
        }
        const origins = session.origins.map(value => new URL(value));
        if (origins.some((url, index) => url.origin !== session.origins[index] || url.username || url.password
            || (url.protocol !== 'https:' && !url.hostname.endsWith('.localhost')))
            || new Set(origins.map(url => url.hostname)).size !== 1) throw Error('Use safe, same-host replica origins.');
        for (const path of [session.detailPath, ...(session.ordinaryPaths || [])]) {
            if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//') || /[\\\r\n]/.test(path)) throw Error('Relative read routes required.');
        }
        const identity = `${session.tenant}:${session.email.toLowerCase()}`;
        if (identities.has(identity)) throw Error('Accounts must be distinct within each tenant.');
        identities.add(identity);
    }
    const tenants = [...new Set(manifest.sessions.map(session => session.tenant))];
    if (tenants.length !== 5 || tenants.some(tenant => manifest.sessions.filter(session => session.tenant === tenant).length < 100)) {
        throw Error('Five synthetic tenants with at least 100 accounts each required.');
    }
    const database = manifest.database;
    if (!database || !/^[a-zA-Z0-9_.-]+$/.test(database.container) || !/^[a-zA-Z0-9_]+$/.test(database.user)
        || database.tenants?.length !== 5 || database.tenants.some(name => !/^[a-zA-Z0-9_]+$/.test(name))) {
        throw Error('Isolated database telemetry for five tenants required.');
    }
    for (const kind of ['web', 'gateway']) {
        if (!Array.isArray(manifest.replicas?.[kind]) || manifest.replicas[kind].length < 2
            || manifest.replicas[kind].some(replica => !/^[a-zA-Z0-9_.-]+$/.test(replica.container)
                || !/@sha256:[a-f0-9]{64}$/.test(replica.image))) throw Error(`Two immutable ${kind} replicas required.`);
    }
    if (new Set([...manifest.replicas.web, ...manifest.replicas.gateway].map(replica => replica.container)).size < 4) {
        throw Error('Replica containers must be distinct.');
    }
    if (!Array.isArray(manifest.gatewayEndpoints) || new Set(manifest.gatewayEndpoints).size < 2
        || manifest.gatewayEndpoints.some(value => {
            const url = new URL(value);
            return url.username || url.password || url.search || url.hash || url.pathname !== '/events'
                || (url.protocol !== 'https:' && !['localhost', '127.0.0.1'].includes(url.hostname) && !url.hostname.endsWith('.localhost'));
        })) throw Error('Two explicit gateway replica events endpoints required.');
    if (new Set(manifest.sessions.filter(session => session.writer).map(session => session.tenant)).size !== 5) {
        throw Error('Each tenant requires an authorized writer.');
    }
    return manifest;
}

export function releaseGate(report, baseline) {
    const failures = [];
    if (report.durationSeconds < 1800 || report.sessions < 500 || report.tenants < 5) failures.push('workload size');
    if (report.ordinaryRequests < report.durationSeconds * 50 || report.mutations < report.durationSeconds * 5) failures.push('request rates');
    if (report.errors.length || report.missingUpdates) failures.push('unexpected errors or missing updates');
    if (report.requestP95Ms === null || report.requestP95Ms > 1500) failures.push('request latency');
    if (report.liveP95Ms === null || report.liveP95Ms > 2000) failures.push('live latency');
    if (report.distinctSessions < 500 || report.minimumConnected < 500) failures.push('authenticated live connections');
    if (!report.telemetrySamples || report.observabilityComplete !== true || report.runtimeVerified !== true) failures.push('observability');
    if (!baseline || baseline.mode !== 'baseline' || baseline.runtimeVerified !== true || baseline.observabilityComplete !== true
        || !report.environment || JSON.stringify(baseline.environment) !== JSON.stringify(report.environment)
        || baseline.commit !== report.commit || baseline.fixtureDigest !== report.fixtureDigest
        || JSON.stringify(baseline.replicas) !== JSON.stringify(report.replicas)
        || baseline.durationSeconds < 300 || baseline.errors.length || baseline.requestP95Ms === null
        || baseline.ordinaryRequests < baseline.durationSeconds * 50 || baseline.mutations < baseline.durationSeconds * 5
        || report.requestP95Ms > baseline.requestP95Ms * 1.1) failures.push('comparable baseline');
    return { passed: failures.length === 0, failures };
}

class Session {
    cookies = new Map();
    constructor(spec, metrics) { this.spec = spec; this.metrics = metrics; this.origin = spec.origins[0]; }
    async request(path, options = {}) {
        const url = new URL(path, this.origin);
        if (!this.spec.origins.includes(url.origin)) throw Error('Request left the authorized tenant origins.');
        const response = await fetch(url, { ...options, redirect: 'manual', signal: options.signal || AbortSignal.timeout(30000), headers: {
            Cookie: [...this.cookies].map(([key, value]) => `${key}=${value}`).join('; '),
            ...(this.metrics.run ? { 'X-KMP-Performance-Run': this.metrics.run } : {}),
            'X-Requested-With': 'XMLHttpRequest', ...options.headers,
        } });
        for (const cookie of response.headers.getSetCookie()) {
            const first = cookie.split(';', 1)[0]; const equals = first.indexOf('=');
            this.cookies.set(first.slice(0, equals), first.slice(equals + 1));
        }
        return response;
    }
    parse(html, selector) {
        const dom = new JSDOM(html);
        try {
            this.csrf = dom.window.document.querySelector('meta[name="csrf-token"]')?.content || this.csrf;
            const form = dom.window.document.querySelector(selector);
            if (!form) throw Error('Expected authorized form missing.');
            const fields = new URLSearchParams();
            form.querySelectorAll('input[name]').forEach(input => {
                if (!['submit', 'file', 'checkbox', 'radio'].includes(input.type) || input.checked) fields.set(input.name, input.value);
            });
            return { action: form.getAttribute('action'), fields };
        } finally { dom.window.close(); }
    }
    async login(password) {
        const login = await this.request('/members/login');
        const form = this.parse(await login.text(), 'form');
        form.fields.set('email_address', this.spec.email); form.fields.set('password', password);
        const response = await this.request(form.action, { method: 'POST', body: form.fields });
        await response.arrayBuffer();
        if (![302, 303].includes(response.status)) throw Error('Synthetic account login failed.');
        const page = await this.request(this.spec.detailPath);
        const html = await page.text();
        if (page.status !== 200 || html.includes('name="password"')) throw Error('Authenticated detail unavailable.');
        const document = new JSDOM(html);
        const enabled = document.window.document.querySelector('meta[name="kmp-turbo-enabled"]')?.content === 'true';
        const live = Boolean(document.window.document.querySelector('meta[name="kmp-live-subscriptions-url"]'));
        document.window.close();
        if (enabled === this.metrics.baseline || live === this.metrics.baseline) throw Error('Runtime switches do not match the requested baseline/live workload.');
        if (this.spec.writer) this.noteForm = this.parse(html, '#note_new form');
        else {
            const dom = new JSDOM(html);
            this.csrf = dom.window.document.querySelector('meta[name="csrf-token"]')?.content;
            dom.window.close();
        }
        this.cookieDigest = digest([...this.cookies].filter(([key]) => !/csrf/i.test(key)).map(pair => pair.join('=')).join(';'));
        // Exercise the same session against the second replica before measuring.
        this.origin = this.spec.origins[1];
        const other = await this.request(this.spec.detailPath); await other.arrayBuffer();
        if (other.status !== 200) throw Error('Shared-session replica check failed.');
    }
    async refresh(requestId) {
        const pending = this.metrics.pending.get(requestId);
        if (!pending || pending.reading) return;
        pending.reading = true;
        try {
            const response = await this.request('/ui/refresh', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                body: JSON.stringify({ context_url: this.spec.detailPath, changes: ['notes'] }),
            });
            const html = await response.text();
            if (response.status !== 200 || !html.includes(pending.subject)) throw Error('Committed note absent from authorized refresh.');
            this.metrics.live.push(performance.now() - pending.started);
            this.metrics.pending.delete(requestId);
        } catch (error) { this.metrics.errors.push(error.message); }
    }
    async live(signal, gatewayEndpoints) {
        const connection = maintainLiveConnection({ signal,
            authorize: async authorizationSignal => {
                const origin = this.origin;
                const response = await this.request('/live/subscriptions', { method: 'POST', signal: authorizationSignal,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf },
                    body: JSON.stringify({ context_url: this.spec.detailPath }) });
                if (response.status !== 200) throw Error('Live subscription denied.');
                const ticket = await response.json();
                return { ...ticket, url: gatewayEndpoints[this.spec.replica % gatewayEndpoints.length], headers: { Origin: origin } };
            },
            onEvent: event => {
                const id = event.data.match(/request-id="([a-f0-9-]{36})"/)?.[1];
                if (id) this.refresh(id);
            },
            onConnected: () => { this.metrics.connected++; },
            onDisconnected: () => { this.metrics.connected--; if (!signal.aborted) this.metrics.reconnects++; },
            onError: error => this.metrics.errors.push(error.message),
        });
        await connection.done;
    }

}

/** Open-loop rates keep server slowdowns visible instead of quietly reducing the offered workload. */
async function offer(rate, seconds, operation) {
    const start = performance.now(); const work = new Set();
    for (let index = 0; index < rate * seconds; index++) {
        const wait = start + index * 1000 / rate - performance.now();
        if (wait > 0) await delay(wait);
        const pending = operation(index).finally(() => work.delete(pending)); work.add(pending);
    }
    await Promise.all(work);
}

async function main() {
    const manifest = validateManifest(JSON.parse(await readFile(process.env.KMP_CAPACITY_MANIFEST, 'utf8')));
    const password = process.env.KMP_CAPACITY_PASSWORD;
    if (!password) throw Error('KMP_CAPACITY_PASSWORD must be supplied privately.');
    const baselineMode = process.env.KMP_CAPACITY_MODE === 'baseline';
    const seconds = Number(process.env.KMP_CAPACITY_SECONDS || (baselineMode ? 300 : 1800));
    if (!Number.isInteger(seconds) || seconds < 1) throw Error('Invalid duration.');
    const output = process.env.KMP_CAPACITY_OUTPUT;
    if (!output) throw Error('KMP_CAPACITY_OUTPUT required.');
    await mkdir(output, { recursive: true, mode: 0o700 });
    let runtimeVerified = true;
    const host = await run('docker', ['info', '--format', '{{.NCPU}} {{.MemTotal}} {{.ServerVersion}}']);
    const environment = { dockerHost: host.stdout.trim(), replicas: [] };
    for (const replica of [...manifest.replicas.web, ...manifest.replicas.gateway]) {
        const [container, image] = await Promise.all([
            run('docker', ['inspect', '--format', '{"image":{{json .Image}},"cpu":{{json .HostConfig.NanoCpus}},"quota":{{json .HostConfig.CpuQuota}},"period":{{json .HostConfig.CpuPeriod}},"memory":{{json .HostConfig.Memory}},"cpuset":{{json .HostConfig.CpusetCpus}}}', replica.container]),
            run('docker', ['image', 'inspect', '--format', '{{.Id}} {{index .Config.Labels "org.opencontainers.image.revision"}}', replica.image]),
        ]);
        const [imageId, revision] = image.stdout.trim().split(/\s+/);
        const actual = JSON.parse(container.stdout);
        environment.replicas.push({ container: replica.container, ...actual });
        if (actual.image !== imageId || revision !== manifest.commit) runtimeVerified = false;
    }
    if (!runtimeVerified) throw Error('Running replicas do not match the candidate commit and immutable images.');
    const metrics = { errors: [], request: [], live: [], pending: new Map(), connected: 0, reconnects: 0, baseline: baselineMode };
    const sessions = manifest.sessions.map((spec, index) => new Session({ ...spec, replica: index }, metrics));
    let next = 0;
    await Promise.all(Array.from({ length: 10 }, async () => {
        while (next < sessions.length) await sessions[next++].login(password);
    }));
    const abort = new AbortController();
    const connections = baselineMode ? [] : sessions.map(session => session.live(abort.signal, manifest.gatewayEndpoints || []));
    const readyAt = performance.now();
    while (!baselineMode && metrics.connected < sessions.length && performance.now() - readyAt < 60000) await delay(100);
    if (!baselineMode && metrics.connected !== sessions.length) { abort.abort(); await Promise.all(connections); throw Error('Not all live sessions connected.'); }
    const started = new Date().toISOString(); const monotonicStart = performance.now();
    metrics.run = digest(randomUUID()).slice(0, 16);
    let minimumConnected = metrics.connected; let telemetryBusy = false; const telemetry = [];
    const containers = [...manifest.replicas.web, ...manifest.replicas.gateway].map(replica => replica.container);
    const sample = async () => {
        if (telemetryBusy) return;
        telemetryBusy = true;
        minimumConnected = Math.min(minimumConnected, metrics.connected);
        try {
            const [stats, gateways, ...databases] = await Promise.all([
                run('docker', ['stats', '--no-stream', '--format', '{{json .}}', ...containers], { timeout: 10000 }),
                Promise.all(manifest.gatewayEndpoints.map(async endpoint => {
                    const response = await fetch(new URL('/health', endpoint), { signal: AbortSignal.timeout(5000) });
                    if (!response.ok) throw Error('Gateway health unavailable.');
                    return response.json();
                })),
                ...manifest.database.tenants.map(async database => {
                    const result = await run('docker', ['exec', manifest.database.container, 'psql', '-X', '-A', '-t',
                        '-U', manifest.database.user, '-d', database, '-c',
                        "SELECT json_build_object('connections', (SELECT count(*) FROM pg_stat_activity WHERE datname = current_database()),"
                        + " 'totalConnections', (SELECT count(*) FROM pg_stat_activity),"
                        + " 'pending', count(*), 'oldestSeconds', COALESCE(EXTRACT(EPOCH FROM CURRENT_TIMESTAMP - min(created)), 0))"
                        + ' FROM ui_update_outbox WHERE delivered IS NULL'], { timeout: 10000 });
                    return { database, ...JSON.parse(result.stdout) };
                }),
            ]);
            telemetry.push({ at: new Date().toISOString(), connected: metrics.connected,
                stats: stats.stdout.trim().split('\n').map(line => JSON.parse(line)), gateways, databases });
        } catch { metrics.errors.push('Replica telemetry unavailable.'); }
        finally { telemetryBusy = false; }
    };
    await sample(); const sampler = setInterval(sample, 5000);
    const writers = sessions.filter(session => session.spec.writer);
    const measured = async (operation, ordinary = false) => {
        const at = performance.now();
        try { await operation(); } catch (error) { metrics.errors.push(error.message); }
        if (ordinary) metrics.request.push(performance.now() - at);
    };
    let ordinaryRequests = 0; let mutations = 0;
    try {
        await Promise.all([
            offer(50, seconds, index => measured(async () => {
                ordinaryRequests++;
                const session = sessions[index % sessions.length];
                session.origin = session.spec.origins[Math.floor(index / sessions.length) % session.spec.origins.length];
                const paths = session.spec.ordinaryPaths?.length ? session.spec.ordinaryPaths : [session.spec.detailPath];
                const response = await session.request(paths[Math.floor(index / sessions.length) % paths.length]);
                await response.arrayBuffer(); if (response.status !== 200) throw Error(`Ordinary request returned ${response.status}.`);
            }, true)),
            offer(5, seconds, index => measured(async () => {
                mutations++;
                const session = writers[index % writers.length]; const id = randomUUID();
                const subject = `Capacity ${id}`; const fields = new URLSearchParams(session.noteForm.fields);
                fields.set('subject', subject); fields.set('body', 'Synthetic release capacity fixture');
                if (fields.has('private')) fields.set('private', '0');
                if (!baselineMode) metrics.pending.set(id, { subject, started: performance.now() });
                const response = await session.request(session.noteForm.action, { method: 'POST', body: fields,
                    headers: { Accept: 'text/vnd.turbo-stream.html', 'X-KMP-Mutation-ID': id, 'X-CSRF-Token': session.csrf } });
                const html = await response.text();
                if (response.status !== 200 || !html.includes(subject)) throw Error(`Mutation returned ${response.status} or omitted its saved note.`);
            })),
        ]);
        await delay(baselineMode ? 0 : 10000);
    } finally { clearInterval(sampler); abort.abort(); await Promise.all(connections); }
    while (telemetryBusy) await delay(50);
    const finished = new Date().toISOString();
    const serverRequests = [];
    for (const replica of manifest.replicas.web) {
        try {
            const log = await run('docker', ['exec', replica.container, 'cat', '/var/www/html/logs/performance.log'], {
                timeout: 30000, maxBuffer: 128 * 1024 * 1024,
            });
            for (const line of log.stdout.split('\n')) {
                if (!line.includes('[request_timing]') || !line.includes(` performance_run=${metrics.run}`)) continue;
                const values = {};
                for (const field of ['duration_ms', 'query_count', 'db_total_ms', 'cpu_user_ms', 'cpu_sys_ms', 'response_bytes']) {
                    values[field] = Number(line.match(new RegExp(` ${field}=([0-9.]+)`))?.[1]);
                }
                if (Object.values(values).every(Number.isFinite)) serverRequests.push({ replica: replica.container, ...values });
            }
        } catch { metrics.errors.push('Complete server request telemetry unavailable.'); }
    }
    const report = {
        schema: 1, run: metrics.run, commit: manifest.commit, runtimeVerified, environment,
        fixtureDigest: digest(JSON.stringify(manifest.sessions)), mode: baselineMode ? 'baseline' : 'live',
        started, finished, durationSeconds: seconds, elapsedSeconds: (performance.now() - monotonicStart) / 1000,
        sessions: sessions.length, distinctSessions: new Set(sessions.map(session => session.cookieDigest)).size,
        tenants: new Set(sessions.map(session => session.spec.tenant)).size, replicas: manifest.replicas,
        ordinaryRequests, mutations, requestP95Ms: percentile(metrics.request), liveP95Ms: percentile(metrics.live),
        missingUpdates: metrics.pending.size, errors: metrics.errors, reconnects: metrics.reconnects,
        minimumConnected, telemetrySamples: telemetry.length,
        serverRequests: serverRequests.length, queryCount: serverRequests.reduce((sum, row) => sum + row.query_count, 0),
        observabilityComplete: serverRequests.length >= ordinaryRequests + mutations && telemetry.length > 0
            && manifest.replicas.web.every(replica => serverRequests.some(row => row.replica === replica.container))
            && telemetry.every(sample => sample.stats.length >= 4 && sample.databases.length === 5
                && (baselineMode || (sample.gateways.every(gateway => gateway.ready && gateway.connections > 0)
                    && sample.gateways.reduce((sum, gateway) => sum + gateway.connections, 0) >= sessions.length))),
    };
    const baseline = process.env.KMP_CAPACITY_BASELINE ? JSON.parse(await readFile(process.env.KMP_CAPACITY_BASELINE, 'utf8')) : null;
    report.gate = releaseGate(report, baseline);
    await writeFile(`${output}/capacity-report.json`, JSON.stringify(report, null, 2), { mode: 0o600 });
    await writeFile(`${output}/replica-telemetry.json`, JSON.stringify(telemetry, null, 2), { mode: 0o600 });
    await writeFile(`${output}/server-request-metrics.json`, JSON.stringify(serverRequests), { mode: 0o600 });
    console.log(JSON.stringify({ mode: report.mode, sessions: report.sessions, requestP95Ms: report.requestP95Ms,
        liveP95Ms: report.liveP95Ms, gate: report.gate }));
    if (!baselineMode && !report.gate.passed) process.exitCode = 1;
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    main().catch(error => { console.error(error.message); process.exitCode = 1; });
}
