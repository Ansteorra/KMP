#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { chromium } = require('playwright');

const profileRoutes = [
    '/members',
    '/members/profile',
    '/members/view/1',
    '/members/grid-data',
    '/gatherings/grid-data',
    '/branches/grid-data',
    '/roles/grid-data',
];

const flowRoutes = [
    '/members/grid-data',
    '/gatherings/grid-data',
    '/branches/grid-data',
    '/roles/grid-data',
    '/members/profile',
    '/members/view/1',
];

const config = {
    baseUrl: process.env.KMP_BASE_URL || 'http://127.0.0.1:8080',
    loginEmail: process.env.KMP_LOGIN_EMAIL || 'admin@amp.ansteorra.org',
    loginPassword: process.env.KMP_LOGIN_PASSWORD || 'TestPassword',
    routeRuns: Math.max(1, Number.parseInt(process.env.KMP_ROUTE_RUNS || '5', 10)),
    concurrencyLevels: parseConcurrencyLevels(process.env.KMP_CONCURRENCY_LEVELS || '1,5,10,20'),
    slowRequestMs: Math.max(100, Number.parseInt(process.env.KMP_SLOW_REQUEST_MS || '1500', 10)),
    cpuTargetUtilizationPct: Math.min(95, Math.max(10, Number.parseFloat(process.env.KMP_CPU_TARGET_UTIL_PCT || '70'))),
    memoryTargetUtilizationPct: Math.min(95, Math.max(10, Number.parseFloat(process.env.KMP_MEMORY_TARGET_UTIL_PCT || '80'))),
    telemetrySampleMs: Math.max(200, Number.parseInt(process.env.KMP_TELEMETRY_SAMPLE_MS || '500', 10)),
    outputDir: process.env.KMP_PERF_OUTPUT_DIR || '/workspaces/KMP/test-results/perf',
    enableDbProfile: (process.env.KMP_ENABLE_DB_PROFILE || '1') === '1',
    dbHost: process.env.KMP_DB_HOST || '127.0.0.1',
    dbUser: process.env.KMP_DB_USER || 'KMPSQLDEV',
    dbPass: process.env.KMP_DB_PASS || 'P@ssw0rd',
    dbName: process.env.KMP_DB_NAME || 'KMP_DEV',
};

let dbAvailable = false;
let originalDbSettings = null;

function parseConcurrencyLevels(value) {
    const levels = value
        .split(',')
        .map((part) => Number.parseInt(part.trim(), 10))
        .filter((n) => Number.isInteger(n) && n > 0);

    if (levels.length === 0) {
        return [1, 5, 10, 20];
    }

    return [...new Set(levels)].sort((a, b) => a - b);
}

function percentile(values, p) {
    if (!values.length) {
        return null;
    }

    const sorted = [...values].sort((a, b) => a - b);
    const rank = Math.max(0, Math.ceil((p / 100) * sorted.length) - 1);

    return sorted[rank];
}

function summarizeNumbers(values) {
    if (!values.length) {
        return {
            min: null,
            avg: null,
            p50: null,
            p95: null,
            max: null,
        };
    }

    const total = values.reduce((sum, value) => sum + value, 0);

    return {
        min: round(values.reduce((min, value) => Math.min(min, value), Number.POSITIVE_INFINITY)),
        avg: round(total / values.length),
        p50: round(percentile(values, 50)),
        p95: round(percentile(values, 95)),
        max: round(values.reduce((max, value) => Math.max(max, value), Number.NEGATIVE_INFINITY)),
    };
}

function round(value) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return null;
    }

    return Number.parseFloat(value.toFixed(2));
}

function bytesToGb(value) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return null;
    }

    return round(value / (1024 ** 3));
}

function bytesToMb(value) {
    if (value === null || value === undefined || Number.isNaN(value)) {
        return null;
    }

    return round(value / (1024 ** 2));
}

function readFileTrim(filePath) {
    try {
        return fs.readFileSync(filePath, 'utf8').trim();
    } catch (error) {
        return null;
    }
}

