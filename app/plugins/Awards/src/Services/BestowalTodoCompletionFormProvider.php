<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionForm;
use App\Services\ActionItems\ActionItemCompletionFormProviderInterface;
use App\Services\ServiceResult;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;
use RuntimeException;

/**
 * Supplies Awards-owned completion UI and apply logic for bestowal gathering todos.
 */
class BestowalTodoCompletionFormProvider implements ActionItemCompletionFormProviderInterface
{
    use LocatorAwareTrait;

    private BestowalUpdateService $bestowalUpdateService;

    private BestowalCourtSlotService $courtSlotService;

    /**
     * @var array<int, \Awards\Model\Entity\Bestowal|null>
     */
    private array $bestowalMemo = [];

    /**
     * @var array<int, \App\Model\Entity\ActionItem|null>
     */
    private array $eventScheduledTodoMemo = [];

    /**
     * @param \Awards\Services\BestowalUpdateService|null $bestowalUpdateService Shared bestowal update service.
     * @param \Awards\Services\BestowalCourtSlotService|null $courtSlotService Court slot helper.
     */
    public function __construct(
        ?BestowalUpdateService $bestowalUpdateService = null,
        ?BestowalCourtSlotService $courtSlotService = null,
    ) {
        $this->bestowalUpdateService = $bestowalUpdateService ?? new BestowalUpdateService();
        $this->courtSlotService = $courtSlotService ?? new BestowalCourtSlotService();
    }

    /**
     * @inheritDoc
     */
    public function canHandle(ActionItem $item): bool
    {
        if ((string)$item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE) {
            return false;
        }

        return $this->gatheringRequirementConfig($item) !== null
            || $this->courtSlotRequirementConfig($item) !== null
            || $this->hasPrerequisite($item);
    }

    /**
     * @inheritDoc
     */
    public function buildForm(ActionItem $item, KmpIdentityInterface $user): ?ActionItemCompletionForm
    {
        $bestowal = $this->loadBestowal($item);
        if ($bestowal === null) {
            return null;
        }

        if ($this->courtSlotRequirementConfig($item) !== null) {
            $options = $this->courtSlotService->buildEligibleOptionsForBestowal($bestowal, $user);
            if ($options === [] || !$this->validatePrerequisites($item, $bestowal)->success) {
                return null;
            }

            $currentValue = $this->courtSlotService->courtSessionSelectValue($bestowal);

            return new ActionItemCompletionForm(
                provider: BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_COURT_SLOT,
                title: __('Add Bestowal to Agenda'),
                description: __('Choose where this bestowal belongs before completing Added to Agenda.'),
                fields: [
                    [
                        'type' => 'select',
                        'name' => 'gathering_scheduled_activity_id',
                        'label' => __('Court Assignment'),
                        'required' => true,
                        'options' => $options,
                        'value' => $currentValue,
                        'help' => __(
                            'Choose Roaming Court, or choose a scheduled court activity that can give this award.',
                        ),
                    ],
                ],
                payload: [
                    'bestowalId' => (int)$bestowal->id,
                    'currentCourtSlot' => $currentValue,
                ],
            );
        }

        if ($this->gatheringRequirementConfig($item) === null) {
            return null;
        }

        $currentGatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
        $currentGatheringName = $bestowal->gathering->name ?? null;
        $lookupUrl = Router::url([
            'plugin' => 'Awards',
            'controller' => 'Bestowals',
            'action' => 'gatheringsForBestowalAutoComplete',
            (int)$bestowal->id,
        ]);

        return new ActionItemCompletionForm(
            provider: BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_GATHERING,
            title: __('Schedule Bestowal Event'),
            description: __('Select the gathering where this bestowal will be presented before completing the to-do.'),
            fields: [
                [
                    'type' => 'autocomplete',
                    'name' => 'bestowal_gathering_name',
                    'valueName' => 'bestowal_gathering_id',
                    'label' => __('Bestowal Gathering'),
                    'required' => true,
                    'url' => $lookupUrl,
                    'value' => $currentGatheringId,
                    'selection' => $currentGatheringId !== null && $currentGatheringName !== null
                        ? ['value' => (string)$currentGatheringId, 'text' => (string)$currentGatheringName]
                        : null,
                    'help' => __(
                        'Choose a gathering using the same options available on the bestowal edit form. ' .
                        'Use Include past gatherings to backdate scheduling.',
                    ),
                ],
            ],
            payload: [
                'bestowalId' => (int)$bestowal->id,
                'currentGatheringId' => $currentGatheringId,
            ],
        );
    }

