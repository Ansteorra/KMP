const fs = require('node:fs');
const path = require('node:path');

const APP_ROOT = path.resolve(__dirname, '../../..');
const REPO_ROOT = path.resolve(APP_ROOT, '..');
const CONFIG_ENV_PATH = path.join(APP_ROOT, 'config', '.env');

let cachedDotEnv;

const parseDotEnvValue = (rawValue = '') => {
    const value = rawValue.trim();
    if (!value) {
        return '';
    }

    const quote = value[0];
    if ((quote === '"' || quote === '\'') && value.endsWith(quote)) {
        return value.slice(1, -1);
    }

    return value.replace(/\s+#.*$/, '');
};

const loadDotEnvFile = () => {
    if (cachedDotEnv !== undefined) {
        return cachedDotEnv;
    }

    cachedDotEnv = {};
    if (!fs.existsSync(CONFIG_ENV_PATH)) {
        return cachedDotEnv;
    }

    for (const line of fs.readFileSync(CONFIG_ENV_PATH, 'utf8').split(/\r?\n/)) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) {
            continue;
        }

        const separatorIndex = trimmed.indexOf('=');
        if (separatorIndex === -1) {
            continue;
        }

        const key = trimmed.slice(0, separatorIndex).trim().replace(/^export\s+/, '');
        if (!key) {
            continue;
        }

        cachedDotEnv[key] = parseDotEnvValue(trimmed.slice(separatorIndex + 1));
    }

    return cachedDotEnv;
};

const getEnvValue = (...keys) => {
    const dotEnv = loadDotEnvFile();

    for (const key of keys) {
        const value = process.env[key] ?? dotEnv[key];
        if (value !== undefined && value !== '') {
            return value;
        }
    }

    return undefined;
};

const trimTrailingSlash = (value) => value ? value.replace(/\/+$/, '') : value;