function parseCpuSetCount(value) {
    if (!value) {
        return null;
    }

    let count = 0;
    for (const section of value.split(',')) {
        const range = section.trim();
        if (!range) {
            continue;
        }

        const [startRaw, endRaw] = range.split('-');
        const start = Number.parseInt(startRaw, 10);
        const end = endRaw === undefined ? start : Number.parseInt(endRaw, 10);

        if (!Number.isInteger(start) || !Number.isInteger(end) || end < start) {
            return null;
        }

        count += (end - start) + 1;
    }

    return count > 0 ? count : null;
}

function detectBenchmarkEnvironment() {
    const hostCpuCount = typeof os.availableParallelism === 'function'
        ? os.availableParallelism()
        : os.cpus().length;
    const hostMemoryBytes = os.totalmem();

    const cgroup = {
        version: null,
        cpuLimit: null,
        memoryLimitBytes: null,
    };

    if (fs.existsSync('/sys/fs/cgroup/cgroup.controllers')) {
        cgroup.version = 'v2';

        const cpuMaxRaw = readFileTrim('/sys/fs/cgroup/cpu.max');
        if (cpuMaxRaw) {
            const [quotaRaw, periodRaw] = cpuMaxRaw.split(/\s+/);
            const quota = Number.parseInt(quotaRaw, 10);
            const period = Number.parseInt(periodRaw, 10);
            if (quotaRaw !== 'max' && Number.isFinite(quota) && Number.isFinite(period) && quota > 0 && period > 0) {
                cgroup.cpuLimit = round(quota / period);
            }
        }

        const cpusetRaw = readFileTrim('/sys/fs/cgroup/cpuset.cpus.effective') || readFileTrim('/sys/fs/cgroup/cpuset.cpus');
        const cpusetCount = parseCpuSetCount(cpusetRaw);
        if (cpusetCount !== null) {
            cgroup.cpuLimit = cgroup.cpuLimit === null
                ? cpusetCount
                : Math.min(cgroup.cpuLimit, cpusetCount);
        }

        const memoryRaw = readFileTrim('/sys/fs/cgroup/memory.max');
        if (memoryRaw && memoryRaw !== 'max') {
            const limit = Number.parseInt(memoryRaw, 10);
            if (Number.isFinite(limit) && limit > 0 && limit < (hostMemoryBytes * 8)) {
                cgroup.memoryLimitBytes = limit;
            }
        }
    } else {
        cgroup.version = 'v1';

        const quotaRaw = readFileTrim('/sys/fs/cgroup/cpu/cpu.cfs_quota_us');
        const periodRaw = readFileTrim('/sys/fs/cgroup/cpu/cpu.cfs_period_us');
        const quota = Number.parseInt(quotaRaw || '', 10);
        const period = Number.parseInt(periodRaw || '', 10);
        if (Number.isFinite(quota) && Number.isFinite(period) && quota > 0 && period > 0) {
            cgroup.cpuLimit = round(quota / period);
        }

        const cpusetRaw = readFileTrim('/sys/fs/cgroup/cpuset/cpuset.cpus');
        const cpusetCount = parseCpuSetCount(cpusetRaw);
        if (cpusetCount !== null) {
            cgroup.cpuLimit = cgroup.cpuLimit === null
                ? cpusetCount
                : Math.min(cgroup.cpuLimit, cpusetCount);
        }

        const memoryRaw = readFileTrim('/sys/fs/cgroup/memory/memory.limit_in_bytes');
        const limit = Number.parseInt(memoryRaw || '', 10);
        if (Number.isFinite(limit) && limit > 0 && limit < (hostMemoryBytes * 8)) {
            cgroup.memoryLimitBytes = limit;
        }
    }

    const effectiveCpuCount = cgroup.cpuLimit === null
        ? hostCpuCount
        : Math.min(hostCpuCount, cgroup.cpuLimit);
    const effectiveMemoryBytes = cgroup.memoryLimitBytes === null
        ? hostMemoryBytes
        : Math.min(hostMemoryBytes, cgroup.memoryLimitBytes);

    return {
        host: {
            cpuCount: hostCpuCount,
            memoryBytes: hostMemoryBytes,
            memoryGb: bytesToGb(hostMemoryBytes),
        },
        cgroup: {
            version: cgroup.version,
            cpuLimit: cgroup.cpuLimit,
            memoryLimitBytes: cgroup.memoryLimitBytes,
            memoryLimitGb: bytesToGb(cgroup.memoryLimitBytes),
        },
        effective: {
            source: cgroup.cpuLimit !== null || cgroup.memoryLimitBytes !== null ? `cgroup-${cgroup.version}` : 'host',
            cpuCount: round(effectiveCpuCount),
            memoryBytes: effectiveMemoryBytes,
            memoryGb: bytesToGb(effectiveMemoryBytes),
        },
    };
}

