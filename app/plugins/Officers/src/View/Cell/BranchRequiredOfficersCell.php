<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;

/**
 * View cell for displaying required officer compliance status for a branch.
 * 
 * Shows required offices filtered by branch type with current assignment status
 * for organizational compliance monitoring and gap identification.
 * 
 * @package Officers\View\Cell
 * @see /docs/5.1-officers-plugin.md for plugin documentation
 */
class BranchRequiredOfficersCell extends Cell
{
    /** @var array<string, mixed> */
    protected array $_validCellOptions = [];

    /**
     * @return void
     */
    public function initialize(): void {}

    /**
     * Display required officers with current assignment status for compliance tracking.
     * 
     * Queries required offices applicable to branch type, includes current officers
     * with member information for gap identification and compliance assessment.
     *
     * @param int $id Branch ID for compliance assessment
     * @return void Sets 'requiredOffices', 'id' for template
     */
    public function display($id)
    {
        $branch = $this->getTableLocator()->get("Branches")
            ->find('byPublicId', [$id])
            ->select(['id', 'parent_id', 'type'])
            ->first();
        if (!$branch) {
            return;
        }
        $branchId = $branch->id;
        $officesTbl = $this->getTableLocator()->get("Officers.Offices");
        $officesQuery = $officesTbl->find()
            ->contain(["CurrentOfficers" => function ($q) use ($branchId) {
                return $q
                    ->select(["id", "member_id", "office_id", "start_on", "expires_on", "Members.sca_name", "CurrentOfficers.email_address"])
                    ->contain(["Members"])
                    ->where(['CurrentOfficers.branch_id' => $branchId]);
            }])
            ->where(['required_office' => true]);
        $officesQuery = $officesQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%']);
        $requiredOffices = $officesQuery->toArray();
        $this->set(compact('requiredOffices', 'id'));
    }
}