const parseMysqlUrl = (databaseUrl) => {
    if (!databaseUrl) {
        return null;
    }

    try {
        const parsed = new URL(databaseUrl);
        if (!parsed.protocol.startsWith('mysql')) {
            return null;
        }

        return {
            host: parsed.hostname || '127.0.0.1',
            port: parsed.port || '3306',
            user: decodeURIComponent(parsed.username || ''),
            password: decodeURIComponent(parsed.password || ''),
            database: decodeURIComponent(parsed.pathname.replace(/^\//, '')),
        };
    } catch {
        return null;
    }
};

const getMysqlConfig = () => parseMysqlUrl(getEnvValue('DATABASE_URL')) ?? {
    host: getEnvValue('DB_HOST', 'MYSQL_HOST') || '127.0.0.1',
    port: getEnvValue('DB_PORT', 'MYSQL_PORT') || '3306',
    user: getEnvValue('DB_USERNAME', 'MYSQL_USERNAME') || 'root',
    password: getEnvValue('DB_PASSWORD', 'MYSQL_PASSWORD') || '',
    database: getEnvValue('DB_DATABASE', 'MYSQL_DB_NAME') || 'kmp',
};

const parsePostgresUrl = (databaseUrl) => {
    if (!databaseUrl) {
        return null;
    }

    try {
        const parsed = new URL(databaseUrl);
        if (!parsed.protocol.startsWith('postgres')) {
            return null;
        }

        return {
            host: parsed.hostname || '127.0.0.1',
            port: parsed.port || '5432',
            user: decodeURIComponent(parsed.username || ''),
            password: decodeURIComponent(parsed.password || ''),
            database: decodeURIComponent(parsed.pathname.replace(/^\//, '')),
        };
    } catch {
        return null;
    }
};

const getPostgresConfig = () => parsePostgresUrl(getEnvValue('DATABASE_URL')) ?? {
    host: getEnvValue('DB_HOST', 'POSTGRES_HOST') || '127.0.0.1',
    port: getEnvValue('DB_PORT', 'POSTGRES_PORT') || '5432',
    user: getEnvValue('DB_USERNAME', 'POSTGRES_USER') || 'postgres',
    password: getEnvValue('DB_PASSWORD', 'POSTGRES_PASSWORD') || '',
    database: getEnvValue('DB_DATABASE', 'POSTGRES_DB') || 'KMP_DEV',
};

/**
 * Name of the running Docker container hosting the Cake app (for `docker exec`).
 */
const getAppContainerName = () => getEnvValue('PLAYWRIGHT_APP_CONTAINER') || 'kmp-app';

/**
 * Name of the running Docker container hosting Postgres (for `docker exec` psql cleanup).
 */
const getDbContainerName = () => getEnvValue('PLAYWRIGHT_DB_CONTAINER') || 'kmp-db';

const DEFAULT_PLAYWRIGHT_HOST_HEADER = 'kmp.localhost';

/**
 * Host: use kmp.localhost:8080 (requires /etc/hosts or KMP_HOST_ALIASES).
 * In-container: loopback :80 with Host header (Apache vhost).
 */
const defaultPlaywrightBaseUrl = () => {
    if (fs.existsSync('/.dockerenv')) {
        return 'http://127.0.0.1';
    }

    return 'http://kmp.localhost:8080';
};

const resolveHostHeader = (baseUrl) => {
    const explicit = getEnvValue('PLAYWRIGHT_HOST_HEADER');
    if (explicit !== undefined) {
        return explicit || null;
    }

    try {
        const hostname = new URL(baseUrl).hostname;
        if (hostname === '127.0.0.1' || hostname === 'localhost') {
            return DEFAULT_PLAYWRIGHT_HOST_HEADER;
        }

        return null;
    } catch {
        return DEFAULT_PLAYWRIGHT_HOST_HEADER;
    }
};

const getUiTestEnvironment = () => {
    const baseUrl = trimTrailingSlash(getEnvValue('PLAYWRIGHT_BASE_URL')) || defaultPlaywrightBaseUrl();

    return {
    baseUrl,
    hostHeader: resolveHostHeader(baseUrl),
    mailpitUrl: trimTrailingSlash(
        getEnvValue('PLAYWRIGHT_MAILPIT_URL', 'MAILPIT_BASE_URL', 'MAILPIT_URL'),
    ) || 'http://127.0.0.1:8025',
    webServerCommand: getEnvValue('PLAYWRIGHT_WEB_SERVER_COMMAND') || 'bash ../dev-up.sh',
    cleanupMemberEmail: getEnvValue('PLAYWRIGHT_ACTIVITY_CLEANUP_EMAIL') || 'iris@ampdemo.com',
    cleanupActivityName: getEnvValue('PLAYWRIGHT_ACTIVITY_CLEANUP_NAME') || 'Armored',
    mysql: getMysqlConfig(),
    };
};

const getMailpitApiUrl = (pathname = '') => new URL(
    pathname.replace(/^\//, ''),
    `${getUiTestEnvironment().mailpitUrl}/`,
).toString();

const DEFAULT_TENANT = 'kmp';

/**
 * Resolve the per-tenant base URL + Host header for a given tenant slug.
 *
 * Two tenants are seeded (`kmp.localhost`, `kmp2.localhost`); the active tenant is
 * selected by the HTTP Host header. When the base URL targets loopback we keep the
 * URL and override the Host header; otherwise we swap the hostname to `<tenant>.localhost`.
 */
const getTenantContextOptions = (tenant = DEFAULT_TENANT) => {
    const slug = (tenant || DEFAULT_TENANT).toString().trim().toLowerCase();
    const tenantHost = `${slug}.localhost`;
    const { baseUrl, hostHeader } = getUiTestEnvironment();

    if (hostHeader) {
        return { baseURL: baseUrl, hostHeader: tenantHost };
    }

    try {
        const url = new URL(baseUrl);
        url.hostname = tenantHost;
        return { baseURL: trimTrailingSlash(url.toString()), hostHeader: null };
    } catch {
        return { baseURL: baseUrl, hostHeader: tenantHost };
    }
};

/**
 * Run fixture PHP via `docker compose exec app` when tests run on the host against Docker DB.
 */
const shouldUseDockerPhp = () => {
    const flag = getEnvValue('PLAYWRIGHT_USE_DOCKER_PHP');
    if (flag === '1') {
        return true;
    }
    if (flag === '0') {
        return false;
    }

    return !fs.existsSync('/.dockerenv');
};

module.exports = {
    APP_ROOT,
    REPO_ROOT,
    CONFIG_ENV_PATH,
    getAppContainerName,
    getDbContainerName,
    getMailpitApiUrl,
    getPostgresConfig,
    getTenantContextOptions,
    getUiTestEnvironment,
    shouldUseDockerPhp,
};
