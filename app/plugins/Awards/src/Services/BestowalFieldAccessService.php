<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\CourtAgendaItem;

/**
 * Applies field-level authorization to protected bestowal data.
 */
class BestowalFieldAccessService
{
    public const ACTION_ACCESS_HERALD_NOTES = 'accessHeraldNotes';

    public const ACTION_ACCESS_CROWN_FIELDS = 'accessCrownFields';

    public const HERALD_MUTATION_FIELDS = [
        'herald_notes',
        'heraldNotes',
    ];

    public const CROWN_MUTATION_FIELDS = [
        'noble_notes',
        'nobleNotes',
        'reason_summary',
        'reasonSummary',
        'link_recommendation_ids',
        'unlink_recommendation_ids',
    ];

    /**
     * @return array{heraldNotes: bool, crownFields: bool}
     */
    public function accessFor(KmpIdentityInterface $identity, Bestowal $bestowal): array
    {
        $crownFields = $identity->checkCan(self::ACTION_ACCESS_CROWN_FIELDS, $bestowal);

        return [
            'heraldNotes' => $crownFields
                || $identity->checkCan(self::ACTION_ACCESS_HERALD_NOTES, $bestowal),
            'crownFields' => $crownFields,
        ];
    }

    /**
     * Remove protected fields and associations before rendering or serializing.
     *
     * @return array{heraldNotes: bool, crownFields: bool}
     */
    public function redact(Bestowal $bestowal, KmpIdentityInterface $identity): array
    {
        $access = $this->accessFor($identity, $bestowal);

        if (!$access['heraldNotes']) {
            unset($bestowal->herald_notes, $bestowal->herald_notes_preview);
        }
        if (!$access['crownFields']) {
            unset(
                $bestowal->noble_notes,
                $bestowal->reason_summary,
                $bestowal->recommendation_reasons,
                $bestowal->recommendations,
                $bestowal->bestowal_recommendations,
                $bestowal->primary_recommendation,
            );
        }

        return $access;
    }

    /**
     * Return protected submitted fields the identity is not allowed to mutate.
     *
     * @param array<string, mixed> $data Submitted mutation data.
     * @return list<string>
     */
    public function deniedMutationFields(
        array $data,
        KmpIdentityInterface $identity,
        Bestowal $bestowal,
    ): array {
        $access = $this->accessFor($identity, $bestowal);
        $submittedFields = array_keys($data);
        $denied = [];

        if (!$access['heraldNotes']) {
            $denied = array_merge($denied, array_intersect(self::HERALD_MUTATION_FIELDS, $submittedFields));
        }
        if (!$access['crownFields']) {
            $denied = array_merge($denied, array_intersect(self::CROWN_MUTATION_FIELDS, $submittedFields));
        }

        return array_values(array_unique($denied));
    }

    /**
     * Apply bestowal visibility and field permissions to builder and print data.
     *
     * @param array<string, mixed> $viewModel Court agenda view model.
     * @return array<string, mixed>
     */
    public function redactAgendaViewModel(array $viewModel, KmpIdentityInterface $identity): array
    {
        foreach ($viewModel['segments'] ?? [] as $segmentIndex => $segmentData) {
            foreach ($segmentData['items'] ?? [] as $itemIndex => $itemData) {
                $bestowal = $itemData['entity']->bestowal ?? null;
                if (
                    !$bestowal instanceof Bestowal
                    && empty($itemData['entity']->bestowal_id)
                    && $itemData['entity']->item_type !== CourtAgendaItem::TYPE_BESTOWAL
                ) {
                    continue;
                }

                if (!$bestowal instanceof Bestowal || !$identity->checkCan('view', $bestowal)) {
                    $itemData = $this->blockedAgendaItem($itemData);
                } else {
                    $access = $this->redact($bestowal, $identity);
                    if (!$access['crownFields']) {
                        $itemData['reasons'] = [];
                    }
                }
                $viewModel['segments'][$segmentIndex]['items'][$itemIndex] = $itemData;
            }

            $eligible = [];
            foreach ($segmentData['eligibleBestowals'] ?? [] as $bestowal) {
                if ($bestowal instanceof Bestowal && $identity->checkCan('placeOnAgenda', $bestowal)) {
                    $this->redact($bestowal, $identity);
                    $eligible[] = $bestowal;
                }
            }
            $viewModel['segments'][$segmentIndex]['eligibleBestowals'] = $eligible;
            if (isset($segmentData['entity'])) {
                $segmentData['entity']->court_agenda_items = array_column(
                    $viewModel['segments'][$segmentIndex]['items'] ?? [],
                    'entity',
                );
            }
        }

        $unscheduled = [];
        foreach ($viewModel['unscheduledBestowals'] ?? [] as $bestowalData) {
            $bestowal = $bestowalData['entity'] ?? null;
            if ($bestowal instanceof Bestowal && $identity->checkCan('placeOnAgenda', $bestowal)) {
                $this->redact($bestowal, $identity);
                $unscheduled[] = $bestowalData;
            }
        }
        $viewModel['unscheduledBestowals'] = $unscheduled;

        return $viewModel;
    }

    /**
     * Retain only placement and timing for a bestowal the viewer cannot read.
     *
     * @param array<string, mixed> $itemData Agenda item view data.
     * @return array<string, mixed>
     */
    private function blockedAgendaItem(array $itemData): array
    {
        $item = new CourtAgendaItem($itemData['entity']->extract([
            'id',
            'court_agenda_segment_id',
            'item_type',
            'sort_order',
            'estimated_minutes',
            'duration_locked',
        ]), ['guard' => false]);

        return [
            'entity' => $item,
            'detailsBlocked' => true,
            'label' => __('Court Item  (details blocked by security)'),
            'awardLabel' => '',
            'durationHint' => '',
            'reasons' => [],
            'specialties' => [],
            'moveSegmentOptions' => [],
            'minutes' => $itemData['minutes'],
        ];
    }
}
