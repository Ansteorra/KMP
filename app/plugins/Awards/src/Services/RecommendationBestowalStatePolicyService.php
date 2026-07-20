<?php
declare(strict_types=1);

namespace Awards\Services;

use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;

/**
 * Defines the recommendation state boundary where bestowals take over.
 */
class RecommendationBestowalStatePolicyService
{
    use LocatorAwareTrait;

    public const HANDOFF_STATE = 'Need to Schedule';

    public const NO_ACTION_STATE = 'No Action';

    /**
     * @var array<int, string>
     */
    private const BESTOWAL_MANAGED_STATES = [
        'Scheduled',
        'Given',
        'Announced Not Given',
    ];

    /**
     * @var array<string, string>
     */
    private const EXPECTED_SYNC_MAPPINGS = [
        'Created' => self::HANDOFF_STATE,
        'Gathering Assigned' => self::HANDOFF_STATE,
        'Scroll Notified' => self::HANDOFF_STATE,
        'Scroll Ready' => self::HANDOFF_STATE,
        'Court Pending' => self::HANDOFF_STATE,
        'Court Scheduled' => 'Scheduled',
        'Ready for Court' => 'Scheduled',
        'Given' => 'Given',
        'Announced Not Given' => 'Announced Not Given',
    ];

    /**
     * @var array<string, string>
     */
    private const EXPECTED_UNWIND_MAPPINGS = [
        'Cancelled' => 'King Approved',
    ];

    /**
     * Whether a recommendation state starts the bestowal handoff.
     */
    public function isHandoffState(string $state): bool
    {
        return $state === self::HANDOFF_STATE;
    }

    /**
     * Whether a recommendation state is only writable through bestowal sync.
     */
    public function isBestowalManagedState(string $state): bool
    {
        return in_array($state, self::BESTOWAL_MANAGED_STATES, true);
    }

    /**
     * @return array<int, string>
     */
    public function getBestowalManagedStates(): array
    {
        return self::BESTOWAL_MANAGED_STATES;
    }

    /**
     * Reject direct user-facing transitions into states owned by bestowals.
     */
    public function assertUserCanTargetRecommendationState(string $state): void
    {
        if (!$this->isBestowalManagedState($state)) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                'Recommendation state "%s" is managed by the bestowal workflow. Move to "%s" to create a bestowal.',
                $state,
                self::HANDOFF_STATE,
            ),
        );
    }

    /**
     * Verify reference data still routes bestowal states back to the expected recommendation states.
     *
     * The legacy bestowal state machine has been removed in favour of the lifecycle_status
     * model, so there are no bestowal-state sync mappings left to validate. Retained as a
     * no-op for call-site compatibility.
     */
    public function assertBestowalSyncMappingsConfigured(?Table $bestowalStatesTable = null): void
    {
    }

    /**
     * Remove bestowal-managed states from user-facing recommendation dropdowns.
     *
     * @param array<string, array<int|string, mixed>> $statusList Grouped status options.
     * @return array<string, array<int|string, mixed>>
     */
    public function filterUserTargetStatusList(array $statusList, ?string $currentState = null): array
    {
        $filteredStatusList = [];
        foreach ($statusList as $status => $states) {
            $filteredStates = [];
            foreach ($states as $key => $label) {
                $state = is_string($label) ? $label : (string)$key;
                if ($this->isBestowalManagedState($state) && $state !== $currentState) {
                    continue;
                }

                $filteredStates[$key] = $label;
            }

            if ($filteredStates !== []) {
                $filteredStatusList[$status] = $filteredStates;
            }
        }

        return $filteredStatusList;
    }
}
