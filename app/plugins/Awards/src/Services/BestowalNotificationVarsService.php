<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use App\KMP\TimezoneHelper;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Throwable;

/**
 * Prepares email template variables for bestowal notification workflows.
 */
class BestowalNotificationVarsService
{
    use BestowalNotesSupportTrait;
    use LocatorAwareTrait;

    private Table $bestowalsTable;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     */
    public function __construct(?Table $bestowalsTable = null)
    {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
    }

    /**
     * Prepare notification variables for a bestowal lifecycle email.
     *
     * @param int $bestowalId Bestowal ID.
     * @param string $eventType Workflow event type (created, stateChanged, cancelled).
     * @param array<string, mixed> $context Optional workflow context overrides.
     * @return array<string, mixed>
     */
    public function prepare(int $bestowalId, string $eventType, array $context = []): array
    {
        if ($bestowalId <= 0) {
            return [
                'success' => false,
                'error' => 'Bestowal ID must be greater than zero.',
                'data' => [],
            ];
        }

        try {
            $bestowal = $this->bestowalsTable->get($bestowalId, contain: [
                'Members',
                'Gatherings',
                'GatheringScheduledActivities',
                'Awards' => ['Levels'],
                'Recommendations' => ['Awards' => ['Levels']],
                'PrimaryRecommendation' => ['Awards' => ['Levels']],
            ]);

            $member = $bestowal->member ?? null;
            $gathering = $bestowal->gathering ?? null;
            $recommendations = $bestowal->recommendations ?? [];
            if ($recommendations === [] && $bestowal->primary_recommendation !== null) {
                $recommendations = [$bestowal->primary_recommendation];
            }

            $awardLabels = [];
            if ($bestowal->hasValue('award')) {
                $awardLabels[] = $this->formatBestowalAwardLabel($bestowal);
            } else {
                foreach ($recommendations as $recommendation) {
                    $awardLabels[] = $this->formatAwardLabel($recommendation);
                }
            }
            $awardLabels = array_values(array_unique(array_filter($awardLabels)));

            $vars = [
                'bestowalId' => (int)$bestowal->id,
                'eventType' => $eventType,
                'memberId' => $bestowal->member_id !== null ? (int)$bestowal->member_id : null,
                'memberScaName' => $member !== null
                    ? (string)$member->sca_name
                    : (string)$bestowal->member_sca_name,
                'lifecycleStatus' => (string)$bestowal->lifecycle_status,
                'source' => (string)$bestowal->source,
                'gatheringId' => $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null,
                'gatheringName' => $gathering !== null ? (string)$gathering->name : '',
                'gatheringStartDate' => $gathering !== null && $gathering->start_date !== null
                    ? TimezoneHelper::formatDate($gathering->start_date)
                    : '',
                'gatheringEndDate' => $gathering !== null && $gathering->end_date !== null
                    ? TimezoneHelper::formatDate($gathering->end_date)
                    : '',
                'courtSlotLabel' => (new BestowalCourtSlotService())->formatCourtSlotDisplay($bestowal),
                'courtSlotStartAt' => !empty($bestowal->roaming_court)
                    ? ''
                    : ($bestowal->gathering_scheduled_activity !== null
                        && $bestowal->gathering_scheduled_activity->start_datetime !== null
                        ? TimezoneHelper::formatDateTime($bestowal->gathering_scheduled_activity->start_datetime)
                        : ''),
                'stackRank' => (int)$bestowal->stack_rank,
                'bestowedAt' => $bestowal->bestowed_at !== null
                    ? TimezoneHelper::formatDateTime($bestowal->bestowed_at)
                    : '',
                'callIntoCourt' => (string)($bestowal->call_into_court ?? ''),
                'courtAvailability' => (string)($bestowal->court_availability ?? ''),
                'personToNotify' => (string)($bestowal->person_to_notify ?? ''),
                'specialty' => (string)($bestowal->specialty ?? ''),
                'nobleNotes' => (string)($bestowal->noble_notes ?? ''),
                'heraldNotes' => (string)($bestowal->herald_notes ?? ''),
                'closeReason' => (string)($bestowal->close_reason ?? ''),
                'awardLabels' => $awardLabels,
                'awardSummary' => implode(', ', $awardLabels),
                'recommendationIds' => array_map(
                    'intval',
                    array_column($recommendations, 'id'),
                ),
                'siteAdminSignature' => StaticHelpers::getAppSetting(
                    'Email.SiteAdminSignature',
                    '',
                    null,
                    true,
                ),
            ];

            if (isset($context['previousState'])) {
                $vars['previousState'] = (string)$context['previousState'];
            }
            if (isset($context['newState'])) {
                $vars['newState'] = (string)$context['newState'];
            }
            if (isset($context['closeReason']) && $vars['closeReason'] === '') {
                $vars['closeReason'] = (string)$context['closeReason'];
            }

            return [
                'success' => true,
                'data' => $vars,
            ];
        } catch (Throwable $e) {
            Log::error('Bestowal notification vars preparation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Prepare vars specifically for bestowal creation notifications.
     *
     * @param int $bestowalId Bestowal ID.
     * @return array<string, mixed>
     */
    public function prepareCreatedVars(int $bestowalId): array
    {
        return $this->prepare($bestowalId, 'created');
    }

    /**
     * Prepare vars specifically for bestowal state-change notifications.
     *
     * @param int $bestowalId Bestowal ID.
     * @param string|null $previousState Previous bestowal state.
     * @param string|null $newState New bestowal state.
     * @return array<string, mixed>
     */
    public function prepareStateChangedVars(
        int $bestowalId,
        ?string $previousState = null,
        ?string $newState = null,
    ): array {
        return $this->prepare($bestowalId, 'stateChanged', array_filter([
            'previousState' => $previousState,
            'newState' => $newState,
        ]));
    }

    /**
     * Prepare vars specifically for bestowal cancellation notifications.
     *
     * @param int $bestowalId Bestowal ID.
     * @param string|null $closeReason Cancellation reason.
     * @return array<string, mixed>
     */
    public function prepareCancelledVars(int $bestowalId, ?string $closeReason = null): array
    {
        return $this->prepare($bestowalId, 'cancelled', array_filter([
            'closeReason' => $closeReason,
        ]));
    }
}