function readCpuStatSnapshot() {
    const raw = readFileTrim('/proc/stat');
    if (!raw) {
        return null;
    }

    const cpuLine = raw.split('\n').find((line) => line.startsWith('cpu '));
    if (!cpuLine) {
        return null;
    }

    const values = cpuLine
        .trim()
        .split(/\s+/)
        .slice(1)
        .map((value) => Number.parseInt(value, 10));
    if (!values.length || values.some((value) => Number.isNaN(value))) {
        return null;
    }

    const total = values.reduce((sum, value) => sum + value, 0);
    const idle = (values[3] || 0) + (values[4] || 0);

    return { total, idle };
}

function calculateCpuBusyPct(previousSnapshot, currentSnapshot) {
    if (!previousSnapshot || !currentSnapshot) {
        return null;
    }

    const totalDelta = currentSnapshot.total - previousSnapshot.total;
    const idleDelta = currentSnapshot.idle - previousSnapshot.idle;
    if (totalDelta <= 0) {
        return null;
    }

    return round(((totalDelta - idleDelta) / totalDelta) * 100);
}

function readMemorySnapshot() {
    const raw = readFileTrim('/proc/meminfo');
    if (!raw) {
        return null;
    }

    let totalKb = null;
    let availableKb = null;
    for (const line of raw.split('\n')) {
        if (line.startsWith('MemTotal:')) {
            totalKb = Number.parseInt(line.replace('MemTotal:', '').trim().split(/\s+/)[0], 10);
        }
        if (line.startsWith('MemAvailable:')) {
            availableKb = Number.parseInt(line.replace('MemAvailable:', '').trim().split(/\s+/)[0], 10);
        }
    }

    if (!Number.isFinite(totalKb) || !Number.isFinite(availableKb) || totalKb <= 0) {
        return null;
    }

    const usedBytes = Math.max(0, (totalKb - availableKb) * 1024);
    const totalBytes = totalKb * 1024;

    return {
        usedBytes,
        usedMb: bytesToMb(usedBytes),
        usedPct: round((usedBytes / totalBytes) * 100),
    };
}

function summarizeTelemetry(samples) {
    const cpuSamples = samples.map((sample) => sample.cpuBusyPct).filter((value) => value !== null);
    const memoryPctSamples = samples.map((sample) => sample.memoryUsedPct).filter((value) => value !== null);
    const memoryMbSamples = samples.map((sample) => sample.memoryUsedMb).filter((value) => value !== null);
    const loadSamples = samples.map((sample) => sample.load1m).filter((value) => value !== null);
    const baselineMemoryMb = memoryMbSamples.length ? memoryMbSamples[0] : null;
    const memoryDeltaSamples = baselineMemoryMb === null
        ? []
        : memoryMbSamples.map((value) => round(value - baselineMemoryMb));

    return {
        sampleCount: samples.length,
        cpuBusyPct: summarizeNumbers(cpuSamples),
        memoryUsedPct: summarizeNumbers(memoryPctSamples),
        memoryUsedMb: summarizeNumbers(memoryMbSamples),
        memoryDeltaMb: summarizeNumbers(memoryDeltaSamples),
        load1m: summarizeNumbers(loadSamples),
    };
}

