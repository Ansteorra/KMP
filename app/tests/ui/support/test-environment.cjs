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

const getUiTestEnvironment = () => ({
    baseUrl: trimTrailingSlash(getEnvValue('PLAYWRIGHT_BASE_URL')) || 'http://127.0.0.1:8080',
    hostHeader: getEnvValue('PLAYWRIGHT_HOST_HEADER') || null,
    mailpitUrl: trimTrailingSlash(
        getEnvValue('PLAYWRIGHT_MAILPIT_URL', 'MAILPIT_BASE_URL', 'MAILPIT_URL'),
    ) || 'http://127.0.0.1:8025',
    webServerCommand: getEnvValue('PLAYWRIGHT_WEB_SERVER_COMMAND') || 'bash ../dev-up.sh',
    cleanupMemberEmail: getEnvValue('PLAYWRIGHT_ACTIVITY_CLEANUP_EMAIL') || 'iris@ampdemo.com',
    cleanupActivityName: getEnvValue('PLAYWRIGHT_ACTIVITY_CLEANUP_NAME') || 'Armored',
    mysql: getMysqlConfig(),
});

const getMailpitApiUrl = (pathname = '') => new URL(
    pathname.replace(/^\//, ''),
    `${getUiTestEnvironment().mailpitUrl}/`,
).toString();

module.exports = {
    APP_ROOT,
    REPO_ROOT,
    CONFIG_ENV_PATH,
    getMailpitApiUrl,
    getUiTestEnvironment,
};
