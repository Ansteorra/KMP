<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\TimezoneHelper;
use Awards\Model\Entity\Bestowal;

/**
 * Formats already-authorized agenda data for printing without changing stored notes.
 */
class CourtAgendaPrintService
{
    use BestowalNotesSupportTrait;

    /**
     * Call only after BestowalFieldAccessService::redactAgendaViewModel().
     *
     * @param array<string, mixed> $viewModel Redacted agenda view model.
     * @return array<string, mixed>
     */
    public function prepare(array $viewModel): array
    {
        $gathering = $viewModel['agenda']->gathering;
        $dates = [];
        foreach ([$gathering->start_date, $gathering->end_date] as $date) {
            if ($date !== null) {
                $dates[] = TimezoneHelper::toUserTimezone($date, gathering: $gathering)->format('m/d/Y');
            }
        }
        $viewModel['eventDates'] = implode(' - ', array_unique($dates));

        usort($viewModel['segments'], static function (array $left, array $right): int {
            $leftStart = $left['entity']->gathering_scheduled_activity?->start_datetime;
            $rightStart = $right['entity']->gathering_scheduled_activity?->start_datetime;
            if ($leftStart === null) {
                return $rightStart === null ? 0 : 1;
            }
            if ($rightStart === null) {
                return -1;
            }

            return $leftStart->getTimestamp() <=> $rightStart->getTimestamp();
        });

        foreach ($viewModel['segments'] as &$segmentData) {
            $segment = $segmentData['entity'];
            $activity = $segment->gathering_scheduled_activity;
            $start = $activity?->start_datetime;
            $segmentData['printHeading'] = (string)$segment->name;
            if ($start !== null) {
                $localStart = TimezoneHelper::toUserTimezone($start, gathering: $gathering);
                $slot = $localStart->format('m/d/Y g:i A T');
                if ($activity->end_datetime !== null && $activity->has_end_time !== false) {
                    $end = TimezoneHelper::toUserTimezone($activity->end_datetime, gathering: $gathering);
                    $endFormat = $end->format('Y-m-d') === $localStart->format('Y-m-d')
                        ? 'g:i A T'
                        : 'm/d/Y g:i A T';
                    $slot .= ' - ' . $end->format($endFormat);
                }
                $segmentData['printHeading'] .= ' - ' . $slot;
            }

            foreach ($segmentData['items'] as &$itemData) {
                $item = $itemData['entity'];
                $bestowal = $item->bestowal;
                $itemData['heraldNotes'] = $this->heraldNotes($bestowal);
                $itemData['callIntoCourt'] = $this->optionalText($bestowal->call_into_court ?? null);
                $itemData['printNotes'] = [];
                $notes = [
                    __('Call into court:') => $itemData['callIntoCourt'],
                    __('Court availability:') => $bestowal->court_availability ?? null,
                    __('Notify:') => $bestowal->person_to_notify ?? null,
                    __('Agenda:') => $item->presentation_notes,
                    __('Print:') => $item->print_notes,
                    __('Notes:') => $itemData['heraldNotes'],
                    __('Noble:') => $bestowal->noble_notes ?? null,
                ];
                foreach ($notes as $label => $value) {
                    $value = $this->optionalText($value);
                    if ($value !== '') {
                        $itemData['printNotes'][] = ['label' => $label, 'value' => $value];
                    }
                }
                $itemData['recommendationNotes'] = [];
                foreach ($this->recommendationNotes($bestowal) as $label => $value) {
                    $itemData['recommendationNotes'][] = ['label' => $label, 'value' => $value];
                }

                $nobleNotes = $this->normalize((string)($bestowal->noble_notes ?? ''));
                $reasons = [];
                foreach ($itemData['reasons'] as $reason) {
                    $reason = $this->optionalText($reason);
                    $normalized = $this->normalize($reason);
                    if ($reason !== '' && !str_contains($nobleNotes, $normalized)) {
                        $reasons[$normalized] = $reason;
                    }
                }
                $itemData['reasons'] = array_values($reasons);
                $itemData['specialties'] = array_values(array_unique(array_filter(array_map(
                    $this->optionalText(...),
                    $itemData['specialties'],
                ))));
            }
            unset($itemData);
        }
        unset($segmentData);

        return $viewModel;
    }

    /**
     * Remove generated recipient/award/call summaries while preserving authored instructions.
     *
     * @param \Awards\Model\Entity\Bestowal|null $bestowal Authorized bestowal.
     * @return string
     */
    private function heraldNotes(?Bestowal $bestowal): string
    {
        if ($bestowal === null) {
            return '';
        }

        $awardLabels = array_map($this->formatAwardLabel(...), $bestowal->recommendations ?? []);
        $awardLabels[] = $this->formatBestowalAwardLabel($bestowal);
        $lines = [];
        $removedSummary = false;
        foreach (preg_split('/\R/u', (string)$bestowal->herald_notes) as $line) {
            $line = trim($line);
            if (preg_match('/^.+?\h+[—–]\h+(?:Specialty|Call):/u', $line)) {
                $parts = preg_split('/\h+[—–]\h+/u', $line);
                array_shift($parts);
                $line = implode(' — ', array_filter(
                    $parts,
                    static fn(string $part): bool => !preg_match('/^(?:Specialty|Call):/u', $part),
                ));
                $removedSummary = true;
                if ($line === '') {
                    continue;
                }
            } elseif (in_array($line, $awardLabels, true) || preg_match('/^(?:Specialty|Call):/u', $line)) {
                $removedSummary = true;
                continue;
            }
            $lines[] = $line;
        }
        if ($removedSummary) {
            $names = [trim((string)$bestowal->member_sca_name), $bestowal->getRecipientName()];
            $lines = array_filter($lines, static fn(string $line): bool => !in_array($line, $names, true));
        }

        return $this->optionalText(implode("\n", $lines));
    }

    /**
     * Summarize only the recommendation data retained after field authorization.
     *
     * @param \Awards\Model\Entity\Bestowal|null $bestowal Authorized bestowal.
     * @return array<string, string>
     */
    private function recommendationNotes(?Bestowal $bestowal): array
    {
        $recommendations = $bestowal->recommendations ?? [];
        if ($recommendations === []) {
            return [];
        }

        $count = count($recommendations);
        $notes = [__('Recommendation count:') => (string)$count];
        if (!$bestowal->hasDifferentRecommendationAward()) {
            return $notes;
        }

        $awards = [];
        foreach ($recommendations as $recommendation) {
            if ($recommendation->award_id === null) {
                continue;
            }
            $name = trim((string)($recommendation->award->name ?? ''));
            if ($name === '') {
                $name = trim((string)($recommendation->award->abbreviation ?? ''));
            }
            $awards[(int)$recommendation->award_id] = $name !== ''
                ? $name
                : __('Award #{0}', (int)$recommendation->award_id);
        }
        $label = $count === 1
            ? __('Original recommendation was for:')
            : __('Original recommendations were for:');
        $notes[$label] = implode(', ', $awards);

        return $notes;
    }

    /**
     * @param mixed $value Optional field value.
     * @return string
     */
    private function optionalText(mixed $value): string
    {
        $text = trim((string)$value);

        return in_array(strtolower($text), ['not set', 'no specialties available'], true) ? '' : $text;
    }

    /**
     * @param string $text Note text.
     * @return string
     */
    private function normalize(string $text): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }
}
