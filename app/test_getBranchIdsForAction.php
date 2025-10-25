<?php

/**
 * Test Script for getBranchIdsForAction() Method
 * 
 * This script demonstrates and tests the new getBranchIdsForAction() helper function
 * in the Member entity. Run this script from the app directory:
 * 
 * php test_getBranchIdsForAction.php
 */

declare(strict_types=1);

// Bootstrap CakePHP
require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/config/bootstrap.php';

use Cake\ORM\TableRegistry;

echo "=== Testing getBranchIdsForAction() Method ===\n\n";

// Get the Members table
$membersTable = TableRegistry::getTableLocator()->get('Members');

// Find a test user (assuming ID 1 exists - adjust as needed)
$testMemberId = 1;
$member = $membersTable->find()
    ->where(['Members.id' => $testMemberId])
    ->first();

if (!$member) {
    echo "ERROR: Could not find member with ID {$testMemberId}\n";
    echo "Please update the test script with a valid member ID.\n";
    exit(1);
}

echo "Testing with Member ID: {$member->id}\n";
echo "Member Name: {$member->sca_name}\n";
echo "Is Super User: " . ($member->isSuperUser() ? 'Yes' : 'No') . "\n\n";

// Test 1: Get branches where member can edit other members
echo "Test 1: Branches where member can EDIT Members\n";
echo "---------------------------------------------------\n";
$editBranches = $member->getBranchIdsForAction('edit', 'Members');
displayResults($editBranches);

// Test 2: Get branches where member can view members
echo "\nTest 2: Branches where member can VIEW Members\n";
echo "---------------------------------------------------\n";
$viewBranches = $member->getBranchIdsForAction('view', 'Members');
displayResults($viewBranches);

// Test 3: Get branches where member can add members
echo "\nTest 3: Branches where member can ADD Members\n";
echo "---------------------------------------------------\n";
$addBranches = $member->getBranchIdsForAction('add', 'Members');
displayResults($addBranches);

// Test 4: Get branches where member can delete members
echo "\nTest 4: Branches where member can DELETE Members\n";
echo "---------------------------------------------------\n";
$deleteBranches = $member->getBranchIdsForAction('delete', 'Members');
displayResults($deleteBranches);

// Test 5: Test with Branches entity
echo "\nTest 5: Branches where member can EDIT Branches\n";
echo "---------------------------------------------------\n";
$editBranchesForBranches = $member->getBranchIdsForAction('edit', 'Branches');
displayResults($editBranchesForBranches);

// Test 6: Test with entity instance
echo "\nTest 6: Using Entity Instance (Members)\n";
echo "---------------------------------------------------\n";
$memberEntity = $membersTable->newEmptyEntity();
$entityBranches = $member->getBranchIdsForAction('edit', $memberEntity);
displayResults($entityBranches);

// Test 7: Test with plugin entity if Officers plugin exists
try {
    $officersTable = TableRegistry::getTableLocator()->get('Officers.Officers');
    echo "\nTest 7: Branches where member can EDIT Officers (Plugin)\n";
    echo "---------------------------------------------------\n";
    $officerBranches = $member->getBranchIdsForAction('edit', 'Officers.Officers');
    displayResults($officerBranches);
} catch (Exception $e) {
    echo "\nTest 7: Skipped (Officers plugin not available)\n";
    echo "---------------------------------------------------\n";
}

// Practical Example: Build branch dropdown
echo "\n\n=== Practical Example: Building Branch Dropdown ===\n";
echo "---------------------------------------------------\n";
$branchIds = $member->getBranchIdsForAction('edit', 'Members');
$branchesTable = TableRegistry::getTableLocator()->get('Branches');

if ($branchIds === null) {
    echo "User has GLOBAL permission - loading all branches\n";
    $branches = $branchesTable->find('list')->all()->toArray();
} elseif (!empty($branchIds)) {
    echo "User has LIMITED permission - loading " . count($branchIds) . " branches\n";
    $branches = $branchesTable->find('list')
        ->where(['id IN' => $branchIds])
        ->all()
        ->toArray();
} else {
    echo "User has NO permission - no branches available\n";
    $branches = [];
}

if (!empty($branches)) {
    echo "\nAvailable branches for dropdown:\n";
    foreach ($branches as $id => $name) {
        echo "  - [{$id}] {$name}\n";
    }
} else {
    echo "No branches available\n";
}

echo "\n=== Test Complete ===\n";

/**
 * Helper function to display test results
 */
function displayResults($branchIds): void
{
    if ($branchIds === null) {
        echo "Result: GLOBAL PERMISSION (all branches)\n";
        echo "Interpretation: User can perform this action on ANY branch\n";
    } elseif (empty($branchIds)) {
        echo "Result: NO PERMISSION (empty array)\n";
        echo "Interpretation: User cannot perform this action on any branch\n";
    } else {
        echo "Result: LIMITED PERMISSION\n";
        echo "Branch IDs: " . implode(', ', $branchIds) . "\n";
        echo "Count: " . count($branchIds) . " branches\n";

        // Load branch names for better readability
        $branchesTable = TableRegistry::getTableLocator()->get('Branches');
        $branches = $branchesTable->find()
            ->where(['id IN' => $branchIds])
            ->select(['id', 'name'])
            ->all();

        echo "Branch Names:\n";
        foreach ($branches as $branch) {
            echo "  - [{$branch->id}] {$branch->name}\n";
        }
    }
}
