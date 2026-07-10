const { expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');

const {
    getMailpitApiUrl,
    getUiTestEnvironment,
    getAppContainerName,
    getDbContainerName,
    getPostgresConfig,
    shouldUseDockerPhp,
    APP_ROOT,
} = require('./test-environment.cjs');

const CONTAINER_APP_DIR = '/var/www/html';

const DEFAULT_TIMEOUT = 15000;

const wait = (delayMs) => new Promise((resolve) => {
    setTimeout(resolve, delayMs);
});

const waitSync = (delayMs) => {
    Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, delayMs);
};

const waitForPageBody = async (page, timeout = DEFAULT_TIMEOUT) => {
    await expect(page.locator('body')).toBeVisible({ timeout });
};

const waitForSuccessfulLogin = async (page, timeout = DEFAULT_TIMEOUT) => {
    await expect(getSignOutButton(page)).toBeVisible({ timeout });
    await waitForPageBody(page, timeout);
};

const loginAs = async (page, emailAddress, password = 'TestPassword') => {
    await page.goto('/members/logout', { waitUntil: 'domcontentloaded' });
    await waitForPageBody(page);
    await page.goto('/members/login', { waitUntil: 'load' });
    await waitForPageBody(page);
    await page.locator('#email-address').fill(emailAddress);
    await page.locator('#password').fill(password);

    const submitButton = page.getByRole('button', { name: 'Sign in', exact: true });
    await submitButton.scrollIntoViewIfNeeded();
    const [loginResponse] = await Promise.all([
        page.waitForResponse((response) => {
            const request = response.request();

            return request.method() === 'POST' && new URL(response.url()).pathname === '/members/login';
        }, { timeout: 30000 }),
        submitButton.click({ noWaitAfter: true }),
    ]);
    if (![200, 302, 303].includes(loginResponse.status())) {
        throw new Error(`Login failed with HTTP ${loginResponse.status()}`);
    }
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await waitForSuccessfulLogin(page, 30000);
};

const clearMailpitMessages = async (requestContext) => {
    const response = await requestContext.delete(getMailpitApiUrl('api/v1/messages'));
    if (!response.ok() && response.status() !== 404) {
        throw new Error(`Mailpit cleanup failed with status ${response.status()}.`);
    }
};

/**
 * Run a `bin/cake` console command, transparently using `docker exec` against the
 * running app container when Playwright runs on the host, or `bin/cake` directly
 * when executing inside the container. Returns captured stdout.
 */
/**
 * Execute a PHP snippet against the application runtime and parse its JSON stdout.
 *
 * Execution model: on the host the script runs via `docker exec -i kmp-app php -r ...`
 * (the host PHP/`bin/cake` cannot resolve the docker-network `db` host); inside the
 * container it runs `php -r ...` directly. The JSON payload is piped on STDIN — never
 * passed via env var or argv — to avoid size limits, shell escaping, and leaking
 * fixture data into process metadata. The PHP snippet must read its input with
 * `stream_get_contents(STDIN)` and `echo` a single JSON document.
 *
 * @param {string} script Raw PHP (no opening tag) that echoes JSON.
 * @param {object} [payload] Data piped to the script on STDIN as JSON.
 * @param {{ timeoutMs?: number }} [options]
 * @returns {object} Parsed JSON, or {} when the script produced no output.
 */
