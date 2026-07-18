<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\KmpIdentityInterface;
use Awards\Model\Entity\Bestowal;

/**
 * Applies field-level authorization to protected bestowal data.
 */
class BestowalFieldAccessService
{
    public const ACTION_ACCESS_HERALD_NOTES = 'accessHeraldNotes';

    public const ACTION_ACCESS_CROWN_FIELDS = 'accessCrownFields';

    private const HERALD_MUTATION_FIELDS = [
        'herald_notes',
        'heraldNotes',
    ];

    private const CROWN_MUTATION_FIELDS = [
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
     * Redact protected bestowal data embedded in a court agenda view model.
     *
     * @param array<string, mixed> $viewModel Court agenda view model.
     * @return array<string, mixed>
     */
    public function redactAgendaViewModel(array $viewModel, KmpIdentityInterface $identity): array
    {
        foreach ($viewModel['segments'] ?? [] as $segmentIndex => $segmentData) {
            foreach ($segmentData['items'] ?? [] as $itemIndex => $itemData) {
                $bestowal = $itemData['entity']->bestowal ?? null;
                if (!$bestowal instanceof Bestowal) {
                    continue;
                }

                $access = $this->redact($bestowal, $identity);
                if (!$access['crownFields']) {
                    $viewModel['segments'][$segmentIndex]['items'][$itemIndex]['reasons'] = [];
                }
            }

            foreach ($segmentData['eligibleBestowals'] ?? [] as $bestowal) {
                if ($bestowal instanceof Bestowal) {
                    $this->redact($bestowal, $identity);
                }
            }
        }

        foreach ($viewModel['unscheduledBestowals'] ?? [] as $bestowalData) {
            if (($bestowalData['entity'] ?? null) instanceof Bestowal) {
                $this->redact($bestowalData['entity'], $identity);
            }
        }

        return $viewModel;
    }
}
