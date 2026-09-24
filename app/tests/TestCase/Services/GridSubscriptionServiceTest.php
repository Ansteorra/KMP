<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\GridSubscriptionReportService;
use App\Services\GridSubscriptionService;
use App\Test\TestCase\BaseTestCase;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;
use RuntimeException;

class GridSubscriptionServiceTest extends BaseTestCase
{
    private function subscription(array $changes = [])
    {
        $table = $this->getTableLocator()->get('GridSubscriptions');
        $entity = $table->newEmptyEntity();
        $entity->patch($changes + [
            'member_id' => self::ADMIN_MEMBER_ID, 'grid_key' => 'Core.actionItems.myTasks',
            'name' => 'Pending tasks', 'query_params' => '{}', 'origin' => 'http://localhost',
            'interval_days' => 3, 'status' => 'active', 'next_run_at' => DateTime::now()->subDays(1),
            'claim_token' => 'test-claim', 'claimed_at' => DateTime::now(),
        ], ['guard' => false]);

        return $table->saveOrFail($entity);
    }

    private function service(GridSubscriptionReportService $reports): GridSubscriptionService
    {
        return new class ($reports) extends GridSubscriptionService {
            public array $sent = [];
            public bool $fail = false;

            protected function send(string $email, string $name, array $report): void
            {
                if ($this->fail) {
                    throw new RuntimeException('Temporary transport failure');
                }
                $this->sent[] = compact('email', 'name', 'report');
            }
        };
    }

    public function testSampleUsesCurrentEmailAndFiltersWithoutChangingSubscriptions(): void
    {
        $subscription = $this->subscription();
        $table = $this->getTableLocator()->get('GridSubscriptions');
        $before = $table->get($subscription->id)->toArray();
        $count = $table->find()->count();
        $reports = $this->createMock(GridSubscriptionReportService::class);
        $reports->expects($this->once())->method('generate')->with(
            self::ADMIN_MEMBER_ID,
            'Core.actionItems.myTasks',
            ['view_id' => 'sys-todos-open', 'search' => 'Unsaved filter'],
            'http://localhost',
        )->willReturn(['headers' => ['Task'], 'rows' => []]);
        $service = $this->service($reports);
        $this->getTableLocator()->get('Members')->updateAll(
            ['email_address' => 'sample-owner@example.test'],
            ['id' => self::ADMIN_MEMBER_ID],
        );
        $service->sendSample(self::ADMIN_MEMBER_ID, [
            'gridKey' => 'Core.actionItems.myTasks', 'name' => 'Current view',
            'query' => 'view_id=sys-todos-open&search=Unsaved%20filter',
            'email' => 'someone-else@example.test', 'member_id' => self::TEST_MEMBER_AGATHA_ID,
        ], 'http://localhost');
        $this->assertCount(1, $service->sent);
        $this->assertSame('sample-owner@example.test', $service->sent[0]['email']);
        $this->assertTrue($service->sent[0]['report']['sample']);
        $this->assertSame([], $service->sent[0]['report']['rows']);
        $this->assertSame($count, $table->find()->count());
        $this->assertEquals($before, $table->get($subscription->id)->toArray());
    }

    public function testSampleDoesNotSendWhenGridAccessIsDenied(): void
    {
        $reports = $this->createMock(GridSubscriptionReportService::class);
        $reports->method('generate')->willThrowException(new ForbiddenException('Access removed'));
        $service = $this->service($reports);
        try {
            $service->sendSample(self::ADMIN_MEMBER_ID, [
                'gridKey' => 'Core.actionItems.myTasks', 'name' => 'Denied view', 'query' => '',
            ], 'http://localhost');
            $this->fail('Expected denied grid access.');
        } catch (ForbiddenException $exception) {
            $this->assertSame('Access removed', $exception->getMessage());
        }
        $this->assertSame([], $service->sent);
    }

