<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\AppController;
use App\Model\Entity\ActionItem;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * ActionItemsController integration tests - the "My To-Dos" surface.
 */
class ActionItemsControllerTest extends HttpIntegrationTestCase
{
    /**
     * Persist an open, member-assigned action item.
     *
     * @param int $memberId The eligible member id
     * @param array<string, mixed> $overrides Field overrides
     * @return \App\Model\Entity\ActionItem
     */
    private function makeMemberItem(int $memberId, array $overrides = []): ActionItem
    {
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $data = array_merge([
            'entity_type' => 'Awards.Bestowals',
            'entity_id' => 999999,
            'title' => 'Scroll finished',
            'description' => 'Calligraphy complete',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => $memberId],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_OPEN,
            'is_gating' => true,
            'sort_order' => 1,
        ], $overrides);
        $entity = $table->newEntity($data);

        return $table->saveOrFail($entity);
    }

    /**
     * Unauthenticated requests are redirected to login.
     *
     * @return void
     */
    public function testMyTasksRequiresAuthentication(): void
    {
        $this->get('/action-items/my-tasks');
        $this->assertResponseCode(302);
    }

    /**
     * The My To-Dos page renders the grid shell.
     *
     * @return void
     */
    public function testMyTasksRendersGridShell(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks');

        $this->assertResponseOk();
        $this->assertResponseContains('My To-Dos');
        $this->assertResponseContains('action-items-grid');
        $this->assertResponseContains('todoCompleteModal');
    }

    /**
     * The grid data endpoint lists open items the member may act on.
     *
     * @return void
     */
    public function testMyTasksGridDataListsEligibleOpenItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertResponseContains('Scroll finished');
    }

    /**
     * Mobile queue direct visits redirect to the Auth Card when there is nothing actionable.
     *
     * @return void
     */
    public function testMobileMyTasksRedirectsWhenNoOpenItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/mobile');

        $this->assertRedirectContains('/members/view-mobile-card');
    }

    /**
     * Mobile queue renders when the current member has an open to-do.
     *
     * @return void
     */
    public function testMobileMyTasksReturnsOkWhenOpenItemsExist(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/mobile');

        $this->assertResponseOk();
        $this->assertResponseContains('mobile-action-items');
        $this->assertResponseContains('My To-Dos');
        $this->assertResponseContains(
            'data-mobile-action-items-per-page-value="' . AppController::MOBILE_QUEUE_DEFAULT_PER_PAGE . '"',
        );
    }

    /**
     * Mobile data returns eligible open items as JSON.
     *
     * @return void
     */
    public function testMobileMyTasksDataReturnsEligibleItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $this->get('/action-items/mobile-data');

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(1, $payload['openCount'] ?? null);
        $this->assertSame('Scroll finished', $payload['groups'][0]['items'][0]['title'] ?? null);
    }

    /**
     * Mobile data is paginated so large to-do queues do not render all cards at once.
     *
     * @return void
     */
    public function testMobileMyTasksDataPaginatesEligibleItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'First task',
            'entity_id' => 999991,
            'sort_order' => 1,
        ]);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Second task',
            'entity_id' => 999992,
            'sort_order' => 2,
        ]);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID, [
            'title' => 'Third task',
            'entity_id' => 999993,
            'sort_order' => 3,
        ]);
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);

        $this->get('/action-items/mobile-data?per_page=2&page=1');

        $this->assertResponseOk();
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertSame(3, $payload['openCount'] ?? null);
        $this->assertSame(1, $payload['pagination']['page'] ?? null);
        $this->assertSame(2, $payload['pagination']['perPage'] ?? null);
        $this->assertSame(3, $payload['pagination']['total'] ?? null);
        $this->assertSame(2, $payload['pagination']['pageCount'] ?? null);
        $this->assertTrue($payload['pagination']['hasNextPage'] ?? false);
        $this->assertCount(2, $payload['groups'] ?? []);
    }

    /**
     * Items assigned to someone else are not listed in the grid.
     *
     * @return void
     */
    public function testMyTasksGridDataHidesIneligibleItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get('/action-items/my-tasks-data');

        $this->assertResponseOk();
        $this->assertResponseNotContains('Scroll finished');
    }

    /**
     * The completed view lists to-dos the current member has completed.
     *
     * @return void
     */
    public function testCompletedViewListsMyCompletedItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $completed = $table->saveOrFail($table->newEntity([
            'entity_type' => 'Awards.Bestowals',
            'entity_id' => 999998,
            'title' => 'Regalia allotted',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_COMPLETED,
            'is_gating' => false,
            'sort_order' => 1,
            'completed_by' => self::TEST_MEMBER_AGATHA_ID,
            'completed_at' => DateTime::now(),
        ]));
        $this->assertNotEmpty($completed->id);

        $this->get('/action-items/my-tasks-data?view_id=sys-todos-completed');

        $this->assertResponseOk();
        $this->assertResponseContains('Regalia allotted');
    }

    /**
     * Another member's completed to-do is not listed in my completed view.
     *
     * @return void
     */
    public function testCompletedViewHidesOthersCompletedItems(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $table = TableRegistry::getTableLocator()->get('ActionItems');
        $table->saveOrFail($table->newEntity([
            'entity_type' => 'Awards.Bestowals',
            'entity_id' => 999997,
            'title' => 'Agatha completed scroll',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::TEST_MEMBER_AGATHA_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_COMPLETED,
            'is_gating' => false,
            'sort_order' => 1,
            'completed_by' => self::TEST_MEMBER_AGATHA_ID,
            'completed_at' => DateTime::now(),
        ]));

        $this->get('/action-items/my-tasks-data?view_id=sys-todos-completed');

        $this->assertResponseOk();
        $this->assertResponseNotContains('Agatha completed scroll');
    }

    /**
     * Completion is POST-only.
     *
     * @return void
     */
    public function testCompleteRejectsGet(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->get("/action-items/complete/{$item->id}");

        $this->assertResponseCode(405);
    }

    /**
     * A turbo-stream completion refreshes the grid and flips the item complete.
     *
     * @return void
     */
    public function testCompleteViaTurboStreamRefreshesGrid(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
        $this->post('/action-items/complete', [
            'id' => $item->id,
            'page_context_url' => '/action-items/my-tasks',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('turbo-stream');
        $this->assertResponseContains('action-items-grid-table');
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isCompleted());
    }

    /**
     * An eligible member can complete their gated item.
     *
     * @return void
     */
    public function testEligibleMemberCanComplete(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/action-items/complete/{$item->id}");

        $this->assertRedirect();
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isCompleted());
    }

    /**
     * Mobile completion can return JSON so cards can be removed without a page reload.
     *
     * @return void
     */
    public function testMobileCompleteReturnsJson(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ],
        ]);
        $this->post("/action-items/complete/{$item->id}");

        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $payload = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($payload['success'] ?? false);
        $this->assertSame((int)$item->id, $payload['itemId'] ?? null);
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isCompleted());
    }

    /**
     * An ineligible member is forbidden from completing an item.
     *
     * @return void
     */
    public function testIneligibleMemberCannotComplete(): void
    {
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);
        $item = $this->makeMemberItem(self::TEST_MEMBER_AGATHA_ID);

        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post("/action-items/complete/{$item->id}");

        $this->assertResponseCode(302);
        $this->assertRedirectContains('unauthorized');
        $reloaded = TableRegistry::getTableLocator()->get('ActionItems')->get($item->id);
        $this->assertTrue($reloaded->isOpen());
    }
}
