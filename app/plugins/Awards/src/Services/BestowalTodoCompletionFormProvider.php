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

/**
 * Supplies Awards-owned completion UI and apply logic for bestowal gathering todos.
 */
class BestowalTodoCompletionFormProvider implements ActionItemCompletionFormProviderInterface
{
    use LocatorAwareTrait;

    private BestowalUpdateService $bestowalUpdateService;

    /**
     * @param \Awards\Services\BestowalUpdateService|null $bestowalUpdateService Shared bestowal update service.
     */
    public function __construct(?BestowalUpdateService $bestowalUpdateService = null)
    {
        $this->bestowalUpdateService = $bestowalUpdateService ?? new BestowalUpdateService();
    }

    /**
     * @inheritDoc
     */
    public function canHandle(ActionItem $item): bool
    {
        if ((string)$item->entity_type !== Bestowal::ACTION_ITEM_ENTITY_TYPE) {
            return false;
        }

        return $this->gatheringRequirementConfig($item) !== null;
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
                        'Choose a future event or court using the same options available on the bestowal edit form.',
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

        $gatheringId = $this->positiveIntOrNull($data['bestowal_gathering_id'] ?? $data['gathering_id'] ?? null);
        if ($gatheringId === null) {
            return $this->validateCompletion($item);
        }

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
        );
        if (!($result['success'] ?? false)) {
            return new ServiceResult(false, (string)($result['error'] ?? 'Failed to assign the gathering.'));
        }

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
        foreach ($item->getRequiredFieldConfigs() as $fieldConfig) {
            if (($fieldConfig['field'] ?? null) === BestowalTodoTemplateItem::REQUIRED_FIELD_GATHERING) {
                return $fieldConfig;
            }
        }

        return BestowalTodoTemplateItem::getDefaultRequiredFieldConfigForSourceRef($item->source_ref);
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

        /** @var \Awards\Model\Entity\Bestowal|null $bestowal */
        $bestowal = $this->fetchTable('Awards.Bestowals')->find()
            ->where(['Bestowals.id' => (int)$item->entity_id])
            ->contain([
                'Gatherings',
                'Recommendations' => function ($query) {
                    return $query->select(['id', 'award_id', 'member_id', 'bestowal_id']);
                },
            ])
            ->first();

        return $bestowal;
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