async function runWithSystemTelemetry(operation, sampleIntervalMs) {
    const samples = [];
    let previousCpu = readCpuStatSnapshot();

    const sample = () => {
        const currentCpu = readCpuStatSnapshot();
        const memory = readMemorySnapshot();
        const load = os.loadavg();
        const cpuBusyPct = calculateCpuBusyPct(previousCpu, currentCpu);

        if (currentCpu) {
            previousCpu = currentCpu;
        }

        samples.push({
            cpuBusyPct,
            memoryUsedPct: memory ? memory.usedPct : null,
            memoryUsedMb: memory ? memory.usedMb : null,
            load1m: Number.isFinite(load[0]) ? round(load[0]) : null,
        });
    };

    const startedAt = new Date().toISOString();
    sample();
    const timer = setInterval(sample, sampleIntervalMs);
    timer.unref();

    try {
        const result = await operation();
        return {
            result,
            telemetry: {
                startedAt,
                endedAt: new Date().toISOString(),
                ...summarizeTelemetry(samples),
            },
        };
    } finally {
        clearInterval(timer);
    }
}

function classifySizingTier(maxHealthyConcurrency) {
    if (maxHealthyConcurrency >= 20) {
        return 'medium';
    }

    if (maxHealthyConcurrency >= 10) {
        return 'small';
    }

    if (maxHealthyConcurrency >= 5) {
        return 'pilot';
    }

    return 'insufficient-headroom';
}

function estimateCapacity(totalUnits, observedPct, targetPct) {
    if (
        totalUnits === null ||
        totalUnits === undefined ||
        observedPct === null ||
        observedPct === undefined ||
        targetPct === null ||
        targetPct === undefined ||
        observedPct <= 0 ||
        targetPct <= 0
    ) {
        return null;
    }

    return Math.max(1, Math.ceil((totalUnits * observedPct) / targetPct));
}

function mysqlExec(sql) {
    const args = [
        '-h',
        config.dbHost,
        `-u${config.dbUser}`,
        `-p${config.dbPass}`,
        '-N',
        '-e',
        sql,
    ];

    if (config.dbName) {
        args.push(config.dbName);
    }

    const result = spawnSync('mysql', args, {
        encoding: 'utf8',
    });

    if (result.status !== 0) {
        throw new Error((result.stderr || result.stdout || 'mysql command failed').trim());
    }

    return result.stdout.trim();
}

function mysqlExecOptional(sql) {
    try {
        return mysqlExec(sql);
    } catch (error) {
        return null;
    }
}

function parseSingleRow(raw, expectedColumns) {
    if (!raw) {
        return [];
    }

    const row = raw.split('\n')[0].split('\t');
    if (expectedColumns !== undefined && row.length < expectedColumns) {
        return [];
    }

    return row;
}

