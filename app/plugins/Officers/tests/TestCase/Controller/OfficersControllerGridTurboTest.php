<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;

/**
 * Turbo stream grid row sync for OfficersController::edit.
 */
class OfficersControllerGridTurboTest extends HttpIntegrationTestCase
{
    private $workflowDefinitions;

    private $workflowVersions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();

        $locator = TableRegistry::getTableLocator();
        $this->workflowDefinitions = $locator->get('WorkflowDefinitions');
        $this->workflowVersions = $locator->get('WorkflowVersions');
    }

    public function testEditFromBranchGridReturnsRowReplaceStream(): void
    {
        $this->ensureActiveWorkflow('officer-assignment-update');
        $branch = TableRegistry::getTableLocator()->get('Branches')->get(self::KINGDOM_BRANCH_ID);
        $officer = $this->createTestOfficerOnBranch((int)$branch->id);
        $this->mockSuccessfulEditWorkflow();

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/officers/officers/edit', [
            'id' => $officer->id,
            'start_on' => $officer->start_on->format('Y-m-d'),
            'expires_on' => $officer->expires_on->format('Y-m-d'),
            'deputy_description' => 'Updated deputy',
            'email_address' => 'grid-turbo@example.com',
            'term_note' => '',
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
        $this->ensureActiveWorkflow('officer-assignment-update');
        $officer = $this->createTestOfficerOnBranch(self::KINGDOM_BRANCH_ID);
        $memberId = (int)$officer->member_id;
        $this->mockSuccessfulEditWorkflow();

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/officers/officers/edit', [
            'id' => $officer->id,
            'start_on' => $officer->start_on->format('Y-m-d'),
            'expires_on' => $officer->expires_on->format('Y-m-d'),
            'deputy_description' => '',
            'email_address' => 'member-grid@example.com',
            'term_note' => '',
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

    private function mockSuccessfulEditWorkflow(): void
    {
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('dispatchTrigger')
            ->willReturnCallback(function (
                string $eventName,
                array $eventData,
                ?int $triggeredBy = null,
            ): array {
                $this->assertSame('Officers.AssignmentUpdateRequested', $eventName);
                $this->assertSame(self::ADMIN_MEMBER_ID, $triggeredBy);

                $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
                $officer = $officers->get($eventData['officerId']);
                $officer->email_address = $eventData['emailAddress'];
                $officer->deputy_description = $eventData['deputyDescription'];
                $officers->saveOrFail($officer);

                return [new ServiceResult(true, null, [
                    'workflowResult' => [
                        'success' => true,
                        'data' => ['officerId' => (int)$officer->id],
                    ],
                ])];
            });
        $this->mockService(WorkflowEngineInterface::class, static fn() => $engine);
    }

    private function ensureActiveWorkflow(string $slug): void
    {
        $definition = $this->workflowDefinitions->find()->where(['slug' => $slug])->first();

        if (!$definition) {
            $definition = $this->workflowDefinitions->newEntity([
                'name' => "Test {$slug}",
                'slug' => $slug,
                'description' => "Test workflow for {$slug}",
                'trigger_type' => 'event',
                'is_active' => true,
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $this->workflowDefinitions->saveOrFail($definition);
        }

        if (!$definition->current_version_id) {
            $version = $this->workflowVersions->newEntity([
                'workflow_definition_id' => $definition->id,
                'version_number' => 1,
                'status' => 'published',
                'definition' => ['nodes' => [], 'edges' => []],
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]);
            $this->workflowVersions->saveOrFail($version);
            $definition->current_version_id = $version->id;
        }

        $definition->is_active = true;
        $this->workflowDefinitions->saveOrFail($definition);
    }
}
