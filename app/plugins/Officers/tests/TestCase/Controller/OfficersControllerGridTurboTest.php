<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;

/**
 * Turbo stream grid row sync for OfficersController::edit.
 */
class OfficersControllerGridTurboTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();
    }

    public function testEditFromBranchGridReturnsRowReplaceStream(): void
    {
        $branch = TableRegistry::getTableLocator()->get('Branches')->get(self::KINGDOM_BRANCH_ID);
        $officer = $this->createTestOfficerOnBranch((int)$branch->id);

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/officers/officers/edit', [
            'id' => $officer->id,
            'deputy_description' => 'Updated deputy',
            'email_address' => 'grid-turbo@example.com',
            'page_context_url' => '/branches/view/' . $branch->public_id . '?tab=branch-officers',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="replace" target="branch-officers-grid-row-' . $officer->id . '"',
        );
        $this->assertResponseNotContains('target="branch-officers-grid-table"');
    }

    public function testEditFromMemberGridReturnsRowReplaceStream(): void
    {
        $officer = $this->createTestOfficerOnBranch(self::KINGDOM_BRANCH_ID);
        $memberId = (int)$officer->member_id;

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/officers/officers/edit', [
            'id' => $officer->id,
            'deputy_description' => '',
            'email_address' => 'member-grid@example.com',
            'page_context_url' => '/members/view/' . $memberId . '?tab=member-officers',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="replace" target="member-officers-grid-row-' . $officer->id . '"',
        );
    }

    private function createTestOfficerOnBranch(int $branchId): object
    {
        $offices = TableRegistry::getTableLocator()->get('Officers.Offices');
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $office = $offices->find()->firstOrFail();

        $officer = $officers->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'office_id' => $office->id,
            'branch_id' => $branchId,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => DateTime::now(),
            'start_on' => DateTime::now()->subDays(30),
            'expires_on' => DateTime::now()->addMonths(6),
            'status' => Officer::CURRENT_STATUS,
            'reports_to_office_id' => $office->reports_to_id ?? $office->id,
            'reports_to_branch_id' => $branchId,
        ]);
        $officers->saveOrFail($officer);

        return $officer;
    }
}
