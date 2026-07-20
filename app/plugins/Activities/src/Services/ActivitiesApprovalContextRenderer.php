<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Services\ApprovalContext\ApprovalContext;
use App\Services\ApprovalContext\ApprovalContextRendererInterface;
use App\Model\Entity\WorkflowInstance;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/**
 * Provides rich display context for Activity authorization approvals
 * shown in the unified approvals queue.
 */
class ActivitiesApprovalContextRenderer implements ApprovalContextRendererInterface
{
    /**
     * @inheritDoc
     */
    public function canRender(WorkflowInstance $instance): bool
    {
        return in_array($instance->entity_type, ['Activities', 'Activities.Authorizations'], true);
    }

    /**
     * @inheritDoc
     */
    public function render(WorkflowInstance $instance): ApprovalContext
    {
        $context = $instance->context ?? [];
        $triggerData = $context['trigger'] ?? $context['event'] ?? $context;

        $activityId = $triggerData['activityId'] ?? null;
        $memberId = $triggerData['memberId'] ?? null;
        $isRenewal = $triggerData['isRenewal'] ?? false;
        $authorizationId = $triggerData['authorizationId'] ?? $instance->entity_id;

        $activityName = $this->loadActivityName($activityId);
        $requesterName = $this->loadRequesterName($memberId);

        $title = $isRenewal
            ? __('Renewal: {0}', $activityName)
            : __('Authorization: {0}', $activityName);

        $description = $requesterName
            ? __("{0} requests authorization for {1}", $requesterName, $activityName)
            : __("Authorization request for {0}", $activityName);

        $fields = [
            ['label' => __('Activity'), 'value' => $activityName],
            ['label' => __('Requester'), 'value' => $requesterName ?? __('Unknown')],
            ['label' => __('Type'), 'value' => $isRenewal ? __('Renewal') : __('New Request')],
        ];

        $entityUrl = $this->buildEntityUrl($authorizationId);

        return new ApprovalContext(
            title: $title,
            description: $description,
            fields: $fields,
            entityUrl: $entityUrl,
            icon: 'bi-shield-check',
            requester: $requesterName,
        );
    }

    /**
     * Load activity name by ID.
     *
     * @param int|string|null $activityId Activity ID.
     * @return string
     */
    private function loadActivityName(int|string|null $activityId): string
    {
        if ($activityId === null) {
            return __('Unknown Activity');
        }

        try {
            $activity = TableRegistry::getTableLocator()
                ->get('Activities.Activities')
                ->find()
                ->where(['id' => (int)$activityId])
                ->select(['name'])
                ->first();

            if ($activity) {
                return $activity->name;
            }
        } catch (\Throwable $e) {
            // Fallback on DB/table errors
        }

        return __('Unknown Activity');
    }

    /**
     * Load requester SCA name by member ID.
     *
     * @param int|string|null $memberId Member ID.
     * @return string|null
     */
    private function loadRequesterName(int|string|null $memberId): ?string
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
     * Build a URL to the authorization entity view.
     *
     * @param int|string|null $authorizationId Authorization ID.
     * @return string|null
     */
    private function buildEntityUrl(int|string|null $authorizationId): ?string
    {
        if ($authorizationId === null) {
            return null;
        }

        try {
            return Router::url([
                'plugin' => 'Activities',
                'controller' => 'Authorizations',
                'action' => 'view',
                $authorizationId,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
