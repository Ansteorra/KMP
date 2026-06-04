// Global setup for Playwright tests
const { execFileSync } = require('node:child_process');

const { request } = require('@playwright/test');

const {
  getUiTestEnvironment,
  getPostgresConfig,
  getDbContainerName,
  shouldUseDockerPhp,
} = require('./support/test-environment.cjs');
const { clearMailpitMessages, runPhpJson, waitForAppReady } = require('./support/ui-helpers.cjs');

const escapeSqlString = (value) => value.replace(/'/g, "''");

const buildAuthCleanupSql = (environment) => {
  const email = escapeSqlString(environment.cleanupMemberEmail);
  const activity = escapeSqlString(environment.cleanupActivityName);

  return [
    'DELETE FROM activities_authorization_approvals aa',
    'USING activities_authorizations az, members m, activities_activities act',
    'WHERE aa.authorization_id = az.id',
    'AND az.member_id = m.id',
    'AND az.activity_id = act.id',
    `AND m.email_address = '${email}'`,
    `AND act.name = '${activity}';`,
    'DELETE FROM activities_authorizations az',
    'USING members m, activities_activities act',
    'WHERE az.member_id = m.id',
    'AND az.activity_id = act.id',
    `AND m.email_address = '${email}'`,
    `AND act.name = '${activity}';`,
  ].join(' ');
};

/**
 * Clean residual activities-authorization rows for the test fixture member so reruns
 * start from a known state. Postgres-aware: execs `psql` inside the DB container when
 * Playwright runs on the host, otherwise connects directly. Non-fatal by design — the
 * per-run DB reset is the authoritative clean baseline.
 */
const cleanupAuthRequests = (environment) => {
  const pg = getPostgresConfig();
  const sql = buildAuthCleanupSql(environment);

  if (shouldUseDockerPhp()) {
    execFileSync('docker', [
      'exec',
      '-e', `PGPASSWORD=${pg.password}`,
      getDbContainerName(),
      'psql',
      '-U', pg.user,
      '-d', pg.database,
      '-v', 'ON_ERROR_STOP=1',
      '-c', sql,
    ], { stdio: 'pipe' });
    return;
  }

  execFileSync('psql', [
    '-h', pg.host,
    '-p', String(pg.port),
    '-U', pg.user,
    '-d', pg.database,
    '-v', 'ON_ERROR_STOP=1',
    '-c', sql,
  ], { stdio: 'pipe', env: { ...process.env, PGPASSWORD: pg.password } });
};

const ensureRequiredAppSettings = () => {
  runPhpJson(String.raw`
require 'vendor/autoload.php';
require 'config/bootstrap.php';

\App\KMP\StaticHelpers::setAppSetting(
    'Members.AccountVerificationContactEmail',
    'amp-secretary@webminister.ansteorra.org',
    'string',
    true
);

echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
`);
};

async function globalSetup() {
  console.log('🚀 Starting global setup for UI tests...');
  const environment = getUiTestEnvironment();
  const apiContext = await request.newContext({
    baseURL: environment.baseUrl,
    ignoreHTTPSErrors: true,
    extraHTTPHeaders: environment.hostHeader ? { Host: environment.hostHeader } : undefined,
  });

  // empty the test mail server inbox
  console.log('🧹 Emptying test mail server inbox...');
  try {
    await clearMailpitMessages(apiContext);
    console.log('✅ Test mail server inbox emptied');
  } catch (error) {
    console.error('❌ Failed to empty test mail server inbox:', error);
  }

  // Clean up authorization requests for test activities to avoid conflicts
  console.log('🧹 Cleaning up auth requests for test user...');
  try {
    ensureRequiredAppSettings();
    cleanupAuthRequests(environment);
    console.log('✅ Auth requests cleaned up');
  } catch (error) {
    console.log('⚠️ Could not clean up auths (non-fatal):', error.message?.substring(0, 100));
  }

  try {
    // Wait for the server to be ready
    console.log('⏳ Waiting for server to be ready...');
    await waitForAppReady(apiContext);
    console.log('✅ Server is ready');
  } catch (error) {
    console.error('❌ Global setup failed:', error);
    throw error;
  } finally {
    await apiContext.dispose();
  }

  console.log('✅ Global setup completed');
}

module.exports = globalSetup;
