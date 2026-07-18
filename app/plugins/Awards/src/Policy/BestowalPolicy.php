<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Model\Entity\BaseEntity;
use App\Model\Table\WorkflowApprovalsTable;
use App\Policy\BasePolicy;
use App\Services\ActionItems\ActionItemAssigneeResolver;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\ORM\TableRegistry;

/**
 * Authorization policy for Bestowal entities in the Awards plugin.
 *
 * @method bool canView(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canEdit(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity, mixed ...$optionalArgs)
 * @see \App\Policy\BasePolicy Base authorization functionality
 */
class BestowalPolicy extends BasePolicy
{
    /**
     * Authorize gathering-scoped bestowal grid data.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canGatheringBestowalsGridData(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        return $this->canViewGatheringBestowals($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize single bestowal state updates.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canUpdateState(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize single bestowal edit modal loading.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canTurboEditForm(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize court-slot lookup data used by bestowal edit forms.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canCourtSlotsForGathering(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize gathering autocomplete used by single bestowal edit forms and
     * the approval Respond modal.
     *
     * Besides bestowal editors and court schedulers, a member who is currently
     * an eligible approver on a pending approval for the recommendation carried
     * in `approval_context_recommendation_id` may use the lookup — the approval
     * modal's Bestowal Gathering field is theirs to fill.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canGatheringsForBestowalAutoComplete(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        if (
            $this->canEdit($user, $entity, ...$optionalArgs)
            || $this->canManageCourtSchedule($user, $entity, ...$optionalArgs)
        ) {
            return true;
        }

        return $this->isPendingApproverForRecommendation(
            $user,
            (int)($entity->get('approval_context_recommendation_id') ?? 0),
        );
    }

    /**
     * Whether the member is an eligible approver on a pending approval for a recommendation.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param int $recommendationId Recommendation ID from the lookup context
     * @return bool
     */
    protected function isPendingApproverForRecommendation(KmpIdentityInterface $user, int $recommendationId): bool
    {
        if ($recommendationId <= 0) {
            return false;
        }
        $memberId = (int)$user->getIdentifier();
        if ($memberId <= 0) {
            return false;
        }

        $instanceIds = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->select(['workflow_instance_id'])
            ->where([
                'recommendation_id' => $recommendationId,
                'workflow_instance_id IS NOT' => null,
                'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            ])
            ->enableHydration(false)
            ->all()
            ->extract('workflow_instance_id')
            ->map(fn($id): int => (int)$id)
            ->toList();
        if ($instanceIds === []) {
            return false;
        }

        return WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember($memberId, $instanceIds) !== [];
    }

    /**
     * Authorize bulk gathering assignment for bestowals.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canBulkAssignGathering(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canManageCourtSchedule($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize assigning the gathering required by an eligible Event Scheduled to-do.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context; first arg is the action item
     * @return bool True if authorized
     */
    public function canAssignRequiredTodoGathering(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        if ($this->canManageCourtSchedule($user, $entity, ...$optionalArgs)) {
            return true;
        }

        $item = $optionalArgs[0] ?? null;
        if (!$item instanceof ActionItem || !$item->isOpen()) {
            return false;
        }
        if (
            (string)$item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE
            || (int)$item->entity_id !== (int)$entity->id
        ) {
            return false;
        }
        if (!$this->actionItemRequiresGathering($item)) {
            return false;
        }

        $memberId = (int)$user->getIdentifier();
        if ($memberId <= 0) {
            return false;
        }

        return (new ActionItemAssigneeResolver())->isMemberEligible($item, $memberId);
    }

    /**
     * Authorize assigning the court slot required by an eligible Added to Agenda to-do.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context; first arg is the action item
     * @return bool True if authorized
     */
    public function canAssignRequiredTodoCourtSlot(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        if ($this->canManageCourtSchedule($user, $entity, ...$optionalArgs)) {
            return true;
        }

        $item = $optionalArgs[0] ?? null;
        if (!$item instanceof ActionItem || !$item->isOpen()) {
            return false;
        }
        if (
            (string)$item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE
            || (int)$item->entity_id !== (int)$entity->id
        ) {
            return false;
        }
        if (!$this->actionItemRequiresCourtSlot($item)) {
            return false;
        }

        $memberId = (int)$user->getIdentifier();
        if ($memberId <= 0) {
            return false;
        }

        return (new ActionItemAssigneeResolver())->isMemberEligible($item, $memberId);
    }

    /**
     * Authorize bestowal cancellation.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canCancel(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize ad-hoc bestowal creation.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canAdHoc(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can view bestowals for a specific gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$args Additional authorization context (typically gathering entity)
     * @return bool True if authorized
     */
    public function canViewGatheringBestowals(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can prepare scrolls for bestowals.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canPrepareScrolls(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can manage court schedule for bestowals.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canManageCourtSchedule(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check whether the user may access herald notes.
     */
    public function canAccessHeraldNotes(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * Check whether the user may access Crown-only bestowal fields.
     */
    public function canAccessCrownFields(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * Whether the action item carries, or defaults to, the bestowal gathering requirement.
     *
     * @param \App\Model\Entity\ActionItem $item Action item
     * @return bool
     */
    private function actionItemRequiresGathering(ActionItem $item): bool
    {
        return $this->actionItemRequiresField($item, BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING);
    }

    /**
     * Whether the action item carries, or defaults to, the bestowal court slot requirement.
     *
     * @param \App\Model\Entity\ActionItem $item Action item
     * @return bool
     */
    private function actionItemRequiresCourtSlot(ActionItem $item): bool
    {
        return $this->actionItemRequiresField($item, BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT);
    }

    /**
     * @param \App\Model\Entity\ActionItem $item Action item
     * @param string $requiredField Required field key
     * @return bool
     */
    private function actionItemRequiresField(ActionItem $item, string $requiredField): bool
    {
        $fieldConfigs = $item->getRequiredFieldConfigs();
        $defaultConfig = BestowalTodoTemplateItem::getDefaultRequiredFieldConfigForSourceRef($item->source_ref);
        if ($fieldConfigs === [] && $defaultConfig !== null) {
            $fieldConfigs[] = $defaultConfig;
        }

        foreach ($fieldConfigs as $fieldConfig) {
            if (($fieldConfig['field'] ?? null) === $requiredField) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user can view hidden bestowal states.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canViewHidden(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}
