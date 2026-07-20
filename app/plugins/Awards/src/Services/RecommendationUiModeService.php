<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Recommendation;

/**
 * Derives recommendation edit-form UI rules from explicit application modes.
 *
 * This service is intentionally UI-only. Workflow approvals, bestowal routing,
 * and legacy state transitions continue to use their existing services.
 */
class RecommendationUiModeService
{
    public const MODE_EDITABLE = 'editable_recommendation';
    public const MODE_NO_ACTION = 'no_action_close';
    public const MODE_BESTOWAL_SCHEDULING = 'bestowal_scheduling';
    public const MODE_BESTOWAL_GIVEN = 'bestowal_given';
    public const MODE_LINKED_BESTOWAL = 'linked_bestowal';

    /**
     * @return array<string, array<string, list<string>>>
     */
    public function buildStateRules(): array
    {
        $rules = [];
        foreach (Recommendation::getStates() as $state) {
            $rules[$state] = $this->rulesForState((string)$state);
        }

        return $rules;
    }

    /**
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation.
     * @return string UI mode.
     */
    public function modeForRecommendation(Recommendation $recommendation): string
    {
        if ($recommendation->isLockedByBestowal()) {
            return self::MODE_LINKED_BESTOWAL;
        }

        return $this->modeForState((string)$recommendation->state);
    }

    /**
     * @param string $state Recommendation state.
     * @return string UI mode.
     */
    public function modeForState(string $state): string
    {
        return match ($state) {
            RecommendationBestowalStatePolicyService::NO_ACTION_STATE => self::MODE_NO_ACTION,
            RecommendationBestowalStatePolicyService::HANDOFF_STATE,
            'Scheduled' => self::MODE_BESTOWAL_SCHEDULING,
            'Given' => self::MODE_BESTOWAL_GIVEN,
            default => self::MODE_EDITABLE,
        };
    }

    /**
     * @param string $state Recommendation state.
     * @return array<string, list<string>>
     */
    private function rulesForState(string $state): array
    {
        return match ($this->modeForState($state)) {
            self::MODE_NO_ACTION => [
                'Visible' => ['closeReasonBlockTarget'],
                'Required' => ['closeReasonTarget'],
            ],
            self::MODE_BESTOWAL_SCHEDULING => [
                'Visible' => ['planToGiveBlockTarget'],
                'Required' => ['planToGiveGatheringTarget'],
            ],
            self::MODE_BESTOWAL_GIVEN => [
                'Visible' => ['planToGiveBlockTarget', 'givenBlockTarget'],
                'Required' => ['givenDateTarget'],
            ],
            default => [],
        };
    }
}
