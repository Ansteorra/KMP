<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCreationService;
use Awards\Services\CourtAgendaService;

class AwardDisplayConsistencyTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    public function testActualAwardAndSpecialtyAppearInBothGridsExportsAndAgenda(): void
    {
        $recommendation = $this->createRecommendation('Display Award Recipient', null, 'King Approved');
        $recommendation->specialty = 'Originally Recommended Specialty';
        $this->getTableLocator()->get('Awards.Recommendations')->saveOrFail($recommendation);
        $gathering = $this->getTableLocator()->get('Gatherings')->find()->firstOrFail();
        $result = (new BestowalCreationService())->createFromRecommendation(
            (int)$recommendation->id,
            self::ADMIN_MEMBER_ID,
            (int)$gathering->id,
        );
        $this->assertTrue($result['success'], $result['error'] ?? 'Unable to create bestowal.');
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->get($result['data']['bestowalId']);
        $actualAward = $this->getTableLocator()->get('Awards.Awards')->find()
            ->where(['id !=' => $recommendation->award_id])->firstOrFail();
        $bestowal->award_id = $actualAward->id;
        $bestowal->specialty = 'Actual Émbroidery <Oak> & Reed';
        $bestowals->saveOrFail($bestowal);

        foreach (['grid-data', 'gathering-bestowals-grid-data/' . $gathering->id] as $action) {
            $query = ['ignore_default' => 1, 'columns' => 'member_sca_name,awards', 'search' => 'Display Award Recipient'];
            $url = '/awards/bestowals/' . $action . '?';
            $this->get($url . http_build_query($query));
            $this->assertResponseOk();
            $this->assertResponseContains(h($actualAward->abbreviation));
            $this->assertResponseContains(h($bestowal->specialty));
            $this->assertResponseNotContains('Originally Recommended Specialty');
            $this->assertResponseNotContains($bestowal->specialty);

            $this->get($url . http_build_query($query + ['export' => 'csv']));
            $this->assertResponseOk();
            $this->assertHeaderContains('Content-Disposition', '.csv');
            $this->assertResponseContains($actualAward->abbreviation);
            $this->assertResponseContains($bestowal->specialty);
            $this->assertResponseNotContains('Originally Recommended Specialty');
        }

        $service = new CourtAgendaService();
        $agenda = $service->getOrCreateDefaultAgenda((int)$gathering->id, self::ADMIN_MEMBER_ID);
        $service->moveToRoamingCourt(
            (int)$agenda->id,
            bestowalId: (int)$bestowal->id,
            actorId: self::ADMIN_MEMBER_ID,
        );
        foreach (['gathering/' . $gathering->id, 'print-agenda/' . $agenda->id] as $action) {
            $this->get('/awards/court-agendas/' . $action);
            $this->assertResponseOk();
            $this->assertResponseContains(h($actualAward->abbreviation));
            $this->assertResponseContains(h($bestowal->specialty));
        }
    }

    public function testRecommendationNameSortingUsesCurrentAccountsInsteadOfSubmittedSnapshots(): void
    {
        $members = $this->getTableLocator()->get('Members');
        $cases = [
            [self::TEST_MEMBER_AGATHA_ID, 'Z Historical Name', 'Sort Current A'],
            [self::TEST_MEMBER_BRYCE_ID, 'A Historical Name', 'Sort Current B'],
        ];
        $ids = [];
        foreach ($cases as [$memberId, $oldName, $currentName]) {
            $ids[] = (int)$this->createRecommendation($oldName, $memberId)->id;
            $member = $members->get($memberId);
            $member->sca_name = $currentName;
            $members->saveOrFail($member);
        }
        $this->get('/awards/recommendations/grid-data?' . http_build_query([
            'ignore_default' => 1, 'columns' => 'member_sca_name', 'search' => 'Sort Current',
            'sort' => 'member_sca_name', 'direction' => 'asc',
        ]));
        $this->assertResponseOk();
        $names = [];
        foreach ($this->viewVariable('recommendations') as $recommendation) {
            if (in_array((int)$recommendation->id, $ids, true)) {
                $names[] = $recommendation->member_sca_name;
            }
        }
        $this->assertSame(['Sort Current A', 'Sort Current B'], $names);
    }

    public function testRenamedAccountDisplaysCurrentNameWithoutChangingSnapshotsOrIdentityLinks(): void
    {
        $members = $this->getTableLocator()->get('Members');
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $linksBefore = $member->additional_info;
        $recommendation = $this->createRecommendation('Submitted Historical Name', (int)$member->id, 'King Approved');
        $result = (new BestowalCreationService())->createFromRecommendation(
            (int)$recommendation->id,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($result['success'], $result['error'] ?? 'Unable to create bestowal.');
        $member->sca_name = 'Current Élodie <Oak> & Reed';
        $members->saveOrFail($member);

        $this->get('/awards/recommendations/view/' . $recommendation->id);
        $this->assertResponseOk();
        $this->assertResponseContains(h($member->sca_name . ' for '));
        $this->assertResponseNotContains('Submitted Historical Name for ');
        $this->get('/awards/recommendations/turbo-edit-form/' . $recommendation->id);
        $this->assertResponseOk();
        $this->assertResponseContains(h(json_encode([
            'value' => $member->public_id, 'text' => $member->sca_name,
        ])));
        $this->get('/awards/bestowals/view/' . $result['data']['bestowalId']);
        $this->assertResponseOk();
        $this->assertResponseContains(h($member->sca_name));
        $this->assertResponseContains(' — ' . h($member->sca_name));

        foreach (['Current Elodie', 'Current Élodie', 'Submitted Historical Name'] as $search) {
            $query = [
                'ignore_default' => 1, 'columns' => 'member_sca_name', 'search' => $search,
                'sort' => 'member_sca_name', 'direction' => 'asc',
            ];
            $url = '/awards/recommendations/grid-data?';
            $this->get($url . http_build_query($query));
            $this->assertResponseOk();
            $this->assertResponseContains(h($member->sca_name));

            $this->get($url . http_build_query($query + ['export' => 'csv']));
            $this->assertResponseOk();
            $this->assertResponseContains($member->sca_name);
        }

        $saved = $this->getTableLocator()->get('Awards.Recommendations')->get($recommendation->id);
        $this->assertSame('Submitted Historical Name', $saved->member_sca_name);
        $this->assertSame((int)$member->id, (int)$saved->member_id);
        $this->assertSame($linksBefore, $members->get($member->id)->additional_info);
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->get($result['data']['bestowalId']);
        $this->assertSame('Submitted Historical Name', $bestowal->member_sca_name);
        $this->assertSame((int)$member->id, (int)$bestowal->member_id);
    }

    public function testRecipientWithoutAccountKeepsRecordedNameInRecommendationAndExport(): void
    {
        $name = 'Unlinked Élodie <Oak> & Reed';
        $recommendation = $this->createRecommendation($name, null);
        $this->get('/awards/recommendations/view/' . $recommendation->id);
        $this->assertResponseOk();
        $this->assertResponseContains(h($name . ' for '));
        $this->get('/awards/recommendations/grid-data?' . http_build_query([
            'ignore_default' => 1, 'columns' => 'member_sca_name', 'search' => 'Unlinked Elodie', 'export' => 'csv',
        ]));
        $this->assertResponseOk();
        $this->assertResponseContains($name);
    }

    private function createRecommendation(string $name, ?int $memberId, ?string $state = null): Recommendation
    {
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->firstOrFail();
        $table = $this->getTableLocator()->get('Awards.Recommendations');

        return $table->saveOrFail($table->newEntity([
            'member_id' => $memberId, 'member_sca_name' => $name,
            'requester_id' => self::ADMIN_MEMBER_ID, 'requester_sca_name' => 'Display Test Submitter',
            'branch_id' => self::KINGDOM_BRANCH_ID, 'award_id' => $award->id,
            'state' => $state ?? Recommendation::getStates()[0], 'contact_email' => 'display@example.test',
            'reason' => 'Display consistency coverage', 'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set', 'person_to_notify' => '',
        ]));
    }
}