const runPhpJson = (script, payload = {}, { timeoutMs = 60000 } = {}) => {
    const useDocker = shouldUseDockerPhp();
    const file = useDocker ? 'docker' : 'php';
    const phpArgs = ['-d', 'xdebug.mode=off', '-r', script];
    const args = useDocker
        ? ['exec', '-i', '-w', CONTAINER_APP_DIR, getAppContainerName(), 'php', ...phpArgs]
        : phpArgs;

    let output;
    try {
        output = execFileSync(file, args, {
            cwd: APP_ROOT,
            env: process.env,
            input: JSON.stringify(payload),
            stdio: ['pipe', 'pipe', 'pipe'],
            timeout: timeoutMs,
            maxBuffer: 32 * 1024 * 1024,
            encoding: 'utf8',
        }).trim();
    } catch (error) {
        const details = [error?.stdout, error?.stderr, error?.message]
            .filter(Boolean)
            .join('\n')
            .substring(0, 1000);
        throw new Error(`runPhpJson failed:\n${details}`);
    }

    if (output === '') {
        return {};
    }

    try {
        return JSON.parse(output);
    } catch (parseError) {
        throw new Error(`runPhpJson could not parse JSON output:\n${output.substring(0, 1000)}`);
    }
};

/**
 * Run a SQL statement against the Postgres test database and return raw stdout.
 *
 * On the host this uses `docker exec kmp-db psql` (no `-h`, local socket); inside the
 * container it uses `psql` with `PGPASSWORD` + `-h`. Intended for lightweight test
 * assertions and queue-settle polling, not application writes.
 *
 * @param {string} sql
 * @param {{ timeoutMs?: number }} [options]
 * @returns {string} Trimmed psql output (`-t -A`, so bare values).
 */
const dbQuery = (sql, { timeoutMs = 20000 } = {}) => {
    const useDocker = shouldUseDockerPhp();
    const pg = getPostgresConfig();
    const file = useDocker ? 'docker' : 'psql';
    const psqlArgs = ['-U', pg.user, '-d', pg.database, '-t', '-A', '-c', sql];
    const args = useDocker
        ? ['exec', getDbContainerName(), 'psql', ...psqlArgs]
        : ['-h', pg.host, '-p', String(pg.port), ...psqlArgs];

    return execFileSync(file, args, {
        cwd: APP_ROOT,
        env: useDocker ? process.env : { ...process.env, PGPASSWORD: pg.password },
        stdio: 'pipe',
        timeout: timeoutMs,
        encoding: 'utf8',
    }).trim();
};

const clearActivityAuthorizationFixtures = () => {
    dbQuery(`
        DELETE FROM workflow_approval_responses war
        USING workflow_approvals wa, workflow_instances wi, activities_authorizations az, members m, activities_activities act
        WHERE war.workflow_approval_id = wa.id
          AND wa.workflow_instance_id = wi.id
          AND wi.entity_type = 'Activities.Authorizations'
          AND wi.entity_id = az.id
          AND az.member_id = m.id
          AND az.activity_id = act.id
          AND m.email_address = 'iris@ampdemo.com'
          AND act.name = 'Armored';
        DELETE FROM workflow_approvals wa
        USING workflow_instances wi, activities_authorizations az, members m, activities_activities act
        WHERE wa.workflow_instance_id = wi.id
          AND wi.entity_type = 'Activities.Authorizations'
          AND wi.entity_id = az.id
          AND az.member_id = m.id
          AND az.activity_id = act.id
          AND m.email_address = 'iris@ampdemo.com'
          AND act.name = 'Armored';
        DELETE FROM workflow_instances wi
        USING activities_authorizations az, members m, activities_activities act
        WHERE wi.entity_type = 'Activities.Authorizations'
          AND wi.entity_id = az.id
          AND az.member_id = m.id
          AND az.activity_id = act.id
          AND m.email_address = 'iris@ampdemo.com'
          AND act.name = 'Armored';
        DELETE FROM activities_authorization_approvals aa
        USING activities_authorizations az, members m, activities_activities act
        WHERE aa.authorization_id = az.id
          AND az.member_id = m.id
          AND az.activity_id = act.id
          AND m.email_address = 'iris@ampdemo.com'
          AND act.name = 'Armored';
        DELETE FROM activities_authorizations az
        USING members m, activities_activities act
        WHERE az.member_id = m.id
          AND az.activity_id = act.id
          AND m.email_address = 'iris@ampdemo.com'
          AND act.name = 'Armored';
    `);
};

