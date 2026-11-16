<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringScheduledActivity;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\CourtAgenda;
use Awards\Model\Entity\CourtAgendaItem;
use Awards\Model\Entity\CourtAgendaSegment;
use Awards\Model\Entity\Recommendation;
use Awards\Services\CourtAgendaPrintService;
use Cake\I18n\DateTime;

/**
 * Print formatting of authorized agenda data.
 */
class CourtAgendaPrintServiceTest extends BaseTestCase
{
    /** @return void */
    public function testPrintSortsScheduledCourtsChronologicallyWithStableTiesAndUnscheduledSectionsLast(): void
    {
        $cases = [
            ['Unscheduled first', null],
            ['Next day morning', '2026-10-25T12:00:00Z'],
            ['Simultaneous first', '2026-10-24T14:00:00Z'],
            ['Evening court', '2026-10-24T23:00:00Z'],
            ['Earliest court', '2026-10-24T12:00:00Z'],
            ['Simultaneous second', '2026-10-24T09:00:00-05:00'],
            ['Roaming Court', null],
        ];
        $model = $this->viewModel();
        $model['segments'] = [];
        foreach ($cases as [$name, $start]) {
            $model['segments'][] = [
                'entity' => new CourtAgendaSegment([
                    'name' => $name,
                    'gathering_scheduled_activity' => $start === null ? null : new GatheringScheduledActivity([
                        'start_datetime' => new DateTime($start),
                    ]),
                ]),
                'items' => [],
            ];
        }

        $printed = (new CourtAgendaPrintService())->prepare($model);
        $names = static fn(array $segment): string => $segment['entity']->name;
        $this->assertSame([
            'Earliest court', 'Simultaneous first', 'Simultaneous second', 'Evening court',
            'Next day morning', 'Unscheduled first', 'Roaming Court',
        ], array_map($names, $printed['segments']));
        $this->assertSame(array_column($cases, 0), array_map($names, $model['segments']));
    }

    /** @return void */
    public function testDatesUseGatheringTimezoneAndCollapseSingleDayRange(): void
    {
        $model = $this->viewModel();
        $service = new CourtAgendaPrintService();
        $printed = $service->prepare($model);

        $this->assertSame('10/23/2026 - 10/25/2026', $printed['eventDates']);
        $this->assertSame('Evening Court - 10/24/2026 8:00 PM CDT', $printed['segments'][0]['printHeading']);
        $activity = $model['segments'][0]['entity']->gathering_scheduled_activity;
        $activity->end_datetime = new DateTime('2026-10-25 05:00:00', 'UTC');
        $this->assertSame(
            'Evening Court - 10/24/2026 8:00 PM CDT - 10/25/2026 12:00 AM CDT',
            $service->prepare($model)['segments'][0]['printHeading'],
        );
        $activity->has_end_time = false;
        $this->assertSame('Evening Court - 10/24/2026 8:00 PM CDT', $service->prepare($model)['segments'][0]['printHeading']);

        $model['agenda']->gathering->end_date = new DateTime('2026-10-24 04:00:00', 'UTC');
        $this->assertSame('10/23/2026', $service->prepare($model)['eventDates']);
        $model['segments'][0]['entity']->gathering_scheduled_activity = null;
        $this->assertSame('Evening Court', $service->prepare($model)['segments'][0]['printHeading']);
    }

    /** @return void */
    public function testPrintOmitsUnsetFieldsAndRepeatedReasonsAndHeraldSpecialty(): void
    {
        $model = $this->viewModel();
        $item = $model['segments'][0]['items'][0]['entity'];
        $item->bestowal = new Bestowal([
            'member_sca_name' => 'Recipient',
            'call_into_court' => ' Not Set ',
            'court_availability' => " \n ",
            'herald_notes' => "Recipient\nAward — Specialty: Outdated specialty — Call: With Notice\nSpeak clearly.",
            'noble_notes' => "Award (AoA): Dedicated service.\n\nAdditional noble instructions.",
        ]);
        $item->presentation_notes = '   ';
        $item->print_notes = '<Keep this instruction>';
        $model['segments'][0]['items'][0]['reasons'] = [
            'Dedicated service.', 'A separate reason.', 'A separate   reason.', ' ',
        ];
        $model['segments'][0]['items'][0]['specialties'] = ['Current specialty', 'No specialties available'];

        $printed = (new CourtAgendaPrintService())->prepare($model)['segments'][0]['items'][0];

        $this->assertSame(['A separate   reason.'], $printed['reasons']);
        $this->assertSame(['Current specialty'], $printed['specialties']);
        $notes = array_column($printed['printNotes'], 'value', 'label');
        $this->assertSame(['Print:', 'Notes:', 'Noble:'], array_keys($notes));
        $this->assertSame('<Keep this instruction>', $notes['Print:']);
        $this->assertSame('Speak clearly.', $notes['Notes:']);
        $this->assertSame($notes['Notes:'], $printed['heraldNotes']);
        $this->assertStringContainsString('Additional noble instructions.', $notes['Noble:']);
        $this->assertStringContainsString('Outdated specialty', $item->bestowal->herald_notes);
    }

