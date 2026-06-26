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
use Cake\Database\SchemaCache;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
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
    if (!is_dir($runtimeTmp)) {
        mkdir($runtimeTmp, 0777, true);
    }
    define('TMP', $runtimeTmp);
}

if (!defined('LOGS') && !is_writable($projectRoot . DIRECTORY_SEPARATOR . 'logs')) {
    if (!is_dir($runtimeLogs)) {
        mkdir($runtimeLogs, 0777, true);
    }
    define('LOGS', $runtimeLogs);
}

require dirname(__DIR__) . '/config/bootstrap.php';

putenv('KMP_TENANCY_ENABLED=false');
$_ENV['KMP_TENANCY_ENABLED'] = 'false';
$_SERVER['KMP_TENANCY_ENABLED'] = 'false';

Configure::write('Queue.connection', 'test');

if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

// Keep document storage under writable test temp path.
$testDocumentPath = TMP . 'uploaded';
if (!is_dir($testDocumentPath)) {
    mkdir($testDocumentPath, 0777, true);
}
Configure::write('Documents.storage.local.path', $testDocumentPath);

// DebugKit skips settings these connection config if PHP SAPI is CLI / PHPDBG.
// But since PagesControllerTest is run with debug enabled and DebugKit is loaded
// in application, without setting up these config DebugKit errors out.
ConnectionManager::setConfig('test_debug_kit', [
    'className' => 'Cake\Database\Connection',
    'driver' => 'Cake\Database\Driver\Sqlite',
    'database' => TMP . 'debug_kit.sqlite',
    'encoding' => 'utf8',
    'cacheMetadata' => true,
    'quoteIdentifiers' => false,
]);

ConnectionManager::alias('test_debug_kit', 'debug_kit');
ConnectionManager::alias('test', 'default');