/**
 * Count queue jobs that are pending (enqueued, due now, not yet completed/failed-out).
 *
 * @returns {number}
 */
const countPendingQueueJobs = () => {
    const raw = dbQuery(
        "SELECT count(*) FROM queued_jobs WHERE completed IS NULL AND (notbefore IS NULL OR notbefore <= now());",
    ).trim();
    if (!/^\d+$/.test(raw)) {
        throw new Error(`countPendingQueueJobs got non-numeric output: ${JSON.stringify(raw).substring(0, 200)}`);
    }
    return Number.parseInt(raw, 10);
};

const drainPendingQueueJobs = () => {
    if (countPendingQueueJobs() === 0) {
        return;
    }

    try {
        runCakeCommand(['queue', 'run', '-q'], {
            env: {
                QUEUE_EXIT_WHEN_NOTHING_TO_DO: '1',
                QUEUE_MAX_WORKERS: '10',
            },
            timeoutMs: 120000,
        });
    } catch (error) {
        const message = error?.message || String(error);
        if (/Too many workers (running|already)/.test(message)) {
            return;
        }
        throw error;
    }
};

const waitForQueueSettledSync = ({ timeoutMs = 45000, pollMs = 1000 } = {}) => {
    const startedAt = Date.now();
    let consecutiveEmpty = 0;
    let lastPending = -1;
    let lastError = null;

    while (Date.now() - startedAt < timeoutMs) {
        try {
            lastPending = countPendingQueueJobs();
            lastError = null;
            if (lastPending > 0) {
                drainPendingQueueJobs();
                lastPending = countPendingQueueJobs();
            }
        } catch (error) {
            lastPending = -1;
            lastError = error;
        }

        if (lastPending === 0) {
            consecutiveEmpty += 1;
            if (consecutiveEmpty >= 2) {
                return true;
            }
        } else {
            consecutiveEmpty = 0;
        }

        waitSync(pollMs);
    }

    let diagnostics = `last pending count = ${lastPending}`;
    if (lastError) {
        diagnostics += `; last DB error: ${(lastError.message || String(lastError)).substring(0, 300)}`;
    } else {
        try {
            const rows = dbQuery(
                "SELECT id, job_task, attempts, failure_message FROM queued_jobs WHERE completed IS NULL ORDER BY created DESC LIMIT 10;",
            );
            diagnostics += `\nUncompleted jobs:\n${rows}`;
        } catch (error) {
            diagnostics += `\n(could not read uncompleted jobs: ${error.message})`;
        }
    }

    throw new Error(`waitForQueueSettledSync timed out after ${timeoutMs}ms — ${diagnostics}`);
};

/**
 * Block until the queue has no due, uncompleted jobs — the deterministic "settled"
 * primitive for trustworthy negative-email and async-side-effect assertions.
 *
 * `completed IS NULL` intentionally counts fetched-but-running (in-flight) jobs, so a
 * job the worker is mid-processing keeps the backlog > 0 until it truly finishes.
 * If a background worker is not currently running, the helper drains due jobs itself.
 *
 * Throws on timeout by default: silently continuing would let a stuck/failed worker turn
 * a "no email" negative assertion into a false pass. Pass `throwOnTimeout: false` only
 * for best-effort, non-asserting callers.
 *
 * @param {{ timeoutMs?: number, pollMs?: number, throwOnTimeout?: boolean }} [options]
 * @returns {Promise<boolean>} true if settled; false only when throwOnTimeout is false.
 */
