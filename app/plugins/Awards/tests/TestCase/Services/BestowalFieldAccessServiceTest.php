<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\KMP\KmpIdentityInterface;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\CourtAgenda;
use Awards\Model\Entity\CourtAgendaItem;
use Awards\Model\Entity\CourtAgendaSegment;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalFieldAccessService;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;

/**
 * Protected bestowal field redaction coverage.
 */
class BestowalFieldAccessServiceTest extends BaseTestCase
{
    public function testGeneralViewerCannotReadOrMutateProtectedFields(): void
    {
        $bestowal = $this->protectedBestowal();
        $identity = $this->identityWithAccess(false, false);
        $service = new BestowalFieldAccessService();

        $access = $service->redact($bestowal, $identity);

        $this->assertSame(['heraldNotes' => false, 'crownFields' => false], $access);
        $this->assertFalse($bestowal->has('herald_notes'));
        $this->assertFalse($bestowal->has('noble_notes'));
        $this->assertFalse($bestowal->has('reason_summary'));
        $this->assertFalse($bestowal->has('recommendation_reasons'));
        $this->assertFalse($bestowal->has('recommendations'));
        $this->assertSame(
            ['herald_notes', 'noble_notes', 'reason_summary', 'link_recommendation_ids'],
            $service->deniedMutationFields([
                'herald_notes' => 'Denied',
                'noble_notes' => 'Denied',
                'reason_summary' => 'Denied',
                'link_recommendation_ids' => [1],
            ], $identity, $this->protectedBestowal()),
        );
    }

    public function testCourtManagerCanAccessOnlyHeraldNotes(): void
    {
        $bestowal = $this->protectedBestowal();
        $identity = $this->identityWithAccess(true, false);
        $service = new BestowalFieldAccessService();

        $access = $service->redact($bestowal, $identity);

        $this->assertSame(['heraldNotes' => true, 'crownFields' => false], $access);
        $this->assertSame('Herald secret', $bestowal->herald_notes);
        $this->assertFalse($bestowal->has('noble_notes'));
        $this->assertSame(
            ['noble_notes', 'unlink_recommendation_ids'],
            $service->deniedMutationFields([
                'herald_notes' => 'Allowed',
                'noble_notes' => 'Denied',
                'unlink_recommendation_ids' => [1],
            ], $identity, $this->protectedBestowal()),
        );
    }

    public function testCrownCanAccessAllProtectedFields(): void
    {
        $bestowal = $this->protectedBestowal();
        $identity = $this->identityWithAccess(false, true);
        $service = new BestowalFieldAccessService();

        $access = $service->redact($bestowal, $identity);

        $this->assertSame(['heraldNotes' => true, 'crownFields' => true], $access);
        $this->assertSame('Herald secret', $bestowal->herald_notes);
        $this->assertSame('Noble secret', $bestowal->noble_notes);
        $this->assertSame('Summary secret', $bestowal->reason_summary);
        $this->assertSame('Recommendation secret', $bestowal->recommendations[0]->reason);
        $this->assertSame([], $service->deniedMutationFields([
            'herald_notes' => 'Allowed',
            'noble_notes' => 'Allowed',
            'reason_summary' => 'Allowed',
            'link_recommendation_ids' => [1],
        ], $identity, $bestowal));
    }

    public function testAgendaRedactionRemovesDerivedRecommendationReasons(): void
    {
        $bestowal = $this->protectedBestowal();
        $item = new Entity(['bestowal' => $bestowal]);
        $service = new BestowalFieldAccessService();

        $viewModel = $service->redactAgendaViewModel([
            'segments' => [[
                'items' => [[
                    'entity' => $item,
                    'reasons' => ['Recommendation secret'],
                ]],
                'eligibleBestowals' => [$bestowal],
            ]],
            'unscheduledBestowals' => [['entity' => $bestowal]],
        ], $this->identityWithAccess(false, false));

        $this->assertSame([], $viewModel['segments'][0]['items'][0]['reasons']);
        $this->assertFalse($bestowal->has('herald_notes'));
        $this->assertFalse($bestowal->has('noble_notes'));
    }

