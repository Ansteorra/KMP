<?php
require __DIR__ . '/vendor/autoload.php';

use Cake\ORM\TableRegistry;

require __DIR__ . '/config/bootstrap.php';

echo "=== Testing Direct Kingdom Reporting ===\n\n";

$officersTable = TableRegistry::getTableLocator()->get('Officers.Officers');

// Test the original case - reporting directly to kingdom (office 18, branch 13)
$testOfficer = $officersTable->newEntity([
    'reports_to_office_id' => 18, // Kingdom Webminister
    'reports_to_branch_id' => 13,  // Southern Region
], ['validate' => false]);

echo "Test: Officer reporting directly to Kingdom Webminister\n";
echo "  reports_to_office_id: 18 (Kingdom Webminister)\n";
echo "  reports_to_branch_id: 13 (Southern Region)\n\n";

$effective = $officersTable->findEffectiveReportsTo($testOfficer);
echo "Result: Found " . count($effective) . " officers\n";
if (count($effective) > 0) {
    foreach ($effective as $off) {
        echo "  - {$off->member->sca_name} ({$off->office->name} in {$off->branch->name})\n";
    }
} else {
    echo "  ERROR: No officers found! Expected to find Kingdom Webminister.\n";
    echo "\n  Let's manually check:\n";
    $manual = $officersTable->find()
        ->where(['Officers.office_id' => 18, 'Officers.status' => 'CURRENT'])
        ->contain(['Members', 'Offices', 'Branches'])
        ->all();
    foreach ($manual as $m) {
        echo "  - Office 18 has: {$m->member->sca_name} in branch {$m->branch_id} ({$m->branch->name})\n";
    }
}

echo "\n=== Test Complete ===\n";
