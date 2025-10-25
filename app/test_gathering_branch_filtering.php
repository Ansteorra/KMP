<?php

declare(strict_types=1);

/**
 * Test Script for Gathering Branch Filtering
 *
 * This script demonstrates that the GatheringsController correctly filters
 * branches based on user permissions using getBranchIdsForAction().
 *
 * Usage: php test_gathering_branch_filtering.php
 */

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/bootstrap.php';

echo "=== Testing Gathering Branch Filtering ===\n\n";

// Get test member
$membersTable = TableRegistry::getTableLocator()->get('Members');
$member = $membersTable->find()
    ->where(['email_address' => 'testking@example.com'])
    ->first();

if (!$member) {
    echo "ERROR: Test member not found\n";
    exit(1);
}

echo "Testing with member: {$member->sca_name} (ID: {$member->id})\n\n";

// Test getBranchIdsForAction for Gatherings
$actions = ['index', 'add', 'edit', 'view'];

foreach ($actions as $action) {
    echo "Testing action: {$action}\n";
    $branchIds = $member->getBranchIdsForAction($action, 'Gatherings');

    if ($branchIds === null) {
        echo "  Result: GLOBAL access (all branches)\n";
    } elseif (empty($branchIds)) {
        echo "  Result: NO access (no branches)\n";
    } else {
        echo "  Result: LIMITED access to branches: " . implode(', ', $branchIds) . "\n";

        // Get branch names
        $branchesTable = TableRegistry::getTableLocator()->get('Branches');
        $branches = $branchesTable->find()
            ->where(['id IN' => $branchIds])
            ->select(['id', 'name'])
            ->all();

        foreach ($branches as $branch) {
            echo "    - {$branch->name} (ID: {$branch->id})\n";
        }
    }
    echo "\n";
}

echo "=== Testing branch query filtering ===\n\n";

// Simulate what the controller does
$branchesTable = TableRegistry::getTableLocator()->get('Branches');

// Test for 'add' action
$branchIds = $member->getBranchIdsForAction('add', 'Gatherings');
echo "For 'add' action:\n";

$branchesQuery = $branchesTable->find('list')->orderBy(['name' => 'ASC']);
if ($branchIds !== null) {
    $branchesQuery->where(['Branches.id IN' => $branchIds]);
    echo "  Applying WHERE filter with branch IDs: " . implode(', ', $branchIds) . "\n";
} else {
    echo "  No filter applied (global access)\n";
}

$branches = $branchesQuery->toArray();
echo "  Available branches in dropdown: " . count($branches) . "\n";
foreach ($branches as $id => $name) {
    echo "    - {$name} (ID: {$id})\n";
}

echo "\n=== Test Complete ===\n";
