<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\KMP\KmpIdentityInterface;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
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
                    BestowalFieldAccessService::ACTION_ACCESS_HERALD_NOTES => $heraldNotes,
                    BestowalFieldAccessService::ACTION_ACCESS_CROWN_FIELDS => $crownFields,
                    default => false,
                };
            },
        );

        return $identity;
    }
}