    public function testDeliveryUsesFreshReportAndCurrentEmailAndCannotRepeat(): void
    {
        $subscription = $this->subscription();
        $reports = $this->createMock(GridSubscriptionReportService::class);
        $reports->expects($this->once())->method('generate')->with(
            self::ADMIN_MEMBER_ID,
            'Core.actionItems.myTasks',
            [],
            'http://localhost',
        )->willReturn(['headers' => ['Task'], 'rows' => [['Current authorized task']]]);
        $service = $this->service($reports);
        $members = $this->getTableLocator()->get('Members');
        $members->updateAll(['email_address' => 'current-recipient@example.test'], ['id' => self::ADMIN_MEMBER_ID]);
        $service->deliver($subscription->id, 'test-claim');
        $service->deliver($subscription->id, 'test-claim');
        $this->assertCount(1, $service->sent);
        $this->assertSame('current-recipient@example.test', $service->sent[0]['email']);
        $saved = $this->getTableLocator()->get('GridSubscriptions')->get($subscription->id);
        $this->assertNotNull($saved->last_sent_at);
        $this->assertNull($saved->claim_token);
        $this->assertGreaterThan(DateTime::now()->addDays(2), $saved->next_run_at);
    }

    public function testAccessLossStopsWithoutSendingAndEmptyResultSkips(): void
    {
        $subscription = $this->subscription();
        $reports = $this->createMock(GridSubscriptionReportService::class);
        $reports->method('generate')->willThrowException(new ForbiddenException('Role removed'));
        $service = $this->service($reports);
        $service->deliver($subscription->id, 'test-claim');
        $saved = $this->getTableLocator()->get('GridSubscriptions')->get($subscription->id);
        $this->assertSame('stopped', $saved->status);
        $this->assertNull($saved->claim_token);
        $this->assertSame([], $service->sent);

        $empty = $this->subscription();
        $reports = $this->createMock(GridSubscriptionReportService::class);
        $reports->method('generate')->willReturn(['headers' => ['Task'], 'rows' => []]);
        $service = $this->service($reports);
        $service->deliver($empty->id, 'test-claim');
        $saved = $this->getTableLocator()->get('GridSubscriptions')->get($empty->id);
        $this->assertSame('active', $saved->status);
        $this->assertNull($saved->last_sent_at);
        $this->assertSame([], $service->sent);
        $this->assertGreaterThan(DateTime::now(), $saved->next_run_at);
    }

    public function testCancelledAndSupersededJobsNeverReadOrSendData(): void
    {
        $subscription = $this->subscription();
        $reports = $this->createMock(GridSubscriptionReportService::class);
        $reports->expects($this->never())->method('generate');
        $service = $this->service($reports);
        $service->deliver($subscription->id, 'stale-token');
        $this->getTableLocator()->get('GridSubscriptions')->deleteOrFail($subscription);
        $service->deliver($subscription->id, 'test-claim');
        $this->assertSame([], $service->sent);
    }

    public function testTransientFailureRetainsClaimForQueueRetry(): void
    {
        $subscription = $this->subscription();
        $reports = $this->createMock(GridSubscriptionReportService::class);
        $reports->method('generate')->willReturn(['headers' => ['Task'], 'rows' => [['Pending']]]);
        $service = $this->service($reports);
        $service->fail = true;
        try {
            $service->deliver($subscription->id, 'test-claim');
            $this->fail('Expected a transport failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Temporary transport failure', $exception->getMessage());
        }
        $saved = $this->getTableLocator()->get('GridSubscriptions')->get($subscription->id);
        $this->assertSame('test-claim', $saved->claim_token);
        $this->assertNull($saved->last_sent_at);
        $service->fail = false;
        $service->deliver($subscription->id, 'test-claim');
        $this->assertCount(1, $service->sent);
    }

    public function testSchedulerClaimsOnlyDueRowsAndDoesNotQueueTwice(): void
    {
        $due = $this->subscription(['claim_token' => null, 'claimed_at' => null]);
        $this->subscription(['claim_token' => null, 'claimed_at' => null, 'next_run_at' => DateTime::now()->addDays(1)]);
        $this->subscription(['status' => 'stopped', 'claim_token' => null, 'claimed_at' => null]);
        $service = new GridSubscriptionService();
        $this->assertSame(1, $service->enqueueDue());
        $this->assertSame(0, $service->enqueueDue());
        $this->assertNotNull($this->getTableLocator()->get('GridSubscriptions')->get($due->id)->claim_token);
    }

    public function testQueryCannotControlRoutesExportsOrPagination(): void
    {
        $query = (new GridSubscriptionService())->normalizeQuery(
            'controller=Members&action=delete&export=csv&page=999&limit=9999&view_id=sys-todos-open&filter[status][]=open',
        );
        $this->assertSame([
            'view_id' => 'sys-todos-open', 'filter' => ['status' => ['open']],
        ], $query);
    }
}
