<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.0.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */

use App\Test\TestCase\Support\SeedManager;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Migrations\Migrations;

/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

// Some local/dev environments run tests as a different OS user than web runtime.
// Fall back to system temp paths when app tmp/logs aren't writable.
$projectRoot = dirname(__DIR__);
$runtimeRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kmp-tests' . DIRECTORY_SEPARATOR;
$runtimeTmp = $runtimeRoot . 'tmp' . DIRECTORY_SEPARATOR;
$runtimeLogs = $runtimeRoot . 'logs' . DIRECTORY_SEPARATOR;

if (!defined('TMP') && !is_writable($projectRoot . DIRECTORY_SEPARATOR . 'tmp')) {
    @mkdir($runtimeTmp, 0777, true);
    define('TMP', $runtimeTmp);
}

if (!defined('LOGS') && !is_writable($projectRoot . DIRECTORY_SEPARATOR . 'logs')) {
    @mkdir($runtimeLogs, 0777, true);
    define('LOGS', $runtimeLogs);
}

require dirname(__DIR__) . '/config/bootstrap.php';

if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

// Keep document storage under writable test temp path.
$testDocumentPath = TMP . 'uploaded';
@mkdir($testDocumentPath, 0777, true);
Configure::write('Documents.storage.local.path', $testDocumentPath);