// Ensure tmp and logs directories exist and are writable for tests
foreach ([LOGS, TMP, TMP . 'cache', TMP . 'sessions', TMP . 'tests'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Fixate sessionid early on, as php7.2+
// does not allow the sessionid to be set after stdout
// has been written to.
if (session_status() === PHP_SESSION_NONE) {
    session_id('cli');
}

// Ensure the test schema is seeded with the shared dev dataset.
// MySQL uses a schema+data dump, so load it before migrations. PostgreSQL uses
// a data-only converted seed, so load it after migrations create the schema.
if (!SeedManager::isPostgres('test')) {
    SeedManager::bootstrap('test');
}

// PostgreSQL tests use a current schema dump plus a data-only seed, so rerunning
// all migrations would try to recreate existing tables.
if (!SeedManager::isPostgres('test')) {
    // Run any pending migrations on the test connection to create tables
    // not yet included in dev_seed_clean.sql (e.g., workflow engine tables).
    (new Migrations())->migrate(['connection' => 'test']);
}

// Fix stale seed data dates: extend expired test member roles to far-future dates
// so time-sensitive tests remain stable across environments.
$conn = ConnectionManager::get('test');
$farFuture = '2100-01-01 00:00:00';

// Apply plugin migrations on MySQL after the shared seed dump. PostgreSQL tests
// use a current schema dump plus a data-only converted seed.
if (!SeedManager::isPostgres('test')) {
    foreach (['Queue', 'Officers', 'Activities', 'Awards', 'Waivers'] as $plugin) {
        (new Migrations())->migrate(['connection' => 'test', 'plugin' => $plugin]);
    }
} else {
    SeedManager::bootstrap('test');
}

$bestowalSchema = $conn->getSchemaCollection()->describe('awards_bestowals');
if (!$bestowalSchema->hasColumn('reason_summary')) {
    $conn->execute('ALTER TABLE awards_bestowals ADD COLUMN reason_summary text');
    (new SchemaCache($conn))->clear();
    $bestowalSchema = $conn->getSchemaCollection()->describe('awards_bestowals');
}
if (!$bestowalSchema->hasColumn('specialty')) {
    $conn->execute('ALTER TABLE awards_bestowals ADD COLUMN specialty varchar(255)');
    (new SchemaCache($conn))->clear();
}

// On Postgres (no MySQL seed dump), we also need to seed essential AppSettings.
if (SeedManager::isPostgres('test')) {
    $hasBestowalAwardId = (bool)$conn->execute(
        "SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'awards_bestowals'
              AND column_name = 'award_id'
        )",
    )->fetchColumn(0);
    if (!$hasBestowalAwardId) {
        $conn->execute('ALTER TABLE awards_bestowals ADD COLUMN award_id integer');
        $conn->execute(
            'UPDATE awards_bestowals b
             SET award_id = (
                 SELECT r.award_id
                 FROM awards_recommendations r
                 WHERE r.id = b.primary_recommendation_id
                 LIMIT 1
             )
             WHERE b.award_id IS NULL
               AND b.primary_recommendation_id IS NOT NULL',
        );
        $conn->execute(
            'UPDATE awards_bestowals b
             SET award_id = (
                 SELECT r.award_id
                 FROM awards_bestowal_recommendations br
                 INNER JOIN awards_recommendations r ON r.id = br.recommendation_id
                 WHERE br.bestowal_id = b.id
                 ORDER BY br.id ASC
                 LIMIT 1
             )
             WHERE b.award_id IS NULL',
        );
        $conn->execute('CREATE INDEX IF NOT EXISTS idx_bestowals_award_id ON awards_bestowals (award_id)');
        $conn->execute(
            "DO $$
             BEGIN
                 IF NOT EXISTS (
                     SELECT 1
                     FROM pg_constraint
                     WHERE conname = 'fk_bestowals_award_id'
                 ) THEN
                     ALTER TABLE awards_bestowals
                         ADD CONSTRAINT fk_bestowals_award_id
                         FOREIGN KEY (award_id)
                         REFERENCES awards_awards(id)
                         ON DELETE RESTRICT
                         ON UPDATE CASCADE;
                 END IF;
             END
             $$",
        );
    }

    // Guard: roaming_court column added by migration 20260604120000
    $hasBestowalRoamingCourt = (bool)$conn->execute(
        "SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'awards_bestowals'
              AND column_name = 'roaming_court'
        )",
    )->fetchColumn(0);
    if (!$hasBestowalRoamingCourt) {
        $conn->execute('ALTER TABLE awards_bestowals ADD COLUMN roaming_court boolean DEFAULT false NOT NULL');
    }

    // Guard: approval-run lifecycle columns added by migration 20260607130000.
    $hasApprovalRunTerminalReason = (bool)$conn->execute(
        "SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = 'awards_recommendation_approval_runs'
              AND column_name = 'terminal_reason'
        )",
    )->fetchColumn(0);
    if (!$hasApprovalRunTerminalReason) {
        $conn->execute('ALTER TABLE awards_recommendation_approval_runs ADD COLUMN terminal_reason varchar(100)');
        $conn->execute('ALTER TABLE awards_recommendation_approval_runs ADD COLUMN consumed_by_bestowal_id integer');
        $conn->execute('ALTER TABLE awards_recommendation_approval_runs ADD COLUMN superseded_by_bestowal_id integer');
        $conn->execute('ALTER TABLE awards_recommendation_approval_runs ADD COLUMN rehydrated_from_run_id integer');
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_awards_rec_approval_runs_terminal_reason
             ON awards_recommendation_approval_runs (terminal_reason)',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_awards_rec_approval_runs_consumed_bestowal
             ON awards_recommendation_approval_runs (consumed_by_bestowal_id)',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_awards_rec_approval_runs_superseded_bestowal
             ON awards_recommendation_approval_runs (superseded_by_bestowal_id)',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_awards_rec_approval_runs_rehydrated_from
             ON awards_recommendation_approval_runs (rehydrated_from_run_id)',
        );
    }

    $hasFeedbackRequestTable = (bool)$conn->execute(
        "SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = current_schema()
              AND table_name = 'awards_recommendation_feedback_requests'
        )",
    )->fetchColumn(0);
    if (!$hasFeedbackRequestTable) {
        $conn->execute(
            "CREATE TABLE awards_recommendation_feedback_requests (
                id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                requester_id integer NOT NULL,
                status varchar(32) DEFAULT 'pending' NOT NULL,
                message text NULL,
                deadline timestamp without time zone NULL,
                workflow_instance_id integer NULL,
                completed_at timestamp without time zone NULL,
                retracted_at timestamp without time zone NULL,
                expired_at timestamp without time zone NULL,
                created timestamp without time zone NOT NULL,
                modified timestamp without time zone NULL,
                created_by integer NULL,
                modified_by integer NULL
            )",
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_rec_feedback_requester_status
             ON awards_recommendation_feedback_requests (requester_id, status)',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_rec_feedback_workflow_instance
             ON awards_recommendation_feedback_requests (workflow_instance_id)',
        );
    }

    $feedbackItemColumnCount = (int)$conn->execute(
        "SELECT count(*)
         FROM information_schema.columns
         WHERE table_schema = current_schema()
           AND table_name = 'awards_recommendation_feedback_request_items'",
    )->fetchColumn(0);
    if ($feedbackItemColumnCount === 0) {
        $conn->execute('DROP TABLE IF EXISTS awards_recommendation_feedback_request_items');
        $conn->execute(
            'CREATE TABLE awards_recommendation_feedback_request_items (
                id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                feedback_request_id integer NOT NULL,
                recommendation_id integer NOT NULL,
                snapshot json NOT NULL,
                created timestamp without time zone NOT NULL,
                modified timestamp without time zone NULL
            )',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_rec_feedback_items_request
             ON awards_recommendation_feedback_request_items (feedback_request_id)',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_rec_feedback_items_rec
             ON awards_recommendation_feedback_request_items (recommendation_id)',
        );
        $conn->execute(
            "DO $$
             BEGIN
                 IF NOT EXISTS (
                     SELECT 1
                     FROM pg_constraint
                     WHERE conname = 'fk_rec_fb_item_request'
                 ) THEN
                     ALTER TABLE awards_recommendation_feedback_request_items
                         ADD CONSTRAINT fk_rec_fb_item_request
                         FOREIGN KEY (feedback_request_id)
                         REFERENCES awards_recommendation_feedback_requests(id)
                         ON DELETE CASCADE
                         ON UPDATE CASCADE;
                 END IF;
                 IF NOT EXISTS (
                     SELECT 1
                     FROM pg_constraint
                     WHERE conname = 'fk_rec_fb_item_rec'
                 ) THEN
                     ALTER TABLE awards_recommendation_feedback_request_items
                         ADD CONSTRAINT fk_rec_fb_item_rec
                         FOREIGN KEY (recommendation_id)
                         REFERENCES awards_recommendations(id)
                         ON DELETE RESTRICT
                         ON UPDATE CASCADE;
                 END IF;
             END
             $$",
        );
    }

    $hasFeedbackRecipientTable = (bool)$conn->execute(
        "SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = current_schema()
              AND table_name = 'awards_recommendation_feedback_request_recipients'
        )",
    )->fetchColumn(0);
    if (!$hasFeedbackRecipientTable) {
        $conn->execute(
            "CREATE TABLE awards_recommendation_feedback_request_recipients (
                id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                feedback_request_id integer NOT NULL,
                recipient_id integer NOT NULL,
                workflow_approval_id integer NULL,
                workflow_approval_response_id integer NULL,
                status varchar(32) DEFAULT 'pending' NOT NULL,
                response_comment text NULL,
                responded_at timestamp without time zone NULL,
                retracted_at timestamp without time zone NULL,
                expired_at timestamp without time zone NULL,
                created timestamp without time zone NOT NULL,
                modified timestamp without time zone NULL
            )",
        );
        $conn->execute(
            'CREATE UNIQUE INDEX IF NOT EXISTS idx_rec_feedback_recipient_unique
             ON awards_recommendation_feedback_request_recipients (feedback_request_id, recipient_id)',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_rec_feedback_recipient_status
             ON awards_recommendation_feedback_request_recipients (recipient_id, status)',
        );
        $conn->execute(
            'CREATE INDEX IF NOT EXISTS idx_rec_feedback_workflow_approval
             ON awards_recommendation_feedback_request_recipients (workflow_approval_id)',
        );
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
                'INSERT INTO app_settings (name, value, created, modified) VALUES (?, ?, ?, ?)',
                [$name, $value, $now, $now],
            );
        } catch (Exception $e) {
            // Setting may already exist from migration seed
        }
    }

    // Seed DB-agnostic authorization edge-case records used by service tests.
    $members = [
        [2871, 'Agatha Local MoAS Demoer', 'Agatha', 'Demoer', 'agatha@ampdemo.com', 'verified', '2100-01-01', 'true', 2000],
        [2872, 'Bryce Local Seneschal Demoer', 'Bryce', 'Demoer', 'bryce@ampdemo.com', 'verified', '2100-01-01', 'true', 2001],
        [2874, 'Devon Regional Armored Demoer', 'Devon', 'Demoer', 'devon@ampdemo.com', 'verified', '2100-01-01', 'false', 2002],
        [2875, 'Eirik Kingdom Seneschal Demoer', 'Eirik', 'Demoer', 'eirik@ampdemo.com', 'verified', '2100-01-01', 'true', 2004],
    ];
    foreach ($members as [$id, $scaName, $firstName, $lastName, $email, $status, $membershipExpiresOn, $warrantable, $birthYear]) {
        $publicId = sprintf('TST%05d', $id);
        $conn->execute(
            "INSERT INTO members (id, public_id, password, sca_name, first_name, last_name, email_address, status, membership_expires_on, warrantable, birth_month, birth_year, created, modified, created_by, modified_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 1, 1)
             ON CONFLICT (id) DO UPDATE SET
                public_id = EXCLUDED.public_id,
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
            [$id, $publicId, '$2y$10$test-test-test-test-test-test-test-test-test', $scaName, $firstName, $lastName, $email, $status, $membershipExpiresOn, $warrantable, $birthYear, $now, $now],
        );
    }

    $conn->execute(
        "INSERT INTO roles (id, name, is_system, created, modified, created_by, modified_by)
         VALUES (9001, 'Edge Case Role', false, ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO roles (id, name, is_system, created, modified, created_by, modified_by)
         VALUES (9002, 'Edge Case Active Role', false, ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO permissions (id, name, is_system, is_super_user, scoping_rule, created, modified, created_by, modified_by)
         VALUES (9901, 'Edge Case Test Permission', false, false, 'Global', ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO roles_permissions (id, permission_id, role_id, created, created_by)
         VALUES (9901, 9901, 9002, ?, 1)
         ON CONFLICT (id) DO NOTHING",
        [$now],
    );
    $conn->execute(
        "INSERT INTO permission_policies (id, permission_id, policy_class, policy_method)
         VALUES (9901, 9901, 'App\\\\Policy\\\\MemberPolicy', 'canView')
         ON CONFLICT (id) DO NOTHING",
    );

    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (362, 2875, 9001, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 1, 1, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (363, 2874, 9001, NOW() - INTERVAL '365 days', NOW() - INTERVAL '30 days', 1, NULL, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (99021, 2872, 9002, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 1, NULL, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO member_roles (id, member_id, role_id, start_on, expires_on, approver_id, revoker_id, created, modified, created_by, modified_by, branch_id)
         VALUES (99022, 2874, 9002, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 1, NULL, ?, ?, 1, 1, NULL)
         ON CONFLICT (id) DO UPDATE SET member_id = EXCLUDED.member_id, role_id = EXCLUDED.role_id, revoker_id = EXCLUDED.revoker_id, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );

    $conn->execute(
        "INSERT INTO warrant_rosters (id, name, approvals_required, approval_count, status, created, modified, created_by, modified_by)
          VALUES (9901, 'Edge Case Warrant Roster', 1, 1, 'Current', ?, ?, 1, 1)
          ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO warrant_rosters (id, name, approvals_required, approval_count, status, created, modified, created_by, modified_by)
          VALUES (9902, 'Pending Approval Warrant Roster', 1, 0, 'Pending', ?, ?, 1, 1)
          ON CONFLICT (id) DO UPDATE SET name = EXCLUDED.name, status = EXCLUDED.status, approval_count = EXCLUDED.approval_count, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO warrants (id, name, member_id, warrant_roster_id, entity_id, member_role_id, start_on, expires_on, status, created, modified, created_by, modified_by)
         VALUES (99011, 'Bryce Current Warrant', 2872, 9901, 0, 99021, NOW() - INTERVAL '30 days', NOW() + INTERVAL '365 days', 'Current', ?, ?, 1, 1)
         ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status, start_on = EXCLUDED.start_on, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );
    $conn->execute(
        "INSERT INTO warrants (id, name, member_id, warrant_roster_id, entity_id, member_role_id, start_on, expires_on, status, created, modified, created_by, modified_by)
          VALUES (99012, 'Bryce Expired Warrant', 2872, 9901, 0, 99021, NOW() - INTERVAL '365 days', NOW() - INTERVAL '30 days', 'Expired', ?, ?, 1, 1)
          ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status, start_on = EXCLUDED.start_on, expires_on = EXCLUDED.expires_on, modified = EXCLUDED.modified, modified_by = EXCLUDED.modified_by",
        [$now, $now],
    );

    $tablesWithSeededIds = [
        'members',
        'roles',
        'permissions',
        'roles_permissions',
        'permission_policies',
        'member_roles',
        'warrant_rosters',
        'warrants',
    ];
    foreach ($tablesWithSeededIds as $table) {
        $conn->execute(
            sprintf(
                "SELECT CASE WHEN pg_get_serial_sequence('%s', 'id') IS NOT NULL THEN setval(pg_get_serial_sequence('%s', 'id'), GREATEST((SELECT MAX(id) FROM %s), 1)) END",
                $table,
                $table,
                $table,
            ),
        );
    }
}

// Clear cached table metadata so CakePHP sees columns added by migrations.
// Without this, Table objects may use stale schema from before migrate() ran.
$testConn = ConnectionManager::get('test');
(new SchemaCache($testConn))->clear();
TableRegistry::getTableLocator()->clear();

// Fix stale seed data dates: extend expired test member roles to far-future dates
// so time-sensitive tests remain stable across environments.
// These fixup queries reference IDs from the MySQL seed dump (dev_seed_clean.sql)
// which is not loaded for Postgres — skip them on Postgres.
if (!SeedManager::isPostgres('test')) {
    $conn = ConnectionManager::get('test');
    $farFuture = '2100-01-01 00:00:00';

    // Extend membership expiration for all synthetic test members so permission queries work
    $conn->execute(
        'UPDATE members SET membership_expires_on = ? WHERE id IN (2871, 2872, 2874, 2875) AND membership_expires_on < NOW()',
        [$farFuture],
    );

    // Devon (2874) needs active Regional Officer Management role at Central Region (branch 12)
    // for multi-region permission tests. Role 363 was revoked, so create a replacement if needed.
    $existingActive = $conn->execute(
        'SELECT COUNT(*) as cnt FROM member_roles WHERE member_id = 2874 AND role_id = 1118 AND branch_id = 12 AND revoker_id IS NULL AND expires_on > NOW()',
    )->fetch('assoc');
    if ($existingActive && (int)$existingActive['cnt'] === 0) {
        $conn->execute(
            "INSERT INTO member_roles (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified, created_by, modified_by) VALUES (2874, 1118, 12, NOW(), ?, 1, 'Officers.Officers', NOW(), NOW(), 1, 1)",
            [$farFuture],
        );
    }
    // Devon (2874) roles at local branches (370, 371) - extend if expired
    $conn->execute(
        'UPDATE member_roles SET expires_on = ? WHERE id IN (370, 371) AND expires_on < NOW()',
        [$farFuture],
    );
}
