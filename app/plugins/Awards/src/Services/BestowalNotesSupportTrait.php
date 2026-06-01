<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;

/**
 * Builds noble and herald note text from linked recommendations.
 */
trait BestowalNotesSupportTrait
{
    /**
     * Summarize recommendation reasons for crown-facing notes.
     *
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Linked recommendations.
     * @return string|null
     */
    protected function buildNobleNotes(array $recommendations): ?string
    {
        $sections = [];
        foreach ($recommendations as $recommendation) {
            $reason = trim((string)($recommendation->reason ?? ''));
            if ($reason === '') {
                continue;
            }

            $sections[] = $this->formatAwardLabel($recommendation) . ': ' . $reason;
        }

        if ($sections === []) {
            return null;
        }

        return implode("\n\n", $sections);
    }

    /**
     * Build herald-oriented call text from linked recommendations.
     *
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Linked recommendations.
     * @param string $memberScaName Recipient SCA name.
     * @return string|null
     */
    protected function buildHeraldNotes(array $recommendations, string $memberScaName): ?string
    {
        $lines = [trim($memberScaName)];
        foreach ($recommendations as $recommendation) {
            $parts = [$this->formatAwardLabel($recommendation)];

            $specialty = trim((string)($recommendation->specialty ?? ''));
            if ($specialty !== '' && $specialty !== 'No specialties available') {
                $parts[] = 'Specialty: ' . $specialty;
            }

            $callIntoCourt = trim((string)($recommendation->call_into_court ?? ''));
            if ($callIntoCourt !== '' && $callIntoCourt !== 'Not Set') {
                $parts[] = 'Call: ' . $callIntoCourt;
            }

            $lines[] = implode(' — ', $parts);
        }

        if (count($lines) <= 1) {
            return count($lines) === 1 && $lines[0] !== '' ? $lines[0] : null;
        }

        return implode("\n", $lines);
    }

    /**
     * Format an award label with optional level for note text.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation with optional award contain.
     * @return string
     */
    protected function formatAwardLabel(Recommendation $recommendation): string
    {
        $award = $recommendation->award ?? null;
        if ($award === null) {
            return 'Award #' . (int)$recommendation->award_id;
        }

        $label = trim((string)($award->abbreviation ?? $award->name ?? ''));
        if ($label === '') {
            $label = 'Award #' . (int)$award->id;
        }

        $level = $award->level ?? null;
        if ($level !== null && !empty($level->name)) {
            $label .= ' (' . (string)$level->name . ')';
        }

        return $label;
    }

    /**
     * Format the award selected on a bestowal for note and notification text.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal with optional award contain.
     * @return string
     */
    protected function formatBestowalAwardLabel(Bestowal $bestowal): string
    {
        $award = $bestowal->award ?? null;
        if ($award === null) {
            return 'Award #' . (int)$bestowal->award_id;
        }

        $label = trim((string)($award->abbreviation ?? $award->name ?? ''));
        if ($label === '') {
            $label = 'Award #' . (int)$award->id;
        }

        $level = $award->level ?? null;
        if ($level !== null && !empty($level->name)) {
            $label .= ' (' . (string)$level->name . ')';
        }

        return $label;
    }
}