    public function testAgendaBlocksUnreadableItemsAndRemovesEveryUnreadableCandidate(): void
    {
        $blocked = $this->protectedBestowal();
        $visible = $this->protectedBestowal();
        $identity = $this->createMock(KmpIdentityInterface::class);
        $identity->method('checkCan')->willReturnCallback(
            static fn(string $action, mixed $resource): bool => $resource === $visible,
        );
        $item = new CourtAgendaItem([
            'id' => 100,
            'bestowal_id' => 200,
            'bestowal' => $blocked,
            'estimated_minutes' => 8,
            'sort_order' => 20,
            'presentation_notes' => 'Secret agenda notes',
            'print_notes' => 'Secret print notes',
            'planned_action' => 'Secret planned action',
            'title' => 'Secret title',
        ], ['guard' => false]);
        $segment = new CourtAgendaSegment(['court_agenda_items' => [$item]]);
        $agenda = new CourtAgenda(['court_agenda_segments' => [$segment]]);

        $result = (new BestowalFieldAccessService())->redactAgendaViewModel([
            'agenda' => $agenda,
            'segments' => [[
                'entity' => $segment,
                'items' => [[
                    'entity' => $item,
                    'label' => 'Secret recipient',
                    'awardLabel' => 'Secret award',
                    'durationHint' => 'Secret award level',
                    'reasons' => ['Secret reason'],
                    'specialties' => ['Secret specialty'],
                    'moveSegmentOptions' => [300 => 'Secret eligible court'],
                    'minutes' => 8,
                ]],
                'eligibleBestowals' => [$blocked, $visible],
            ]],
            'unscheduledBestowals' => [
                ['entity' => $blocked, 'label' => 'Secret backlog recipient'],
                ['entity' => $visible, 'label' => 'Visible recipient'],
            ],
            'totalMinutes' => 8,
        ], $identity);

        $redacted = $result['segments'][0]['items'][0];
        $this->assertSame('Court Item  (details blocked by security)', $redacted['label']);
        $this->assertTrue($redacted['detailsBlocked']);
        $this->assertSame(8, $redacted['minutes']);
        $this->assertSame(100, $redacted['entity']->id);
        $this->assertSame(20, $redacted['entity']->sort_order);
        $this->assertNull($redacted['entity']->bestowal);
        $this->assertNull($redacted['entity']->bestowal_id);
        $this->assertSame('', $redacted['awardLabel']);
        $this->assertSame('', $redacted['durationHint']);
        $this->assertSame([], $redacted['reasons']);
        $this->assertSame([], $redacted['specialties']);
        $this->assertSame([], $redacted['moveSegmentOptions']);
        $this->assertSame([$visible], $result['segments'][0]['eligibleBestowals']);
        $this->assertSame([['entity' => $visible, 'label' => 'Visible recipient']], $result['unscheduledBestowals']);
        $this->assertSame($redacted['entity'], $segment->court_agenda_items[0]);
        $this->assertSame($redacted['entity'], $agenda->court_agenda_segments[0]->court_agenda_items[0]);
        $this->assertStringNotContainsString('Secret', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testUnresolvedBestowalIsBlockedAndManualBlocksKeepTheirDetails(): void
    {
        $unresolved = [
            'entity' => new CourtAgendaItem([
                'item_type' => CourtAgendaItem::TYPE_BESTOWAL,
                'presentation_notes' => 'Secret notes for missing bestowal',
            ]),
            'label' => 'Secret recipient',
            'minutes' => 5,
        ];
        $manual = [
            'entity' => new CourtAgendaItem([
                'item_type' => CourtAgendaItem::TYPE_BLOCK,
                'title' => 'Court business',
                'presentation_notes' => 'Public court business notes',
            ]),
            'label' => 'Court business',
            'minutes' => 10,
        ];

        $result = (new BestowalFieldAccessService())->redactAgendaViewModel([
            'segments' => [['items' => [$unresolved, $manual]]],
        ], $this->identityWithAccess(false, false));

        $this->assertTrue($result['segments'][0]['items'][0]['detailsBlocked']);
        $this->assertSame($manual, $result['segments'][0]['items'][1]);
        $this->assertStringNotContainsString('Secret', json_encode($result, JSON_THROW_ON_ERROR));
    }

    private function protectedBestowal(): Bestowal
    {
        return new Bestowal([
            'herald_notes' => 'Herald secret',
            'herald_notes_preview' => 'Herald preview',
            'noble_notes' => 'Noble secret',
            'reason_summary' => 'Summary secret',
            'recommendation_reasons' => 'Recommendation secret',
            'recommendations' => [new Recommendation(['reason' => 'Recommendation secret'])],
        ]);
    }

    private function identityWithAccess(bool $heraldNotes, bool $crownFields): KmpIdentityInterface
    {
        $identity = $this->createMock(KmpIdentityInterface::class);
        $identity->method('checkCan')->willReturnCallback(
            static function (string $action, mixed $resource) use ($heraldNotes, $crownFields): bool {
                if (!$resource instanceof EntityInterface) {
                    return false;
                }

                return match ($action) {
                    'view' => true,
                    BestowalFieldAccessService::ACTION_ACCESS_HERALD_NOTES => $heraldNotes,
                    BestowalFieldAccessService::ACTION_ACCESS_CROWN_FIELDS => $crownFields,
                    default => false,
                };
            },
        );

        return $identity;
    }
}