const waitForQueueSettled = async ({ timeoutMs = 45000, pollMs = 1000, throwOnTimeout = true } = {}) => {
    const startedAt = Date.now();
    let consecutiveEmpty = 0;
    let lastPending = -1;
    let lastError = null;

    while (Date.now() - startedAt < timeoutMs) {
        try {
            lastPending = countPendingQueueJobs();
            lastError = null;
            if (lastPending > 0) {
                drainPendingQueueJobs();
                lastPending = countPendingQueueJobs();
            }
        } catch (error) {
            lastPending = -1;
            lastError = error;
        }

        if (lastPending === 0) {
            consecutiveEmpty += 1;
            if (consecutiveEmpty >= 2) {
                return true;
            }
        } else {
            consecutiveEmpty = 0;
        }

        await wait(pollMs);
    }

    let diagnostics = `last pending count = ${lastPending}`;
    if (lastError) {
        diagnostics += `; last DB error: ${(lastError.message || String(lastError)).substring(0, 300)}`;
    } else {
        try {
            const rows = dbQuery(
                "SELECT id, job_task, attempts, failure_message FROM queued_jobs WHERE completed IS NULL ORDER BY created DESC LIMIT 10;",
            );
            diagnostics += `\nUncompleted jobs:\n${rows}`;
        } catch (error) {
            diagnostics += `\n(could not read uncompleted jobs: ${error.message})`;
        }
    }

    if (throwOnTimeout) {
        throw new Error(`waitForQueueSettled timed out after ${timeoutMs}ms — ${diagnostics}`);
    }
    console.warn(`⚠️ waitForQueueSettled timed out — ${diagnostics}`);
    return false;
};

/**
 * Count uncompleted email-capable queue jobs (`job_task = 'Queue.Mailer'`),
 * optionally scoped to a fixture-unique substring (recipient address, template id,
 * subject fragment) found in the serialized job payload.
 *
 * Negative "no email" assertions MUST consider DELAYED jobs (`notbefore > now`):
 * waitForQueueSettled only drains DUE jobs, so a future-dated mailer job would
 * otherwise let a negative assertion false-pass. Keep includeDelayed=true (default)
 * for negatives; set false only when you specifically want due-now jobs.
 *
 * @param {{ match?: string|null, includeDelayed?: boolean }} [options]
 * @returns {number}
 */
const countUncompletedEmailJobs = ({ match = null, includeDelayed = true } = {}) => {
    const clauses = ['completed IS NULL', "job_task = 'Queue.Mailer'"];
    if (!includeDelayed) {
        clauses.push('(notbefore IS NULL OR notbefore <= now())');
    }
    if (match) {
        clauses.push(`data ILIKE '%${String(match).replace(/'/g, "''")}%'`);
    }
    const raw = dbQuery(`SELECT count(*) FROM queued_jobs WHERE ${clauses.join(' AND ')};`).trim();
    if (!/^\d+$/.test(raw)) {
        throw new Error(`countUncompletedEmailJobs got non-numeric output: ${JSON.stringify(raw).substring(0, 200)}`);
    }
    return Number.parseInt(raw, 10);
};

/**
 * Assert that NO email-capable job (due OR delayed) remains queued for a
 * fixture-unique token. This is the queue-side half of a trustworthy negative
 * email assertion; pair it with a fixture-scoped Mailpit "no message" check.
 * Run AFTER waitForQueueSettled so any legitimately-due mail has been delivered.
 *
 * @param {string} match fixture-unique substring (recipient email, template id, …)
 */
