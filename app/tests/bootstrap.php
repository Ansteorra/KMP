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

/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/config/bootstrap.php';

if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

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

// Fix stale seed data dates: extend expired test member roles to far-future dates
// so time-sensitive tests remain stable across environments.
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
