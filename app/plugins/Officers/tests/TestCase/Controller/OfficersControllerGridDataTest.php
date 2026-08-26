<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Model\Entity\Warrant;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;

class OfficersControllerGridDataTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testBranchGridDataSearchUsesValidBranchAssociationAlias(): void
    {
        $this->skipIfPostgres();

        $branches = TableRegistry::getTableLocator()->get('Branches');
        $branch = $branches->find()
            ->select(['id', 'name'])
            ->where(['id' => self::KINGDOM_BRANCH_ID])
            ->first();

        if ($branch === null) {
            $this->markTestSkipped('Expected seeded kingdom branch not found.');
        }

        $this->get('/officers/officers/grid-data?branch_id=' . $branch->id . '&search=' . urlencode($branch->name));

        $this->assertResponseOk();
    }

    public function testApiExportsOfficerWithoutExpirationDate(): void
    {
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer = $officers->find()
            ->contain(['Members'])
            ->firstOrFail();
        $officers->updateAll(['expires_on' => null], ['id' => $officer->id]);

        $this->get('/officers/officers/api');

        $this->assertResponseOk();
        $this->assertHeaderContains('Content-Disposition', 'officers-');
        $this->assertResponseContains('Office,Name,email,Branch,Department,Start,End');
        $this->assertResponseContains($officer->member->sca_name);
    }

    public function testApiStatusFilterIsCaseInsensitiveForPublicRequests(): void
    {
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer = $officers->find()
            ->contain(['Members'])
            ->firstOrFail();
        $officers->updateAll(['status' => Officer::CURRENT_STATUS], ['id' => $officer->id]);
        $this->session([]);

        $this->get('/officers/officers/api?status=current');

        $this->assertResponseOk();
        $this->assertResponseContains($officer->member->sca_name);
    }

    public function testMemberGridShowsCurrentWarrantExpirationAndHistoryDisclosure(): void
    {
        [$officer, $currentExpiration] = $this->prepareOfficerWarrantHistory();

        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'member-officers-grid-table'],
        ]);
        $this->get(sprintf(
            '/officers/officers/grid-data?member_id=%d&view_id=sys-officers-current',
            $officer->member_id,
        ));

        $this->assertResponseOk();
        $this->assertResponseContains('Show warrant history:');
        $this->assertResponseContains('aria-controls="subrow-' . $officer->id . '-warrant-history"');
        $this->assertResponseContains('Expires ' . $currentExpiration->format('F j, Y'));
        $this->assertResponseContains('Exp. ' . $currentExpiration->format('M j, Y'));
    }

    public function testWarrantHistoryReturnsAllWarrantsForOfficerAssignment(): void
    {
        [$officer, $currentExpiration, $expiredExpiration] = $this->prepareOfficerWarrantHistory();

        $this->get('/officers/officers/warrant-history/' . $officer->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Warrant History');
        $this->assertResponseContains('Current');
        $this->assertResponseContains('Expired');
        $this->assertResponseContains($currentExpiration->format('F j, Y'));
        $this->assertResponseContains($expiredExpiration->format('F j, Y'));
        $this->assertResponseContains('<th scope="col" class="pe-4">Status</th>');
        $this->assertResponseContains('class="table-responsive overflow-y-hidden"');
        $this->assertResponseContains('class="table table-sm table-striped align-middle w-auto mb-0"');
    }

    public function testCurrentMemberGridShowsLatestExpiredWarrantByExpirationDate(): void
    {
        [$officer, , , $warrantIds] = $this->prepareOfficerWarrantHistory();
        $now = DateTime::now();
        $newerStartOlderExpiration = $now->subMonths(5)->setTime(12, 0);
        $olderStartLatestExpiration = $now->subDays(7)->setTime(12, 0);
        $warrants = TableRegistry::getTableLocator()->get('Warrants');

        $warrants->updateAll([
            'status' => Warrant::EXPIRED_STATUS,
            'start_on' => $now->subMonths(6),
            'expires_on' => $newerStartOlderExpiration,
        ], ['id' => $warrantIds[0]]);
        $warrants->updateAll([
            'status' => Warrant::EXPIRED_STATUS,
            'start_on' => $now->subYears(2),
            'expires_on' => $olderStartLatestExpiration,
        ], ['id' => $warrantIds[1]]);

        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'member-officers-grid-table'],
        ]);
        $this->get(sprintf(
            '/officers/officers/grid-data?member_id=%d&view_id=sys-officers-current',
            $officer->member_id,
        ));

        $this->assertResponseOk();
        $this->assertResponseContains('Show warrant history:');
        $this->assertResponseContains('Expired ' . $olderStartLatestExpiration->format('F j, Y'));
        $this->assertResponseNotContains('Expired ' . $newerStartOlderExpiration->format('F j, Y'));
    }

    /**
     * @return array{
     *   0: \Officers\Model\Entity\Officer,
     *   1: \Cake\I18n\DateTime,
     *   2: \Cake\I18n\DateTime,
     *   3: list<int>
     * }
     */
    private function prepareOfficerWarrantHistory(): array
    {
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer = $officers->find()
            ->where(['Officers.member_id' => self::ADMIN_MEMBER_ID])
            ->firstOrFail();

        $now = DateTime::now();
        $currentExpiration = $now->addYears(3)->setTime(12, 0);
        $expiredExpiration = $now->subYears(1)->setTime(12, 0);
        $officers->updateAll([
            'status' => Officer::CURRENT_STATUS,
            'start_on' => $now->subMonths(1),
            'expires_on' => $currentExpiration,
        ], ['id' => $officer->id]);

        $warrants = TableRegistry::getTableLocator()->get('Warrants');
        $warrantIds = $warrants->find()
            ->select(['id'])
            ->limit(2)
            ->all()
            ->extract('id')
            ->toList();
        $this->assertCount(2, $warrantIds);

        $warrants->updateAll([
            'member_id' => $officer->member_id,
            'entity_type' => 'Officers.Officers',
            'entity_id' => $officer->id,
            'status' => Warrant::CURRENT_STATUS,
            'start_on' => $now->subMonths(1),
            'expires_on' => $currentExpiration,
        ], ['id' => $warrantIds[0]]);
        $warrants->updateAll([
            'member_id' => $officer->member_id,
            'entity_type' => 'Officers.Officers',
            'entity_id' => $officer->id,
            'status' => Warrant::EXPIRED_STATUS,
            'start_on' => $now->subYears(2),
            'expires_on' => $expiredExpiration,
        ], ['id' => $warrantIds[1]]);

        $officer->member_id = self::ADMIN_MEMBER_ID;

        return [$officer, $currentExpiration, $expiredExpiration, $warrantIds];
    }
}
