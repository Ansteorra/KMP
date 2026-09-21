<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature;

use App\Services\GridSubscriptionService;
use App\Services\Security\RateLimitResult;
use App\Services\Security\RequestRateLimiter;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\I18n\DateTime;
use Cake\Routing\Router;

class GridSubscriptionsTest extends HttpIntegrationTestCase
{
    protected function tearDown(): void
    {
        $this->removeMockService(GridSubscriptionService::class);
        $this->removeMockService(RequestRateLimiter::class);
        parent::tearDown();
    }

    public function testSampleSendsForTheSignedInMemberWithoutScheduling(): void
    {
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $limiter = $this->createMock(RequestRateLimiter::class);
        $limiter->expects($this->once())->method('attempt')
            ->with(RequestRateLimiter::BUCKET_GRID_EMAIL_SAMPLE, (string)self::ADMIN_MEMBER_ID)
            ->willReturn(new RateLimitResult(true, 4, 0));
        $this->mockService(RequestRateLimiter::class, fn() => $limiter);
        $service = $this->createMock(GridSubscriptionService::class);
        $service->expects($this->once())->method('sendSample')->with(
            self::ADMIN_MEMBER_ID,
            $this->callback(fn($data) => $data['query'] === 'search=Current+filter'),
            $this->isType('string'),
        );
        $service->expects($this->never())->method('subscribe');
        $this->mockService(GridSubscriptionService::class, fn() => $service);
        $this->post('/grid-subscriptions/sample', [
            'gridKey' => 'Core.actionItems.myTasks', 'name' => 'Sample', 'query' => 'search=Current+filter',
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
        ]);
        $this->assertResponseOk();
        $this->assertResponseContains('"success":true');
    }

    public function testSampleRateLimitStopsDelivery(): void
    {
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $limiter = $this->createMock(RequestRateLimiter::class);
        $limiter->method('attempt')->willReturn(new RateLimitResult(false, 0, 45));
        $this->mockService(RequestRateLimiter::class, fn() => $limiter);
        $service = $this->createMock(GridSubscriptionService::class);
        $service->expects($this->never())->method('sendSample');
        $this->mockService(GridSubscriptionService::class, fn() => $service);
        $this->post('/grid-subscriptions/sample', ['gridKey' => 'Core.actionItems.myTasks', 'name' => 'Sample']);
        $this->assertResponseCode(429);
        $this->assertHeader('Retry-After', '45');
    }

    public function testCreateListAndCancelOwnSubscription(): void
    {
        $this->authenticateAsSuperUser();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/grid-subscriptions/add', [
            'gridKey' => 'Core.actionItems.myTasks', 'name' => 'My pending work',
            'intervalDays' => 3, 'query' => 'view_id=sys-todos-open',
        ]);
        $this->assertResponseOk();
        $data = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($data['success']);
        $id = $data['id'];
        $subscription = $this->getTableLocator()->get('GridSubscriptions')->get($id);
        $this->assertSame(self::ADMIN_MEMBER_ID, $subscription->member_id);
        $this->assertSame(3, $subscription->interval_days);
        // Bootstrap settings audits must not read the previous simulated request's closed CLI session.
        Router::reload();
        $this->get('/grid-subscriptions');
        $this->assertResponseOk();
        $this->assertResponseContains('My pending work');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/grid-subscriptions/delete/' . $id);
        $this->assertRedirectContains('/grid-subscriptions');
        $this->assertFalse($this->getTableLocator()->get('GridSubscriptions')->exists(['id' => $id]));
    }

    public function testApprovalsAndBestowalsUseTheirExistingGridPipelines(): void
    {
        $this->authenticateAsSuperUser();
        foreach (['Workflows.approvals.main', 'WarrantRosters.index.main', 'Awards.Bestowals.index.main'] as $grid) {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->post('/grid-subscriptions/add', [
                'gridKey' => $grid, 'name' => $grid, 'intervalDays' => 7, 'query' => '',
            ]);
            $this->assertResponseOk();
            $this->assertStringContainsString('"success":true', (string)$this->_response->getBody());
        }
    }

    public function testRejectUnsupportedGridAndFrequency(): void
    {
        $this->authenticateAsSuperUser();
        foreach ([['Members.index.main', 1], ['Core.actionItems.myTasks', 2]] as [$grid, $days]) {
            $this->enableCsrfToken();
            $this->enableSecurityToken();
            $this->post('/grid-subscriptions/add', [
                'gridKey' => $grid, 'name' => 'Invalid', 'intervalDays' => $days, 'query' => '',
            ]);
            $this->assertResponseCode(422);
        }
    }

    public function testCannotListOrCancelAnotherMembersSubscription(): void
    {
        $table = $this->getTableLocator()->get('GridSubscriptions');
        $subscription = $table->newEmptyEntity();
        $subscription->patch([
            'member_id' => self::TEST_MEMBER_AGATHA_ID, 'name' => 'Private subscription',
            'grid_key' => 'Core.actionItems.myTasks', 'interval_days' => 1, 'status' => 'active',
            'query_params' => '{}', 'origin' => 'http://localhost', 'next_run_at' => new DateTime(),
        ], ['guard' => false]);
        $table->saveOrFail($subscription);
        $this->authenticateAsSuperUser();
        $this->get('/grid-subscriptions');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Private subscription');
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/grid-subscriptions/delete/' . $subscription->id);
        $this->assertRedirectContains('/pages/unauthorized');
        $this->assertTrue($table->exists(['id' => $subscription->id]));
    }
    public function testDeletedSavedViewCannotFallBackToAllRows(): void
    {
        $this->authenticateAsSuperUser();
        $views = $this->getTableLocator()->get('GridViews');
        $view = $views->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID, 'grid_key' => 'Core.actionItems.myTasks',
            'name' => 'Deleted subscription view', 'config' => '{}',
        ]);
        $views->saveOrFail($view);
        $views->deleteOrFail($view);
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->post('/grid-subscriptions/add', [
            'gridKey' => 'Core.actionItems.myTasks', 'name' => 'Deleted',
            'intervalDays' => 1, 'query' => 'view_id=' . $view->id,
        ]);
        $this->assertResponseCode(403);
        $this->assertFalse($this->getTableLocator()->get('GridSubscriptions')->exists(['name' => 'Deleted']));
    }

}