    /**
     * @inheritDoc
     */
    public function applySubmission(
        ActionItem $item,
        array $data,
        int $actorId,
        KmpIdentityInterface $user,
    ): ServiceResult {
        $bestowal = $this->loadBestowal($item);
        if ($bestowal === null) {
            return new ServiceResult(false, 'Bestowal not found.');
        }

        if ($this->courtSlotRequirementConfig($item) !== null) {
            $rawActivityId = $data['gathering_scheduled_activity_id'] ?? $data['court_slot'] ?? null;
            if ($rawActivityId === null || $rawActivityId === '') {
                return $this->validateCompletion($item);
            }
            if (!$user->checkCan('assignRequiredTodoCourtSlot', $bestowal, $item)) {
                return new ServiceResult(false, 'You are not allowed to assign this bestowal to a court agenda.');
            }

            try {
                $this->courtSlotService->applyEligibleCourtSessionSelection($bestowal, $rawActivityId);
            } catch (RuntimeException $exception) {
                return new ServiceResult(false, $exception->getMessage());
            }

            $bestowal->modified_by = $actorId;
            if (!$this->fetchTable('Awards.Bestowals')->save($bestowal)) {
                return new ServiceResult(false, 'Failed to assign the bestowal to a court agenda.');
            }
            unset($this->bestowalMemo[(int)$bestowal->id]);

            return new ServiceResult(true, null, [
                'bestowalId' => (int)$bestowal->id,
                'courtSlot' => $this->courtSlotService->courtSessionSelectValue($bestowal),
            ]);
        }

        if ($this->gatheringRequirementConfig($item) === null) {
            return $this->validateCompletion($item);
        }

        $gatheringId = $this->positiveIntOrNull($data['bestowal_gathering_id'] ?? $data['gathering_id'] ?? null);
        if ($gatheringId === null) {
            return $this->validateCompletion($item);
        }
        $includePast = filter_var($data['include_past'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($bestowal->gathering_id !== null && (int)$bestowal->gathering_id === $gatheringId) {
            return $this->validateCompletion($item);
        }

        if (!$user->checkCan('assignRequiredTodoGathering', $bestowal, $item)) {
            return new ServiceResult(false, 'You are not allowed to assign a gathering for this bestowal.');
        }

        $result = $this->bestowalUpdateService->assignGathering(
            $this->fetchTable('Awards.Bestowals'),
            (int)$bestowal->id,
            $gatheringId,
            $actorId,
            !$includePast,
            false,
        );
        if (!($result['success'] ?? false)) {
            return new ServiceResult(false, (string)($result['error'] ?? 'Failed to assign the gathering.'));
        }
        unset($this->bestowalMemo[(int)$bestowal->id]);

        return new ServiceResult(true, null, $result['data'] ?? []);
    }

    /**
     * @inheritDoc
     */
    public function validateCompletion(ActionItem $item): ServiceResult
    {
        $bestowal = $this->loadBestowal($item);
        if ($bestowal === null) {
            return new ServiceResult(false, 'Bestowal not found.');
        }

        $prerequisiteResult = $this->validatePrerequisites($item, $bestowal);
        if (!$prerequisiteResult->success) {
            return $prerequisiteResult;
        }

        if ($this->gatheringRequirementConfig($item) === null && $this->courtSlotRequirementConfig($item) === null) {
            return new ServiceResult(true);
        }

        if ($this->courtSlotRequirementConfig($item) !== null) {
            return $this->courtSlotService->hasAgendaAssignment($bestowal)
                ? new ServiceResult(true)
                : new ServiceResult(
                    false,
                    'Assign this bestowal to Roaming Court or a scheduled court activity before completing ' .
                    'Added to Agenda.',
                );
        }

        return $bestowal->gathering_id !== null && (int)$bestowal->gathering_id > 0
            ? new ServiceResult(true)
            : new ServiceResult(false, 'Assign a gathering before completing this to-do.');
    }

    /**
     * Resolve gathering requirement metadata, including the built-in Event Scheduled fallback.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return array<string, mixed>|null
     */
    private function gatheringRequirementConfig(ActionItem $item): ?array
    {
        return $this->requiredFieldConfig($item, BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING);
    }

    /**
     * Resolve court-slot requirement metadata, including the built-in Added to Agenda fallback.
     *
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return array<string, mixed>|null
     */
    private function courtSlotRequirementConfig(ActionItem $item): ?array
    {
        return $this->requiredFieldConfig($item, BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT);
    }

    /**
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @param string $requiredField Required field key.
     * @return array<string, mixed>|null
     */
    private function requiredFieldConfig(ActionItem $item, string $requiredField): ?array
    {
        foreach ($item->getRequiredFieldConfigs() as $fieldConfig) {
            if (($fieldConfig['field'] ?? null) === $requiredField) {
                return $fieldConfig;
            }
        }

        $defaultConfig = BestowalTodoTemplateItem::getDefaultRequiredFieldConfigForSourceRef($item->source_ref);
        if (($defaultConfig['field'] ?? null) === $requiredField) {
            return $defaultConfig;
        }

        return null;
    }

    /**
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return bool
     */
    private function hasPrerequisite(ActionItem $item): bool
    {
        return (string)$item->source_ref === BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA;
    }

    /**
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return \App\Services\ServiceResult
     */
    private function validatePrerequisites(ActionItem $item, ?Bestowal $bestowal = null): ServiceResult
    {
        if (!$this->hasPrerequisite($item)) {
            return new ServiceResult(true);
        }

        $bestowal ??= $this->loadBestowal($item);
        if ($bestowal === null) {
            return new ServiceResult(false, 'Bestowal not found.');
        }

        $eventScheduled = $this->eventScheduledTodoForBestowal((int)$item->entity_id);
        if ($eventScheduled !== null && !$eventScheduled->isCompleted()) {
            return new ServiceResult(
                false,
                'Complete Event Scheduled before Added to Agenda can be completed.',
            );
        }

        return $bestowal->gathering_id !== null && (int)$bestowal->gathering_id > 0
            ? new ServiceResult(true)
            : new ServiceResult(false, 'Assign a gathering before Added to Agenda can be completed.');
    }

    /**
     * @param int $bestowalId Bestowal ID.
     * @return \App\Model\Entity\ActionItem|null
     */
    private function eventScheduledTodoForBestowal(int $bestowalId): ?ActionItem
    {
        if ($bestowalId <= 0) {
            return null;
        }
        if (array_key_exists($bestowalId, $this->eventScheduledTodoMemo)) {
            return $this->eventScheduledTodoMemo[$bestowalId];
        }

        /** @var \App\Model\Entity\ActionItem|null $item */
        $item = $this->fetchTable('ActionItems')->find()
            ->where([
                'ActionItems.entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
                'ActionItems.entity_id' => $bestowalId,
                'ActionItems.source_ref' => BestowalTodoTemplateItem::ITEM_KEY_EVENT_SCHEDULED,
            ])
            ->first();

        return $this->eventScheduledTodoMemo[$bestowalId] = $item;
    }

    /**
     * @param \App\Model\Entity\ActionItem $item Action item.
     * @return \Awards\Model\Entity\Bestowal|null
     */
    private function loadBestowal(ActionItem $item): ?Bestowal
    {
        if ((string)$item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE || (int)$item->entity_id <= 0) {
            return null;
        }
        $bestowalId = (int)$item->entity_id;
        if (array_key_exists($bestowalId, $this->bestowalMemo)) {
            return $this->bestowalMemo[$bestowalId];
        }

        /** @var \Awards\Model\Entity\Bestowal|null $bestowal */
        $bestowal = $this->fetchTable('Awards.Bestowals')->find()
            ->where(['Bestowals.id' => $bestowalId])
            ->contain([
                'Gatherings',
                'Recommendations' => function ($query) {
                    return $query->select(['id', 'award_id', 'member_id', 'bestowal_id']);
                },
            ])
            ->first();

        return $this->bestowalMemo[$bestowalId] = $bestowal;
    }

    /**
     * @param mixed $value Raw value.
     * @return int|null
     */
    private function positiveIntOrNull(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $value = (int)$value;

            return $value > 0 ? $value : null;
        }

        return null;
    }
}