async function login(page) {
    await page.goto(`${config.baseUrl}/members/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email_address"]', config.loginEmail);
    await page.fill('input[name="password"]', config.loginPassword);
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle' }),
        page.click('button[type="submit"], input[type="submit"]'),
    ]);
}

async function collectNavigationTimings(page) {
    return page.evaluate(() => {
        const navigation = performance.getEntriesByType('navigation')[0];
        if (!navigation) {
            return {
                ttfbMs: null,
                domContentLoadedMs: null,
                loadMs: null,
                transferSizeBytes: null,
            };
        }

        return {
            ttfbMs: navigation.responseStart,
            domContentLoadedMs: navigation.domContentLoadedEventEnd,
            loadMs: navigation.loadEventEnd,
            transferSizeBytes: navigation.transferSize,
        };
    });
}

async function profileRoutesSequential(page, routes, runs) {
    const samplesByRoute = {};

    for (const route of routes) {
        const samples = [];
        for (let run = 1; run <= runs; run += 1) {
            const started = Date.now();
            const response = await page.goto(`${config.baseUrl}${route}`, { waitUntil: 'networkidle' });
            const elapsedMs = Date.now() - started;
            const timing = await collectNavigationTimings(page);

            samples.push({
                run,
                status: response ? response.status() : 0,
                elapsedMs,
                ttfbMs: round(timing.ttfbMs),
                domContentLoadedMs: round(timing.domContentLoadedMs),
                loadMs: round(timing.loadMs),
                transferSizeBytes: timing.transferSizeBytes,
            });
        }
        samplesByRoute[route] = samples;
    }

    const summary = routes.map((route) => {
        const routeSamples = samplesByRoute[route];
        const elapsed = summarizeNumbers(routeSamples.map((sample) => sample.elapsedMs));
        const ttfb = summarizeNumbers(routeSamples.map((sample) => sample.ttfbMs).filter((value) => value !== null));
        const dcl = summarizeNumbers(routeSamples.map((sample) => sample.domContentLoadedMs).filter((value) => value !== null));
        const statuses = [...new Set(routeSamples.map((sample) => sample.status))].sort((a, b) => a - b);

        return {
            route,
            statuses,
            elapsedMs: elapsed,
            ttfbMs: ttfb,
            domContentLoadedMs: dcl,
        };
    });

    return {
        runs,
        routes: samplesByRoute,
        summary,
    };
}

async function profileDbByRoute(page, routes) {
    if (!dbAvailable) {
        return {
            enabled: false,
            reason: 'MySQL profiling unavailable or disabled',
            summary: [],
        };
    }

    const results = [];

    for (const route of routes) {
        mysqlExec('TRUNCATE TABLE mysql.slow_log; SET GLOBAL log_output=\'TABLE\'; SET GLOBAL long_query_time=0; SET GLOBAL slow_query_log=ON;');
        const started = Date.now();
        const response = await page.goto(`${config.baseUrl}${route}`, { waitUntil: 'networkidle' });
        const elapsedMs = Date.now() - started;
        mysqlExec('SET GLOBAL slow_query_log=OFF;');

        const summaryRaw = mysqlExec(
            'SELECT COUNT(*), COALESCE(ROUND(SUM(TIME_TO_SEC(query_time))*1000,3),0), COALESCE(ROUND(MAX(TIME_TO_SEC(query_time))*1000,3),0), COALESCE(SUM(rows_examined),0) FROM mysql.slow_log;'
        );
        const [queryCountRaw, totalQueryMsRaw, maxQueryMsRaw, rowsExaminedRaw] = parseSingleRow(summaryRaw, 4);

        const topRaw = mysqlExecOptional(
            "SELECT ROUND(TIME_TO_SEC(query_time)*1000,3), rows_examined, rows_sent, LEFT(REPLACE(REPLACE(sql_text, '\\n', ' '), '\\t', ' '), 180) FROM mysql.slow_log ORDER BY rows_examined DESC, query_time DESC LIMIT 1;"
        );
        const [topQueryMsRaw, topRowsExaminedRaw, topRowsSentRaw, topSql] = parseSingleRow(topRaw || '', 4);

        results.push({
            route,
            status: response ? response.status() : 0,
            pageElapsedMs: elapsedMs,
            queryCount: Number.parseInt(queryCountRaw || '0', 10),
            totalQueryMs: Number.parseFloat(totalQueryMsRaw || '0'),
            maxQueryMs: Number.parseFloat(maxQueryMsRaw || '0'),
            rowsExamined: Number.parseInt(rowsExaminedRaw || '0', 10),
            topQuery: topSql ? {
                queryMs: Number.parseFloat(topQueryMsRaw || '0'),
                rowsExamined: Number.parseInt(topRowsExaminedRaw || '0', 10),
                rowsSent: Number.parseInt(topRowsSentRaw || '0', 10),
                sqlSnippet: topSql,
            } : null,
        });
    }

    return {
        enabled: true,
        summary: results,
    };
}

async function runUserFlow(browser, routes) {
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    const samples = [];

    try {
        await login(page);

        for (const route of routes) {
            const started = Date.now();
            try {
                const response = await page.goto(`${config.baseUrl}${route}`, { waitUntil: 'networkidle' });
                samples.push({
                    route,
                    status: response ? response.status() : 0,
                    elapsedMs: Date.now() - started,
                    error: null,
                });
            } catch (error) {
                samples.push({
                    route,
                    status: 0,
                    elapsedMs: Date.now() - started,
                    error: error.message,
                });
            }
        }
    } finally {
        await context.close();
    }

    return samples;
}

async function runConcurrencyScenario(browser, routes, concurrency) {
    const started = Date.now();
    const workerRuns = await Promise.all(
        Array.from({ length: concurrency }, () => runUserFlow(browser, routes))
    );
    const durationMs = Date.now() - started;

    const samples = workerRuns.flat();
    const successSamples = samples.filter((sample) => sample.status >= 200 && sample.status < 400);
    const errorSamples = samples.filter((sample) => !(sample.status >= 200 && sample.status < 400));
    const latencies = successSamples.map((sample) => sample.elapsedMs);
    const latencySummary = summarizeNumbers(latencies);
    const totalRequests = samples.length;
    const throughputRps = durationMs > 0 ? round(totalRequests / (durationMs / 1000)) : null;
    const errorRate = totalRequests > 0 ? round((errorSamples.length / totalRequests) * 100) : 0;

    return {
        concurrency,
        routeCountPerUser: routes.length,
        totalRequests,
        successfulRequests: successSamples.length,
        failedRequests: errorSamples.length,
        errorRatePct: errorRate,
        durationMs,
        throughputRps,
        latencyMs: latencySummary,
    };
}

function buildSizingRecommendation(
    concurrencyResults,
    slowThresholdMs,
    benchmarkEnvironment,
    cpuTargetUtilizationPct,
    memoryTargetUtilizationPct
) {
    const healthyLevels = concurrencyResults.filter((result) =>
        result.errorRatePct === 0 &&
        result.latencyMs.p95 !== null &&
        result.latencyMs.p95 <= slowThresholdMs
    );
    const maxHealthyLevel = healthyLevels.length ? healthyLevels[healthyLevels.length - 1] : null;
    const maxHealthyConcurrency = maxHealthyLevel ? maxHealthyLevel.concurrency : 0;
    const recommendedTier = classifySizingTier(maxHealthyConcurrency);
    const observedCpuP95 = maxHealthyLevel?.systemTelemetry?.cpuBusyPct?.p95 ?? null;
    const observedMemoryP95 = maxHealthyLevel?.systemTelemetry?.memoryUsedPct?.p95 ?? null;

    const benchmarkCpuCount = benchmarkEnvironment.host.cpuCount;
    const benchmarkMemoryGb = Math.max(1, Math.ceil(benchmarkEnvironment.host.memoryGb));
    const combinedCpuCapacity = estimateCapacity(benchmarkCpuCount, observedCpuP95, cpuTargetUtilizationPct) || benchmarkCpuCount;
    const combinedMemoryCapacityGb =
        estimateCapacity(benchmarkMemoryGb, observedMemoryP95, memoryTargetUtilizationPct) || benchmarkMemoryGb;

    const appCpu = Math.max(1, Math.ceil(combinedCpuCapacity * 0.5));
    const databaseCpu = Math.max(1, Math.ceil(combinedCpuCapacity * 0.5));
    const appMemoryGb = Math.max(2, Math.ceil(combinedMemoryCapacityGb * 0.4));
    const databaseMemoryGb = Math.max(2, Math.ceil(combinedMemoryCapacityGb * 0.6));

    let guidance = 'Current environment showed limited headroom. Tune hotspots and re-test before production rollout.';
    if (recommendedTier === 'medium') {
        guidance = 'Suitable for sustained multi-user back-office load. Keep monitoring p95 latency and host utilization.';
    } else if (recommendedTier === 'small') {
        guidance = 'Good starter production shape for light-to-moderate administrative load.';
    } else if (recommendedTier === 'pilot') {
        guidance = 'Use for pilot/limited rollout only; monitor closely before wider adoption.';
    }

    return {
        recommendedTier,
        maxHealthyConcurrency,
        app: `${appCpu} vCPU / ${appMemoryGb} GB RAM`,
        database: `${databaseCpu} vCPU / ${databaseMemoryGb} GB RAM`,
        guidance: `${guidance} Derived from measured benchmark-host telemetry.`,
        methodology: 'Latency/error gate plus host CPU+memory telemetry at the highest healthy concurrency level.',
        benchmarkHost: {
            cpuCount: benchmarkEnvironment.host.cpuCount,
            memoryGb: benchmarkEnvironment.host.memoryGb,
            cgroupCpuLimit: benchmarkEnvironment.cgroup.cpuLimit,
            cgroupMemoryLimitGb: benchmarkEnvironment.cgroup.memoryLimitGb,
            effectiveCpuCount: benchmarkEnvironment.effective.cpuCount,
            effectiveMemoryGb: benchmarkEnvironment.effective.memoryGb,
            effectiveSource: benchmarkEnvironment.effective.source,
        },
        utilizationTargets: {
            cpuPct: cpuTargetUtilizationPct,
            memoryPct: memoryTargetUtilizationPct,
        },
        observedAtMaxHealthy: {
            concurrency: maxHealthyConcurrency,
            cpuBusyP95Pct: observedCpuP95,
            memoryUsedP95Pct: observedMemoryP95,
        },
        derivedCapacityModel: {
            combinedCpuVcpu: combinedCpuCapacity,
            combinedMemoryGb: combinedMemoryCapacityGb,
            splitAssumption: 'App/DB split uses 50/50 CPU and 40/60 memory from combined benchmark-derived capacity.',
        },
    };
}

function detectPerformanceRisks(routeProfile, dbProfile, concurrencyResults, slowThresholdMs) {
    const routeRisks = routeProfile.summary
        .filter((entry) => entry.elapsedMs.p95 !== null && entry.elapsedMs.p95 > slowThresholdMs)
        .map((entry) => ({
            type: 'route-latency',
            route: entry.route,
            p95Ms: entry.elapsedMs.p95,
            thresholdMs: slowThresholdMs,
            message: `Route ${entry.route} p95 latency ${entry.elapsedMs.p95}ms exceeds ${slowThresholdMs}ms.`,
        }));

    const dbRisks = dbProfile.enabled
        ? dbProfile.summary
            .filter((entry) => entry.queryCount > 40 || entry.rowsExamined > 1000)
            .map((entry) => ({
                type: 'db-heavy-route',
                route: entry.route,
                queryCount: entry.queryCount,
                rowsExamined: entry.rowsExamined,
                message: `Route ${entry.route} is query-heavy (${entry.queryCount} queries, ${entry.rowsExamined} rows examined).`,
            }))
        : [];

    const concurrencyRisks = concurrencyResults
        .filter((result) => result.errorRatePct > 0 || (result.latencyMs.p95 !== null && result.latencyMs.p95 > slowThresholdMs))
        .map((result) => ({
            type: 'concurrency-pressure',
            concurrency: result.concurrency,
            errorRatePct: result.errorRatePct,
            p95Ms: result.latencyMs.p95,
            message: `Concurrency ${result.concurrency} showed pressure (errorRate=${result.errorRatePct}% p95=${result.latencyMs.p95}ms).`,
        }));

    return {
        routeRisks,
        dbRisks,
        concurrencyRisks,
    };
}

function printSummary(report, reportFile) {
    console.log('\nBenchmark host:');
    console.log(
        `host ${report.environment.host.cpuCount} vCPU / ${report.environment.host.memoryGb} GB RAM | ` +
        `effective ${report.environment.effective.cpuCount} vCPU / ${report.environment.effective.memoryGb} GB RAM (${report.environment.effective.source})`
    );

    console.log('\nRoute profile summary:');
    for (const entry of report.routeProfile.summary) {
        console.log(
            `${entry.route} | status ${entry.statuses.join('/')} | avg ${entry.elapsedMs.avg}ms | p95 ${entry.elapsedMs.p95}ms`
        );
    }

    console.log('\nConcurrency summary:');
    for (const result of report.concurrency) {
        const cpuP95 = result.systemTelemetry?.cpuBusyPct?.p95;
        const memoryP95 = result.systemTelemetry?.memoryUsedPct?.p95;
        const cpuLabel = cpuP95 === null || cpuP95 === undefined ? 'n/a' : `${cpuP95}%`;
        const memoryLabel = memoryP95 === null || memoryP95 === undefined ? 'n/a' : `${memoryP95}%`;
        console.log(
            `VU ${result.concurrency} | req ${result.totalRequests} | throughput ${result.throughputRps} req/s | ` +
            `p95 ${result.latencyMs.p95}ms | errors ${result.errorRatePct}% | hostCPU p95 ${cpuLabel} | hostMem p95 ${memoryLabel}`
        );
    }

    if (report.dbProfile.enabled) {
        console.log('\nDB profile summary:');
        for (const entry of report.dbProfile.summary) {
            console.log(
                `${entry.route} | queries ${entry.queryCount} | rows ${entry.rowsExamined} | maxQuery ${round(entry.maxQueryMs)}ms`
            );
        }
    } else {
        console.log('\nDB profile summary: disabled');
    }

    console.log('\nSizing recommendation:');
    console.log(
        `${report.recommendation.recommendedTier} | app ${report.recommendation.app} | db ${report.recommendation.database}`
    );
    console.log(`Guidance: ${report.recommendation.guidance}`);
    console.log(`Max healthy tested concurrency: ${report.recommendation.maxHealthyConcurrency}`);
    const observedCpu = report.recommendation.observedAtMaxHealthy.cpuBusyP95Pct;
    const observedMem = report.recommendation.observedAtMaxHealthy.memoryUsedP95Pct;
    const observedCpuLabel = observedCpu === null || observedCpu === undefined ? 'n/a' : `${observedCpu}%`;
    const observedMemLabel = observedMem === null || observedMem === undefined ? 'n/a' : `${observedMem}%`;
    console.log(
        `Observed host at max healthy: CPU p95 ${observedCpuLabel} | Mem p95 ${observedMemLabel}`
    );

    const riskCount =
        report.risks.routeRisks.length +
        report.risks.dbRisks.length +
        report.risks.concurrencyRisks.length;
    console.log(`\nPerformance risks detected: ${riskCount}`);
    console.log(`Report written: ${reportFile}`);
}

async function main() {
    fs.mkdirSync(config.outputDir, { recursive: true });
    const environment = detectBenchmarkEnvironment();

    if (config.enableDbProfile) {
        try {
            mysqlExec('SELECT 1;');
            const current = parseSingleRow(
                mysqlExec('SELECT @@GLOBAL.slow_query_log, @@GLOBAL.long_query_time, @@GLOBAL.log_output;'),
                3
            );
            if (current.length === 3) {
                originalDbSettings = {
                    slowQueryLog: current[0],
                    longQueryTime: current[1],
                    logOutput: current[2],
                };
                dbAvailable = true;
            }
        } catch (error) {
            dbAvailable = false;
            console.warn(`DB profiling disabled: ${error.message}`);
        }
    }

    const browser = await chromium.launch({ headless: true });
    let routeProfile;
    let dbProfile = {
        enabled: false,
        reason: 'Not collected',
        summary: [],
    };

    try {
        const context = await browser.newContext({ ignoreHTTPSErrors: true });
        const page = await context.newPage();
        await login(page);

        routeProfile = await profileRoutesSequential(page, profileRoutes, config.routeRuns);
        dbProfile = await profileDbByRoute(page, profileRoutes);
        await context.close();

        const concurrency = [];
        for (const level of config.concurrencyLevels) {
            const scenario = await runWithSystemTelemetry(
                () => runConcurrencyScenario(browser, flowRoutes, level),
                config.telemetrySampleMs
            );
            concurrency.push({
                ...scenario.result,
                systemTelemetry: scenario.telemetry,
            });
        }

        const recommendation = buildSizingRecommendation(
            concurrency,
            config.slowRequestMs,
            environment,
            config.cpuTargetUtilizationPct,
            config.memoryTargetUtilizationPct
        );
        const risks = detectPerformanceRisks(routeProfile, dbProfile, concurrency, config.slowRequestMs);

        const report = {
            generatedAt: new Date().toISOString(),
            config: {
                ...config,
                dbPass: config.dbPass ? '***' : '',
            },
            environment,
            routeProfile,
            dbProfile,
            concurrency,
            recommendation,
            risks,
        };

        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        const reportFile = path.join(config.outputDir, `sizing-report-${timestamp}.json`);
        fs.writeFileSync(reportFile, `${JSON.stringify(report, null, 2)}\n`, 'utf8');

        printSummary(report, reportFile);
    } finally {
        await browser.close();

        if (dbAvailable && originalDbSettings) {
            mysqlExec(
                `SET GLOBAL slow_query_log=${originalDbSettings.slowQueryLog}; ` +
                `SET GLOBAL long_query_time=${originalDbSettings.longQueryTime}; ` +
                `SET GLOBAL log_output='${originalDbSettings.logOutput}';`
            );
        }
    }
}

main().catch((error) => {
    console.error(`Benchmark failed: ${error.message}`);
    process.exit(1);
});