const assertNoQueuedEmailFor = (match) => {
    if (!match) {
        throw new Error('assertNoQueuedEmailFor requires a fixture-unique match token');
    }
    const count = countUncompletedEmailJobs({ match, includeDelayed: true });
    if (count > 0) {
        const escaped = String(match).replace(/'/g, "''");
        const rows = dbQuery(
            `SELECT id, notbefore, substring(data for 200) FROM queued_jobs `
            + `WHERE completed IS NULL AND job_task = 'Queue.Mailer' AND data ILIKE '%${escaped}%' LIMIT 5;`,
        );
        throw new Error(`Expected no queued email for "${match}", found ${count} uncompleted (due or delayed):\n${rows}`);
    }
};

const runCakeCommand = (cakeArgs, { env = {}, timeoutMs = 60000 } = {}) => {
    const useDocker = shouldUseDockerPhp();
    const file = useDocker ? 'docker' : 'bash';
    const dockerEnvArgs = Object.entries(env).flatMap(([key, value]) => ['-e', `${key}=${value}`]);
    const args = useDocker
        ? ['exec', ...dockerEnvArgs, getAppContainerName(), 'bin/cake', ...cakeArgs]
        : [`${APP_ROOT}/bin/cake`, ...cakeArgs];

    return execFileSync(file, args, {
        cwd: APP_ROOT,
        env: { ...process.env, ...env },
        stdio: 'pipe',
        timeout: timeoutMs,
        encoding: 'utf8',
    });
};

/**
 * Deterministically settle pending workflow/queue side-effects before asserting.
 *
 * Architecture: trigger-driven workflows execute synchronously during the web request
 * and enqueue their email jobs. Queue draining is handled by waitForQueueSettled(), which
 * only runs the queue command when due jobs already exist so it cannot hang on an empty
 * queue.
 *
 * When `forceScheduler` is set we dispatch time-based scheduled workflows now via
 * `workflow_scheduler --force` (fast, ~1s); the worker then drains anything it enqueued.
 *
 * @param {{ forceScheduler?: boolean }} [options]
 */
const flushWorkflowsAndQueue = ({ forceScheduler = false } = {}) => {
    if (forceScheduler) {
        // Deterministic test helper: a scheduler failure must surface, not be swallowed,
        // or scheduled workflows could silently never enqueue and turn negative email
        // assertions into false passes.
        runCakeCommand(['workflow_scheduler', '--force', '-q'], { timeoutMs: 30000 });
    }

    waitForQueueSettledSync();
};

/**
 * Poll the Mailpit search API and return the total number of messages matching a query.
 */
const mailpitSearchTotal = async (requestContext, query) => {
    const response = await requestContext.get(getMailpitApiUrl('api/v1/search'), {
        params: { query },
    });
    if (!response.ok()) {
        return 0;
    }
    const data = await response.json();
    // `total` is the whole-mailbox count; `messages_count` is the number of
    // messages matching the search query. Assertions want the match count.
    return data.messages_count ?? 0;
};

/**
 * Poll Mailpit until the matching-message count is stable across a short quiet
 * window. Use this after waitForQueueSettled for negative assertions so a
 * just-delivered email cannot race a single zero-count read.
 *
 * @param {import('@playwright/test').APIRequestContext} requestContext
 * @param {string} query Mailpit search query.
 * @param {{ polls?: number, pollMs?: number }} [options]
 * @returns {Promise<number>} Stable matching-message count.
 */
const waitForStableMailpitSearchTotal = async (
    requestContext,
    query,
    { polls = 3, pollMs = 500 } = {},
) => {
    let lastTotal = null;
    let stableReads = 0;

    while (stableReads < polls) {
        const total = await mailpitSearchTotal(requestContext, query);
        if (total === lastTotal) {
            stableReads += 1;
        } else {
            lastTotal = total;
            stableReads = 1;
        }

        if (stableReads < polls) {
            await wait(pollMs);
        }
    }

    return lastTotal;
};

const waitForAppReady = async (requestContext, timeout = 60000) => {
    const { baseUrl, hostHeader } = getUiTestEnvironment();
    const startedAt = Date.now();
    let lastErrorMessage = 'no response received';
    const requestOptions = {
        failOnStatusCode: false,
        timeout: 5000,
        headers: hostHeader ? { Host: hostHeader } : undefined,
    };

    while (Date.now() - startedAt < timeout) {
        try {
            const response = await requestContext.get(baseUrl, {
                ...requestOptions,
                maxRedirects: 0,
            });

            if (response.ok() || [301, 302, 303, 307, 308, 401, 403].includes(response.status())) {
                return;
            }

            lastErrorMessage = `HTTP ${response.status()}`;
        } catch (error) {
            lastErrorMessage = error.message;
        }

        await wait(1000);
    }

    throw new Error(`Timed out waiting for ${baseUrl}: ${lastErrorMessage}`);
};

const runAndWaitForNetworkIdle = async (page, action, timeout = DEFAULT_TIMEOUT) => {
    await Promise.all([
        page.waitForLoadState('networkidle', { timeout }),
        Promise.resolve().then(action),
    ]);
    await waitForPageBody(page, timeout);
};

const waitForGridRows = async (
    scope,
    selector = 'table.table tbody tr:visible, .dataTable tbody tr:visible, table tbody tr:visible',
    timeout = 30000,
) => {
    const rows = scope.locator(selector);
    await expect.poll(async () => {
        const count = await rows.count();
        for (let index = 0; index < count; index += 1) {
            if (await rows.nth(index).isVisible()) {
                return true;
            }
        }

        return false;
    }, { timeout }).toBe(true);

    return rows;
};

const clickTabAndWait = async (tab, panel = null, timeout = DEFAULT_TIMEOUT) => {
    await tab.click();
    await expect(tab).toHaveClass(/active/, { timeout });
    if (panel) {
        await expect(panel).toBeVisible({ timeout });
    }
};

const getSignOutButton = (page) => page.locator('a.btn-outline-secondary').filter({ hasText: /Sign out/i }).first();

const isLocatorVisible = async (locator) => {
    try {
        return await locator.isVisible();
    } catch {
        return false;
    }
};

const waitForTurboFrame = async (page, frameId, timeout = 30000) => {
    const frame = page.locator(`turbo-frame#${frameId}`);
    await expect(frame).toBeVisible({ timeout });
    return frame;
};

const waitForTurboStreamResponse = (page, action) => Promise.all([
    page.waitForResponse(
        (response) => response.headers()['content-type']?.includes('turbo-stream') && response.ok(),
        { timeout: 30000 },
    ),
    action(),
]);

const assertUrlContainsQuery = async (page, fragment) => {
    await expect(page).toHaveURL(new RegExp(fragment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
};

const waitForGridStateJson = async (page, frameId, timeout = 30000) => {
    const frame = page.locator(`turbo-frame#${frameId}`);
    await expect(frame).toBeVisible({ timeout });
    const stateScript = frame.locator('script[type="application/json"]');
    await expect(stateScript).toBeAttached({ timeout });
    const raw = await stateScript.textContent();
    return JSON.parse(raw || '{}');
};

/**
 * Assert the grid Stimulus shell stayed on the page (no full document navigation).
 */
const assertGridShellPreserved = async (page, selector = '[data-controller*="grid-view"]') => {
    const gridShell = page.locator(selector).first();
    await expect(gridShell).toBeVisible({ timeout: 15000 });
    const navType = await page.evaluate(() => {
        const entry = performance.getEntriesByType('navigation')[0];
        return entry ? entry.type : 'navigate';
    });
    expect(navType).not.toBe('reload');
};

module.exports = {
    assertGridShellPreserved,
    assertNoQueuedEmailFor,
    assertUrlContainsQuery,
    clearActivityAuthorizationFixtures,
    clearMailpitMessages,
    clickTabAndWait,
    countPendingQueueJobs,
    countUncompletedEmailJobs,
    dbQuery,
    flushWorkflowsAndQueue,
    getSignOutButton,
    isLocatorVisible,
    loginAs,
    mailpitSearchTotal,
    runAndWaitForNetworkIdle,
    runCakeCommand,
    runPhpJson,
    waitForAppReady,
    waitForGridRows,
    waitForPageBody,
    waitForQueueSettled,
    waitForStableMailpitSearchTotal,
    waitForSuccessfulLogin,
    waitForTurboFrame,
    waitForTurboStreamResponse,
    waitForGridStateJson,
};
