<?php
declare(strict_types=1);

require dirname(__FILE__) . '/vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;

// Bootstrap the application
require_once dirname(__FILE__) . '/config/bootstrap.php';

echo "=== Testing Landed Nobility Reporting ===\n\n";

$officersTable = TableRegistry::getTableLocator()->get('Officers.Officers');
$officesTable = TableRegistry::getTableLocator()->get('Officers.Offices');
$branchesTable = TableRegistry::getTableLocator()->get('Branches');

// Find landed nobility office
$nobilityOffice = $officesTable->find()
    ->where([
        'OR' => [
            ['name LIKE' => '%Nobility%'],
            ['name LIKE' => '%Baron%'],
            ['name LIKE' => '%Baroness%']
        ]
    ])
    ->first();

if ($nobilityOffice) {
    echo "Found nobility office: {$nobilityOffice->name} (ID: {$nobilityOffice->id})\n";
    echo "reports_to_id: " . ($nobilityOffice->reports_to_id ?? 'NULL') . "\n";
    echo "can_skip_report: " . ($nobilityOffice->can_skip_report ? 'true' : 'false') . "\n\n";
}

// Find a landed nobility officer in branch 41
$nobilityOfficer = $officersTable->find()
    ->where([
        'Officers.branch_id' => 41,
        'Officers.status' => 'CURRENT'
    ])
    ->contain(['Offices', 'Members', 'Branches', 'ReportsToOffices', 'ReportsToBranches'])
    ->matching('Offices', function ($q) {
        return $q->where([
            'OR' => [
                ['Offices.name LIKE' => '%Nobility%'],
                ['Offices.name LIKE' => '%Baron%'],
                ['Offices.name LIKE' => '%Baroness%']
            ]
        ]);
    })
    ->first();

if ($nobilityOfficer) {
    echo "1. Found Nobility Officer\n";
    echo "   Name: {$nobilityOfficer->member->sca_name}\n";
    echo "   Office: {$nobilityOfficer->office->name} (ID: {$nobilityOfficer->office_id})\n";
    echo "   Branch: {$nobilityOfficer->branch->name} (ID: {$nobilityOfficer->branch_id})\n";
    echo "   reports_to_office_id: " . ($nobilityOfficer->reports_to_office_id ?? 'NULL') . "\n";
    echo "   reports_to_branch_id: " . ($nobilityOfficer->reports_to_branch_id ?? 'NULL') . "\n";
    
    if ($nobilityOfficer->reports_to_office_id) {
        $reportsToOffice = $officesTable->get($nobilityOfficer->reports_to_office_id);
        echo "   Reports To Office: {$reportsToOffice->name}\n";
    }
    
    if ($nobilityOfficer->reports_to_branch_id) {
        $reportsToBranch = $branchesTable->get($nobilityOfficer->reports_to_branch_id);
        echo "   Reports To Branch: {$reportsToBranch->name}\n";
        echo "   Reports To Branch parent_id: " . ($reportsToBranch->parent_id ?? 'NULL') . "\n";
    }
    
    echo "\n2. Check Branch Hierarchy\n";
    $currentBranch = $branchesTable->get(41);
    echo "   Current Branch: {$currentBranch->name} (ID: {$currentBranch->id})\n";
    echo "   Parent ID: " . ($currentBranch->parent_id ?? 'NULL') . "\n";
    if ($currentBranch->parent_id) {
        $parentBranch = $branchesTable->get($currentBranch->parent_id);
        echo "   Parent Branch: {$parentBranch->name} (ID: {$parentBranch->id})\n";
    }
    
    echo "\n3. Check for officers in reporting office+branch\n";
    if ($nobilityOfficer->reports_to_office_id && $nobilityOfficer->reports_to_branch_id) {
        $reportingOfficers = $officersTable->find()
            ->where([
                'office_id' => $nobilityOfficer->reports_to_office_id,
                'branch_id' => $nobilityOfficer->reports_to_branch_id,
                'status' => 'CURRENT'
            ])
            ->contain(['Members', 'Offices'])
            ->all();
        
        echo "   Query: office_id={$nobilityOfficer->reports_to_office_id}, branch_id={$nobilityOfficer->reports_to_branch_id}\n";
        echo "   Found: " . $reportingOfficers->count() . " officers\n";
        foreach ($reportingOfficers as $officer) {
            echo "   - {$officer->member->sca_name} ({$officer->office->name})\n";
        }
    } else {
        echo "   No reports_to_office_id or reports_to_branch_id\n";
    }
    
    echo "\n4. Test findEffectiveReportsTo method\n";
    $effectiveOfficers = $officersTable->findEffectiveReportsTo($nobilityOfficer);
    echo "   Found: " . count($effectiveOfficers) . " effective reporting officers\n";
    foreach ($effectiveOfficers as $officer) {
        echo "   - {$officer->member->sca_name} ({$officer->office->name})\n";
    }
    
    echo "\n5. Test reports_to_list property\n";
    $reportsList = $nobilityOfficer->reports_to_list;
    echo "   Result: {$reportsList}\n";
    
} else {
    echo "No nobility officer found in branch 41\n";
}

echo "\n=== Test Complete ===\n";