    /** @return void */
    public function testManualOrRedactedItemsHaveNoBestowalNotes(): void
    {
        $printed = (new CourtAgendaPrintService())->prepare($this->viewModel());

        $this->assertSame([], $printed['segments'][0]['items'][0]['printNotes']);
        $this->assertSame('', $printed['segments'][0]['items'][0]['heraldNotes']);
        $this->assertSame('', $printed['segments'][0]['items'][0]['callIntoCourt']);
        $this->assertSame([], $printed['segments'][0]['items'][0]['recommendationNotes']);
        $this->assertSame([], $printed['segments'][0]['items'][0]['reasons']);
        $this->assertSame([], $printed['segments'][0]['items'][0]['specialties']);
    }

    /** @return void */
    public function testGeneratedHeraldSummariesAreRemovedWithoutChangingStoredOrAuthoredNotes(): void
    {
        $model = $this->viewModel();
        $raw = "Áed of Argyll\nCenturion (Grant) — Call: Yes\nAmicitia (Non-Armigerous) — Call: Yes";
        $bestowal = new Bestowal([
            'member_sca_name' => 'Áed of Argyll',
            'call_into_court' => 'With Notice',
            'herald_notes' => $raw . "\nBring the family.\nPronounce <Argyll> carefully.",
        ]);
        $model['segments'][0]['items'][0]['entity']->bestowal = $bestowal;
        $service = new CourtAgendaPrintService();
        $printed = $service->prepare($model)['segments'][0]['items'][0];
        $this->assertSame('With Notice', $printed['callIntoCourt']);
        $this->assertSame("Bring the family.\nPronounce <Argyll> carefully.", $printed['heraldNotes']);
        $this->assertStringContainsString($raw, $bestowal->herald_notes);

        $bestowal->herald_notes = $raw;
        $this->assertSame('', $service->prepare($model)['segments'][0]['items'][0]['heraldNotes']);
        $bestowal->herald_notes = 'Discuss the Centurion (Grant) recommendation with the Crown.';
        $this->assertSame(
            $bestowal->herald_notes,
            $service->prepare($model)['segments'][0]['items'][0]['heraldNotes'],
        );
    }

    /** @return void */
    public function testRecommendationNotesCountTheWholeGroupAndListOriginalAwardsOnce(): void
    {
        $awards = $this->getTableLocator()->get('Awards.Awards')->find()->limit(2)->all()->toList();
        $awards[0]->name = 'Original <Oak> Award';
        $awards[1]->name = 'Second Original Award';
        $recommendations = [];
        foreach ([$awards[0], $awards[0], $awards[1]] as $award) {
            $recommendations[] = new Recommendation(['award_id' => $award->id, 'award' => $award]);
        }
        $model = $this->viewModel();
        $bestowal = new Bestowal([
            'award_id' => $awards[1]->id,
            'recommendations' => $recommendations,
        ]);
        $model['segments'][0]['items'][0]['entity']->bestowal = $bestowal;
        $service = new CourtAgendaPrintService();
        $notes = $service->prepare($model)['segments'][0]['items'][0]['recommendationNotes'];

        $this->assertSame([
            ['label' => 'Recommendation count:', 'value' => '3'],
            [
                'label' => 'Original recommendations were for:',
                'value' => 'Original <Oak> Award, Second Original Award',
            ],
        ], $notes);

        $bestowal->recommendations = [$recommendations[0]];
        $notes = $service->prepare($model)['segments'][0]['items'][0]['recommendationNotes'];
        $this->assertSame('1', $notes[0]['value']);
        $this->assertSame('Original recommendation was for:', $notes[1]['label']);

        $bestowal->award_id = $awards[0]->id;
        $this->assertSame([
            ['label' => 'Recommendation count:', 'value' => '1'],
        ], $service->prepare($model)['segments'][0]['items'][0]['recommendationNotes']);

        unset($bestowal->recommendations);
        $this->assertSame([], $service->prepare($model)['segments'][0]['items'][0]['recommendationNotes']);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewModel(): array
    {
        return [
            'agenda' => new CourtAgenda(['gathering' => new Gathering([
                'timezone' => 'America/Chicago',
                'start_date' => new DateTime('2026-10-24 01:00:00', 'UTC'),
                'end_date' => new DateTime('2026-10-25 17:00:00', 'UTC'),
            ])]),
            'segments' => [[
                'entity' => new CourtAgendaSegment([
                    'name' => 'Evening Court',
                    'gathering_scheduled_activity' => new GatheringScheduledActivity([
                        'start_datetime' => new DateTime('2026-10-25 01:00:00', 'UTC'),
                    ]),
                ]),
                'items' => [[
                    'entity' => new CourtAgendaItem(),
                    'reasons' => [],
                    'specialties' => [],
                ]],
            ]],
        ];
    }
}
