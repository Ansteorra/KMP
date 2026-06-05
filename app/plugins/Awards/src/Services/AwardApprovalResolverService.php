<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\ActiveWindowBaseEntity;
use Awards\Model\Entity\ApprovalProcess;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Award;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use RuntimeException;

/**
 * Resolves branch-scoped approvers for award approval process steps.
 */
class AwardApprovalResolverService
{
    use LocatorAwareTrait;

    /**
     * Preview every configured step for an award.
     *
     * @param \Awards\Model\Entity\ApprovalProcess $process Approval process
     * @param \Awards\Model\Entity\Award $award Award context
     * @return array<int, array<string, mixed>>
     */
    public function previewProcess(ApprovalProcess $process, Award $award): array
    {
        $preview = [];
        foreach ($process->approval_process_steps ?? [] as $step) {
            try {
                $branch = $this->resolveBranch($step, $award);
                $preview[] = [
                    'step' => $step,
                    'branch' => $branch,
                    'members' => $this->resolveApprovers($step, $award),
                    'error' => null,
                ];
            } catch (RuntimeException $exception) {
                $preview[] = [
                    'step' => $step,
                    'branch' => null,
                    'members' => [],
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $preview;
    }

    /**
     * Resolve approver members for one step and award context.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step
     * @param \Awards\Model\Entity\Award $award Award context
     * @return array<\App\Model\Entity\Member>
     */
    public function resolveApprovers(ApprovalProcessStep $step, Award $award): array
    {
        return match ($step->approver_type) {
            ApprovalProcessStep::APPROVER_TYPE_ROLE => $this->resolveRoleApprovers($step, $award),
            ApprovalProcessStep::APPROVER_TYPE_PERMISSION => $this->resolvePermissionApprovers($step, $award),
            ApprovalProcessStep::APPROVER_TYPE_OFFICE => $this->resolveOfficeApprovers($step, $award),
            ApprovalProcessStep::APPROVER_TYPE_MEMBER => $this->resolveMemberApprover($step),
            ApprovalProcessStep::APPROVER_TYPE_DYNAMIC => throw new RuntimeException(
                __('Dynamic resolver "{0}" is not registered yet.', $step->approver_source_key ?: __('unknown')),
            ),
            default => throw new RuntimeException(__('Unknown approver type "{0}".', $step->approver_type)),
        };
    }

    /**
     * Resolve the branch context for branch-scoped approver lookups.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step
     * @param \Awards\Model\Entity\Award $award Award context
     * @return \Cake\Datasource\EntityInterface|null
     */
    public function resolveBranch(ApprovalProcessStep $step, Award $award): ?EntityInterface
    {
        if ($step->approver_type === ApprovalProcessStep::APPROVER_TYPE_MEMBER) {
            return null;
        }

        $branchId = (int)$award->branch_id;
        if ($branchId <= 0) {
            throw new RuntimeException(__('The selected award does not have a branch.'));
        }

        $branches = $this->fetchTable('Branches');
        $branch = $branches->get($branchId);

        if ($step->branch_mode === ApprovalProcessStep::BRANCH_MODE_AWARD) {
            return $branch;
        }

        if ($step->branch_mode === ApprovalProcessStep::BRANCH_MODE_ANCESTOR_TYPE) {
            if (empty($step->branch_type)) {
                throw new RuntimeException(__('Select an ancestor branch type for this step.'));
            }

            return $this->findAncestorBranchByType($branch, (string)$step->branch_type);
        }

        throw new RuntimeException(__('Unknown branch scope "{0}".', $step->branch_mode));
    }

    /**
     * Resolve active members with a role in the approval branch.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step
     * @param \Awards\Model\Entity\Award $award Award context
     * @return array<\App\Model\Entity\Member>
     */
    protected function resolveRoleApprovers(ApprovalProcessStep $step, Award $award): array
    {
        if (empty($step->approver_source_id)) {
            throw new RuntimeException(__('Select a role for this approval step.'));
        }

        $branch = $this->resolveBranch($step, $award);
        $memberRoles = $this->fetchTable('MemberRoles');

        return $memberRoles->find('current')
            ->where([
                'MemberRoles.role_id' => (int)$step->approver_source_id,
                'MemberRoles.branch_id' => $branch?->id,
            ])
            ->contain(['Members' => function (SelectQuery $query): SelectQuery {
                return $query->select(['id', 'sca_name'])->orderBy(['sca_name' => 'ASC']);
            }])
            ->all()
            ->extract('member')
            ->filter()
            ->toList();
    }

    /**
     * Resolve active members whose branch-scoped roles grant a permission.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step
     * @param \Awards\Model\Entity\Award $award Award context
     * @return array<\App\Model\Entity\Member>
     */
    protected function resolvePermissionApprovers(ApprovalProcessStep $step, Award $award): array
    {
        if (empty($step->approver_source_id)) {
            throw new RuntimeException(__('Select a permission for this approval step.'));
        }

        $branch = $this->resolveBranch($step, $award);
        $memberRoles = $this->fetchTable('MemberRoles');

        return $memberRoles->find('current')
            ->where(['MemberRoles.branch_id' => $branch?->id])
            ->matching('Roles.Permissions', function (SelectQuery $query) use ($step): SelectQuery {
                return $query->where(['Permissions.id' => (int)$step->approver_source_id]);
            })
            ->contain(['Members' => function (SelectQuery $query): SelectQuery {
                return $query->select(['id', 'sca_name'])->orderBy(['sca_name' => 'ASC']);
            }])
            ->distinct(['Members.id'])
            ->all()
            ->extract('member')
            ->filter()
            ->toList();
    }

    /**
     * Resolve current office holders in the approval branch.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step
     * @param \Awards\Model\Entity\Award $award Award context
     * @return array<\App\Model\Entity\Member>
     */
    protected function resolveOfficeApprovers(ApprovalProcessStep $step, Award $award): array
    {
        if (empty($step->approver_source_id)) {
            throw new RuntimeException(__('Select an office for this approval step.'));
        }

        $branch = $this->resolveBranch($step, $award);
        $now = DateTime::now();

        return $this->fetchTable('Officers.Officers')->find()
            ->where([
                'Officers.office_id' => (int)$step->approver_source_id,
                'Officers.branch_id' => $branch?->id,
                'Officers.status' => ActiveWindowBaseEntity::CURRENT_STATUS,
                'Officers.start_on <=' => $now,
                'OR' => [
                    'Officers.expires_on IS' => null,
                    'Officers.expires_on >=' => $now,
                ],
            ])
            ->contain(['Members' => function (SelectQuery $query): SelectQuery {
                return $query->select(['id', 'sca_name'])->orderBy(['sca_name' => 'ASC']);
            }])
            ->all()
            ->extract('member')
            ->filter()
            ->toList();
    }

    /**
     * Resolve an explicit member approver.
     *
     * @param \Awards\Model\Entity\ApprovalProcessStep $step Approval step
     * @return array<\App\Model\Entity\Member>
     */
    protected function resolveMemberApprover(ApprovalProcessStep $step): array
    {
        if (empty($step->approver_source_id)) {
            throw new RuntimeException(__('Select a member for this approval step.'));
        }

        return [$this->fetchTable('Members')->get((int)$step->approver_source_id, fields: ['id', 'sca_name'])];
    }

    /**
     * Walk parent branches until a branch of the requested type is found.
     *
     * @param \Cake\Datasource\EntityInterface $branch Starting branch
     * @param string $branchType Branch type
     * @return \Cake\Datasource\EntityInterface
     */
    protected function findAncestorBranchByType(EntityInterface $branch, string $branchType): EntityInterface
    {
        $branches = $this->fetchTable('Branches');
        $current = $branch;

        while ($current !== null) {
            if ((string)$current->get('type') === $branchType) {
                return $current;
            }

            $parentId = $current->get('parent_id');
            if ($parentId === null) {
                break;
            }

            $current = $branches->get($parentId);
        }

        throw new RuntimeException(__('No ancestor branch of type "{0}" was found.', $branchType));
    }
}
