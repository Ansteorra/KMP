<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;
use Cake\Datasource\Paging\NumericPaginator;
use App\Services\ViewCellRegistry;

/**
 * View cell for displaying branch officers with hierarchical office organization.
 * 
 * Provides officer assignment interface with permission-based management,
 * deputy relationships, and organizational structure visualization.
 * 
 * @package Officers\View\Cell
 * @see /docs/5.1-officers-plugin.md for plugin documentation
 */
class BranchOfficersCell extends Cell
{
    static protected array $validRoutes = [
        ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
        'label' => 'Officers',
        'id' => 'branch-officers',
        'order' => 1,
        'tabBtnBadge' => null,
        'cell' => 'Officers.BranchOfficers'
    ];

    /** @var array<string, mixed> */
    protected array $_validCellOptions = [];

    /**
     * @return void
     */
    public function initialize(): void {}

    /**
     * Display branch officers with hierarchical office tree and assignment permissions.
     * 
     * Builds office hierarchy filtered by branch type, validates user assignment
     * permissions, and generates email addresses from branch/department domains.
     *
     * @param int $id Branch ID for officer display
     * @return void Sets 'id', 'offices', 'newOfficer' for template
     */
    public function display($id)
    {

        $branch = $this->fetchTable("Branches")
            ->find('byPublicId', [$id])
            ->select(['id', 'parent_id', 'type', 'domain'])
            ->first();
        if (!$branch) {
            return;
        }
        $branchId = $branch->id;
        $officersTable = $this->fetchTable("Officers.Officers");

        $newOfficer = $officersTable->newEmptyEntity();

        $officesTbl = $this->fetchTable("Officers.Offices");
        $officeQuery = $officesTbl->find("all")
            ->contain(["Departments"])
            ->select(["id", "Offices.name", "deputy_to_id", "reports_to_id", "applicable_branch_types", "default_contact_address"])
            ->orderBY(["Offices.name" => "ASC"]);
        $officeSet = $officeQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%'])->toArray();
        $user = $this->request->getAttribute("identity");
        $hireAll = false;
        $canHireOffices = [];
        $myOffices = [];
        if ($user->checkCan("assign", "Officers.Officers", $branchId) && $user->checkCan("workWithAllOfficers", "Officers.Officers", $branchId)) {
            $hireAll = true;
        } else {
            $canHireOffices = $officesTbl->officesMemberCanWork($user, $branchId);
            $officersTbl = TableRegistry::getTableLocator()->get("Officers.Officers");
            $userOffices = $officersTbl->find("current")->where(['member_id' => $user->id])->select(['office_id'])->toArray();
            foreach ($userOffices as $userOffice) {
                $myOffices[] = $userOffice->office_id;
            }
        }
        $offices = $this->buildOfficeTree($officeSet, $branch,  $hireAll, $myOffices, $canHireOffices, null);
        $this->set(compact('id', 'branchId', 'offices', 'newOfficer'));
    }

    /**
     * Build hierarchical office tree with permission-based filtering.
     * 
     * Recursively constructs office hierarchy including deputy relationships,
     * validates user permissions, and generates email addresses from domains.
     *
     * @param array $offices Array of office entities
     * @param object $branch Branch entity for domain resolution
     * @param bool $hireAll User has global assignment authority
     * @param array $myOffices User's current office IDs
     * @param array $canHireOffices Office IDs user can assign to
     * @param int|null $office_id Parent office ID for recursion (null for root)
     * @return array Hierarchical office tree sorted alphabetically
     */
    private function buildOfficeTree($offices, $branch, $hireAll, $myOffices, $canHireOffices, $office_id = null)
    {
        $tree = [];
        foreach ($offices as $office) {
            if ($office->deputy_to_id == $office_id || ($office_id == null && in_array($office->id, $myOffices))) {
                $newofficeEmail = "";
                if (isset($office->default_contact_address) && !empty($office->default_contact_address)) {
                    if (isset($branch->domain) && !empty($branch->domain)) {
                        $newofficeEmail = $office->default_contact_address . "@" . $branch->domain;
                    } else if (isset($office->department->domain) && !empty($office->department->domain)) {
                        $newofficeEmail = $office->default_contact_address . "@" . $office->department->domain;
                    } else {
                        $newofficeEmail = $office->default_contact_address . "@no_defaults_found.no_domain";
                    }
                }
                if ($hireAll) {
                    $canHire = true;
                } else {
                    $canHire = in_array($office->id, $canHireOffices);
                }
                if ($canHire) {
                    $newOffice = [
                        'id' => $office->id,
                        'name' => $office->name,
                        'deputy_to_id' => $office->deputy_to_id,
                        'deputies' => [],
                        'email_address' => $newofficeEmail,
                        'enabled' => strpos($office->applicable_branch_types, "\"$branch->type\"") !== false
                    ];
                    $newOffice['deputies'] = $this->buildOfficeTree($offices, $branch, $hireAll, $myOffices, $canHireOffices,  $office->id,);
                    $tree[] = $newOffice;
                } elseif (in_array($office->id, $myOffices)) {
                    $tempDeputies = $this->buildOfficeTree($offices, $branch, $hireAll, $myOffices, $canHireOffices,  $office->id);
                    foreach ($tempDeputies as $tempDeputy) {
                        $tree[] = $tempDeputy;
                    }
                }
            }
        }
        //order the tree by name
        usort($tree, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });
        return $tree;
    }
}
