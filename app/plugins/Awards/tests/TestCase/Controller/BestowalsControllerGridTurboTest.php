<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\Member;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use ReflectionProperty;
use Throwable;

/**
 * Turbo stream grid row sync for BestowalsController.
 */
class BestowalsControllerGridTurboTest extends HttpIntegrationTestCase
{
    /**
     * @var array<int, string>
     */
    private array $mockedServiceKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->enableRetainFlashMessages();
        $this->authenticateAsSuperUser();

        $this->mockServiceClean(CakeContainerInterface::class, function () {
            return $this->createMock(CakeContainerInterface::class);
        });
    }

    protected function tearDown(): void
    {
        $this->mockedServiceKeys = [];
        parent::tearDown();
    }

    public function modifyContainer(
        EventInterface $event,
        PsrContainerInterface $container,
    ): void {
        parent::modifyContainer($event, $container);

        foreach ($this->mockedServiceKeys as $key) {
            if (!$container->has($key)) {
                continue;
            }

            try {
                $definition = $container->extend($key);
                $arguments = new ReflectionProperty($definition, 'arguments');
                $arguments->setAccessible(true);
                $arguments->setValue($definition, []);
            } catch (Throwable) {
                continue;
            }
        }
    }

    public function testEditFromGridReturnsRowReplaceStreamOnMainIndex(): void
    {
        $this->ensureActiveWorkflow('awards-bestowal-update');
        $bestowal = $this->createExistingBestowal();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($bestowal) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->exactly(2))
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use ($bestowal): array {
                    if ($event === 'Awards.BestowalUpdateRequested') {
                        $this->assertSame((int)$bestowal->id, $context['bestowalId']);

                        return [$this->successfulWorkflowDispatchResult([
                            'bestowalId' => (int)$bestowal->id,
                        ])];
                    }

                    return [];
                });

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/awards/bestowals/edit/' . $bestowal->id, [
            'page_context_url' => '/awards/bestowals',
            'note' => 'Grid turbo row sync test',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="replace" target="bestowals-grid-row-' . $bestowal->id . '"',
        );
        $this->assertResponseNotContains('target="bestowals-grid-table"');
    }

    public function testEditFromGridFallsBackToTableRefreshOutsideMainIndex(): void
    {
        $this->ensureActiveWorkflow('awards-bestowal-update');
        $bestowal = $this->createExistingBestowal();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($bestowal) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->exactly(2))
                ->method('dispatch')
                ->willReturnCallback(function (string $event) use ($bestowal): array {
                    if ($event === 'Awards.BestowalUpdateRequested') {
                        return [$this->successfulWorkflowDispatchResult([
                            'bestowalId' => (int)$bestowal->id,
                        ])];
                    }

                    return [];
                });

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/awards/bestowals/edit/' . $bestowal->id, [
            'page_context_url' => '/awards/bestowals/table/draft',
            'note' => 'Outside main index',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-stream action="replace" target="bestowals-grid-table"');
    }

    public function testBulkUpdateTurboStreamRendersSingleSuccessFlash(): void
    {
        $this->ensureActiveWorkflow('awards-bestowal-bulk-transition');
        $bestowal = $this->createExistingBestowal();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($bestowal) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->exactly(2))
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use ($bestowal): array {
                    if ($event === 'Awards.BestowalBulkTransitionRequested') {
                        $this->assertSame([(int)$bestowal->id], $context['bestowalIds']);

                        return [$this->successfulWorkflowDispatchResult([
                            'bestowalIds' => [(int)$bestowal->id],
                        ])];
                    }

                    if ($event === 'Awards.BestowalStateChanged') {
                        $this->assertSame((int)$bestowal->id, $context['bestowalId']);
                    }

                    return [];
                });

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/awards/bestowals/update-states', [
            'page_context_url' => '/awards/bestowals?sort=member_sca_name&direction=desc',
            'ids' => (string)$bestowal->id,
            'newState' => 'Court Scheduled',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-stream action="replace" target="bestowals-grid-table"');
        $this->assertSame(
            1,
            substr_count((string)$this->_response->getBody(), 'The bestowals have been updated.'),
        );
    }

    public function testActiveSystemViewExcludesClosedBestowals(): void
    {
        $activeMemberName = 'bestowal-active-filter-active-' . uniqid();
        $closedMemberName = 'bestowal-active-filter-closed-' . uniqid();
        $activeMember = $this->createGridMember($activeMemberName);
        $closedMember = $this->createGridMember($closedMemberName);

        $this->createBestowalForMember((int)$activeMember->id, 'Created', 'Planning');
        $this->createBestowalForMember((int)$closedMember->id, 'Given', 'Closed');

        $url = '/awards/bestowals/grid-data?' . http_build_query([
            'view_id' => 'sys-bestowals-active',
            'search' => 'bestowal-active-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Active Bestowals');
        $this->assertResponseContains($activeMemberName);
        $this->assertResponseNotContains($closedMemberName);
    }

    public function testCompletedSystemViewShowsGivenBestowalsOnly(): void
    {
        $givenMemberName = 'bestowal-completed-filter-given-' . uniqid();
        $activeMemberName = 'bestowal-completed-filter-active-' . uniqid();
        $givenMember = $this->createGridMember($givenMemberName);
        $activeMember = $this->createGridMember($activeMemberName);

        $this->createBestowalForMember((int)$givenMember->id, 'Given', 'Closed');
        $this->createBestowalForMember((int)$activeMember->id, 'Created', 'Planning');

        $url = '/awards/bestowals/grid-data?' . http_build_query([
            'view_id' => 'sys-bestowals-completed',
            'search' => 'bestowal-completed-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Completed');
        $this->assertResponseContains($givenMemberName);
        $this->assertResponseNotContains($activeMemberName);
    }

    private function createExistingBestowal(): Bestowal
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'state' => 'Created',
            'status' => 'Planning',
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        return $bestowal;
    }

    private function createGridMember(string $scaName): Member
    {
        $members = $this->getTableLocator()->get('Members');
        $member = $members->newEntity([
            'password' => 'VeryStrongPassword123!',
            'sca_name' => $scaName,
            'first_name' => 'Grid',
            'last_name' => 'Tester',
            'street_address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'phone_number' => '',
            'email_address' => 'grid-' . uniqid() . '@example.test',
            'birth_month' => 1,
            'birth_year' => 1990,
        ]);

        $savedMember = $members->saveOrFail($member);
        $this->assertInstanceOf(Member::class, $savedMember);

        return $savedMember;
    }

    private function createBestowalForMember(int $memberId, string $state, string $status): Bestowal
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => $memberId,
            'award_id' => $award->id,
            'state' => $state,
            'status' => $status,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        return $bestowal;
    }

    /**
     * @param string $class Class name
     * @param \Closure $factory Factory
     * @return void
     */
    private function mockServiceClean(string $class, Closure $factory): void
    {
        $this->mockService($class, $factory);
        $this->mockedServiceKeys[] = $class;
    }

    private function ensureActiveWorkflow(string $slug): void
    {
        TableRegistry::getTableLocator()->get('WorkflowDefinitions')
            ->updateAll(['is_active' => true], ['slug' => $slug]);
    }

    /**
     * @param array<string, mixed> $data Workflow result data
     * @return \App\Services\ServiceResult
     */
    private function successfulWorkflowDispatchResult(array $data): ServiceResult
    {
        return new ServiceResult(true, null, [
            'workflowResult' => [
                'success' => true,
                'data' => $data,
            ],
        ]);
    }
}