// DebugKit skips settings these connection config if PHP SAPI is CLI / PHPDBG.
// But since PagesControllerTest is run with debug enabled and DebugKit is loaded
// in application, without setting up these config DebugKit errors out.
ConnectionManager::setConfig('test_debug_kit', [
    'className' => "Cake\Database\Connection",
    'driver' => "Cake\Database\Driver\Sqlite",
    'database' => TMP . 'debug_kit.sqlite',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

ConnectionManager::alias('test_debug_kit', 'debug_kit');
ConnectionManager::alias('test', 'default');

// Ensure tmp and logs directories exist and are writable for tests
@mkdir(LOGS, 0777, true);
@mkdir(TMP, 0777, true);
@mkdir(TMP . 'cache', 0777, true);
@mkdir(TMP . 'sessions', 0777, true);
@mkdir(TMP . 'tests', 0777, true);

// Fixate sessionid early on, as php7.2+
// does not allow the sessionid to be set after stdout
// has been written to.
if (session_status() === PHP_SESSION_NONE) {
    session_id('cli');
}

// Ensure the test schema is seeded with the shared dev dataset
SeedManager::bootstrap('test');

// Apply migrations after seeding so test schema includes recent columns.
(new Migrations())->migrate(['connection' => 'test']);

// On Postgres (no MySQL seed dump), we also need to run plugin migrations
// to create all plugin tables from scratch, and seed essential AppSettings.
if (SeedManager::isPostgres('test')) {
    foreach (['Queue', 'Officers', 'Activities', 'Awards', 'Waivers'] as $plugin) {
        (new Migrations())->migrate(['connection' => 'test', 'plugin' => $plugin]);
    }

    // Seed essential AppSettings that tests expect
    $conn = ConnectionManager::get('test');
    $now = date('Y-m-d H:i:s');
    $settings = [
        ['KMP.KingdomName', 'Test Kingdom'],
        ['KMP.ShortSiteTitle', 'KMP Test'],
        ['KMP.LongSiteTitle', 'KMP Test Environment'],
        ['KMP.RequireActiveWarrantForSecurity', 'false'],
        ['Warrant.LastCheck', $now],
    ];
    foreach ($settings as [$name, $value]) {
        try {
            $conn->execute(
                "INSERT INTO app_settings (name, value, created, modified) VALUES (?, ?, ?, ?)",
                [$name, $value, $now, $now]
            );
        } catch (\Exception $e) {
            // Setting may already exist from migration seed
        }
    }

    // Seed DB-agnostic authorization edge-case records used by service tests.
    $members = [
        [1, 'Admin von Admin', 'Admin', 'von', 'admin@test.com', 'verified', '2100-01-01', true, 1980],
        [2871, 'Agatha Local MoAS Demoer', 'Agatha', 'Demoer', 'agatha@ampdemo.com', 'verified', '2100-01-01', true, 2000],
        [2872, 'Bryce Local Seneschal Demoer', 'Bryce', 'Demoer', 'bryce@ampdemo.com', 'verified', '2100-01-01', true, 2001],
        [2874, 'Devon Regional Armored Demoer', 'Devon', 'Demoer', 'devon@ampdemo.com', 'verified', '2100-01-01', true, 2002],
        [2875, 'Eirik Kingdom Seneschal Demoer', 'Eirik', 'Demoer', 'eirik@ampdemo.com', 'verified', '2100-01-01', true, 2004],
    ];
    foreach ($members as [$id, $scaName, $firstName, $lastName, $email, $status, $membershipExpiresOn, $warrantable, $birthYear]) {
        $conn->execute(
            "INSERT INTO members (id, password, sca_name, first_name, last_name, email_address, status, membership_expires_on, warrantable, birth_month, birth_year, created, modified, created_by, modified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 1, 1)
             ON CONFLICT (id) DO UPDATE SET
                sca_name = EXCLUDED.sca_name,
                first_name = EXCLUDED.first_name,
                last_name = EXCLUDED.last_name,
                email_address = EXCLUDED.email_address,
                status = EXCLUDED.status,
                membership_expires_on = EXCLUDED.membership_expires_on,
                warrantable = EXCLUDED.warrantable,
                birth_month = EXCLUDED.birth_month,
                birth_year = EXCLUDED.birth_year,
                modified = EXCLUDED.modified,
                modified_by = EXCLUDED.modified_by",
            [$id, '$2y$10$test-test-test-test-test-test-test-test-test', $scaName, $firstName, $lastName, $email, $status, $membershipExpiresOn, $warrantable, $birthYear, $now, $now]
        );
    }

    $conn->execute(
        "INSERT INTO roles (id, name, is_system, created, modified, created_by, modified_by)
         VALUES (9001, 'Edge Case Role', false, ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO roles (id, name, is_system, created, modified, created_by, modified_by)
         VALUES (9002, 'Edge Case Active Role', false, ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO permissions (id, name, is_system, is_super_user, scoping_rule, created, modified, created_by, modified_by)
         VALUES (9901, 'Edge Case Test Permission', false, false, 'Global', ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO roles_permissions (id, permission_id, role_id, created, created_by)
         VALUES (9901, 9901, 9002, ?, 1)
         ON CONFLICT (id) DO NOTHING",
        [$now]
    );
    $conn->execute(
        "INSERT INTO permission_policies (id, permission_id, policy_class, policy_method)
         VALUES (9901, 9901, 'App\\\\Policy\\\\MemberPolicy', 'canView')
         ON CONFLICT (id) DO NOTHING"
    );

    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (362, 2875, 9001, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 1, 1, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (363, 2874, 9001, NOW() - INTERVAL '365 days', NOW() - INTERVAL '30 days', 1, NULL, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (99021, 2872, 9002, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 1, NULL, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (99022, 2874, 9002, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 1, NULL, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );

    $conn->execute(
        "INSERT INTO warrant_rosters (id, name, approvals_required, approval_count, status, created, modified, created_by, modified_by)
         VALUES (9901, 'Edge Case Warrant Roster', 1, 1, 'Current', ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO warrants (id, name, member_id, warrant_roster_id, entity_id, member_role_id, start_on, expires_on, status, created, modified, created_by, modified_by)
         VALUES (99011, 'Bryce Current Warrant', 2872, 9901, 0, 99021, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 'Current', ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status, start_on = EXCLUDED.start_on, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
    $conn->execute(
        "INSERT INTO warrants (id, name, member_id, warrant_roster_id, entity_id, member_role_id, start_on, expires_on, status, created, modified, created_by, modified_by)
         VALUES (99012, 'Bryce Expired Warrant', 2872, 9901, 0, 99021, NOW() - INTERVAL '365 days', NOW() - INTERVAL '30 days', 'Expired', ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status, start_on = EXCLUDED.start_on, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now]
    );
}

// Clear cached table metadata so CakePHP sees columns added by migrations.
// Without this, Table objects may use stale schema from before migrate() ran.
$testConn = ConnectionManager::get('test');
(new \Cake\Database\SchemaCache($testConn))->clear();
\Cake\ORM\TableRegistry::getTableLocator()->clear();

// Fix stale seed data dates: extend expired test member roles to far-future dates
// so time-sensitive tests remain stable across environments.
// These fixup queries reference IDs from the MySQL seed dump (dev_seed_clean.sql)
// which is not loaded for Postgres — skip them on Postgres.
if (!SeedManager::isPostgres('test')) {
    $conn = ConnectionManager::get('test');
    $farFuture = '2100-01-01 00:00:00';

    // Extend membership expiration for all synthetic test members so permission queries work
    $conn->execute(
        "UPDATE members SET membership_expires_on = ? WHERE id IN (2871, 2872, 2874, 2875) AND membership_expires_on < NOW()",
        [$farFuture]
    );

    // Devon (2874) needs active Regional Officer Management role at Central Region (branch 12)
    // for multi-region permission tests. Role 363 was revoked, so create a replacement if needed.
    $existingActive = $conn->execute(
        "SELECT COUNT(*) as cnt FROM member_roles WHERE member_id = 2874 AND role_id = 1118 AND branch_id = 12 AND revoker_id IS NULL AND expires_on > NOW()"
    )->fetch('assoc');
    if ($existingActive && (int)$existingActive['cnt'] === 0) {
        $conn->execute(
            "INSERT INTO member_roles (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified, created_by, modified_by) VALUES (2874, 1118, 12, NOW(), ?, 1, 'Officers.Officers', NOW(), NOW(), 1, 1)",
            [$farFuture]
        );
    }
    // Devon (2874) roles at local branches (370, 371) - extend if expired
    $conn->execute(
        "UPDATE member_roles SET expires_on = ? WHERE id IN (370, 371) AND expires_on < NOW()",
        [$farFuture]
    );
}
