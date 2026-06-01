// Global setup for Playwright tests
const { execFileSync } = require('node:child_process');

const { request } = require('@playwright/test');

const { getUiTestEnvironment } = require('./support/test-environment.cjs');
const { clearMailpitMessages, waitForAppReady } = require('./support/ui-helpers.cjs');

const escapeSqlString = (value) => value.replace(/'/g, "''");

const buildMysqlArgs = (environment) => {
  const mysqlArgs = [
    '-h',
    environment.mysql.host,
    '-P',
    String(environment.mysql.port),
    '-u',
    environment.mysql.user,
  ];

  if (environment.mysql.password) {
    mysqlArgs.push(`-p${environment.mysql.password}`);
  }

  mysqlArgs.push(
    environment.mysql.database,
    '-e',
    [
      'DELETE aa FROM activities_authorization_approvals aa',
      'JOIN activities_authorizations az ON aa.authorization_id = az.id',
      'JOIN members m ON az.member_id = m.id',
      'JOIN activities_activities act ON az.activity_id = act.id',
      `WHERE m.email_address = '${escapeSqlString(environment.cleanupMemberEmail)}'`,
      `AND act.name = '${escapeSqlString(environment.cleanupActivityName)}';`,
      'DELETE az FROM activities_authorizations az',
      'JOIN members m ON az.member_id = m.id',
      'JOIN activities_activities act ON az.activity_id = act.id',
      `WHERE m.email_address = '${escapeSqlString(environment.cleanupMemberEmail)}'`,
      `AND act.name = '${escapeSqlString(environment.cleanupActivityName)}';`,
    ].join(' '),
  );

  return mysqlArgs;
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
    execFileSync('mysql', buildMysqlArgs(environment), { stdio: 'pipe' });
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
