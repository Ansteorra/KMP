<?php

declare(strict_types=1);

namespace App\Services\ApprovalContext;

use App\Model\Entity\WorkflowInstance;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/**
 * Provides rich display context for warrant roster approvals
 * shown in the unified approvals queue.
 */
class WarrantRosterApprovalContextRenderer implements ApprovalContextRendererInterface
{
    /**
     * @inheritDoc
     */
    public function canRender(WorkflowInstance $instance): bool
    {
        return $instance->entity_type === 'WarrantRosters';
    }

    /**
     * @inheritDoc
     */
    public function render(WorkflowInstance $instance): ApprovalContext
    {
        $context = $instance->context ?? [];
        $triggerData = $context['trigger'] ?? $context['event'] ?? $context;

        $rosterId = $triggerData['rosterId'] ?? $instance->entity_id;
        $rosterName = $triggerData['rosterName'] ?? null;
        $approvalsRequired = $triggerData['approvalsRequired'] ?? null;

        $roster = $this->loadRoster((int)$rosterId);

        if ($roster !== null) {
            $rosterName = $rosterName ?? $roster->name;
            $approvalsRequired = $approvalsRequired ?? $roster->approvals_required;
        }

        $rosterName = $rosterName ?? __('Unknown Roster');
        $warrantCount = $this->getWarrantCount((int)$rosterId);
        $creatorName = $this->loadCreatorName($roster->created_by ?? null);

        $title = __('Warrant Roster: {0}', $rosterName);

        $description = $creatorName
            ? __("{0} submitted {1} warrant(s) for approval", $creatorName, $warrantCount)
            : __("{0} warrant(s) submitted for approval", $warrantCount);

        $fields = [
            ['label' => __('Roster Name'), 'value' => (string)$rosterName],
            ['label' => __('Warrant Count'), 'value' => (string)$warrantCount],
            ['label' => __('Approvals Required'), 'value' => (string)($approvalsRequired ?? __('N/A'))],
            ['label' => __('Status'), 'value' => (string)($roster->status ?? __('Pending'))],
            ['label' => __('Created By'), 'value' => $creatorName ?? __('Unknown')],
        ];

        $entityUrl = $this->buildEntityUrl($rosterId);

        return new ApprovalContext(
            title: $title,
            description: $description,
            fields: $fields,
            entityUrl: $entityUrl,
            icon: 'bi-file-earmark-check',
            requester: $creatorName,
        );
    }

    /**
     * Load the warrant roster entity.
     *
     * @param int|null $rosterId Roster ID.
     * @return \App\Model\Entity\WarrantRoster|null
     */
    private function loadRoster(?int $rosterId): ?\App\Model\Entity\WarrantRoster
    {
        if ($rosterId === null) {
            return null;
        }

        try {
            return TableRegistry::getTableLocator()
                ->get('WarrantRosters')
                ->find()
                ->where(['WarrantRosters.id' => $rosterId])
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get the count of warrants in a roster.
     *
     * @param int|null $rosterId Roster ID.
     * @return int
     */
    private function getWarrantCount(?int $rosterId): int
    {
        if ($rosterId === null) {
            return 0;
        }

        try {
            return TableRegistry::getTableLocator()
                ->get('Warrants')
                ->find()
                ->where(['warrant_roster_id' => $rosterId])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Load creator SCA name by member ID.
     *
     * @param int|string|null $memberId Member ID.
     * @return string|null
     */
    private function loadCreatorName(int|string|null $memberId): ?string
    {
        if ($memberId === null) {
            return null;
        }

        try {
            $member = TableRegistry::getTableLocator()
                ->get('Members')
                ->find()
                ->where(['id' => (int)$memberId])
                ->select(['sca_name'])
                ->first();

            if ($member) {
                return $member->sca_name;
            }
        } catch (\Throwable $e) {
            // Fallback on DB/table errors
        }

        return null;
    }

    /**
     * Build a URL to the warrant roster view.
     *
     * @param int|string|null $rosterId Roster ID.
     * @return string|null
     */
    private function buildEntityUrl(int|string|null $rosterId): ?string
    {
        if ($rosterId === null) {
            return null;
        }

        try {
            return Router::url([
                'controller' => 'WarrantRosters',
                'action' => 'view',
                $rosterId,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
