<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCourtSlotService;
use Awards\Services\BestowalCreationService;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\Event\EventInterface;
use Cake\ORM\TableRegistry;
use Closure;
use Exception;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use ReflectionProperty;

/**
 * BestowalsController integration tests.
 */
class BestowalsControllerTest extends HttpIntegrationTestCase
{
    /**
     * @var array<int, string>
     */
    private array $mockedServiceKeys = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();

        $this->mockServiceClean(CakeContainerInterface::class, function () {
            return $this->createMock(CakeContainerInterface::class);
        });
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->mockedServiceKeys = [];
        parent::tearDown();
    }

    /**
     * @param \Cake\Event\EventInterface $event Event
     * @param \Psr\Container\ContainerInterface $container Container
     * @return void
     */
    public function modifyContainer(EventInterface $event, PsrContainerInterface $container): void
    {
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
            } catch (Exception) {
                continue;
            }
        }
    }

    /**
     * @return void
     */
    public function testGridDataSortByGatheringNameAsc(): void
    {
        $this->get(
            '/awards/bestowals/grid-data?ignore_default=1&sort=gathering_name&direction=asc',
        );
        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testGridDataSortByMemberScaNameAsc(): void
    {
        $this->get(
            '/awards/bestowals/grid-data?ignore_default=1&sort=member_sca_name&direction=asc',
        );
        $this->assertResponseOk();
    }

    /**
     * @return void
     */
    public function testTurboEditFormReturnsFormForTurboFrame(): void
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
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        $this->configRequest([
            'headers' => [
                'Turbo-Frame' => 'editBestowalQuick',
            ],
        ]);
        $this->get('/awards/bestowals/turbo-edit-form/' . $bestowal->id);
        $this->assertResponseOk();
        $this->assertResponseContains('id="bestowal_form"');
        $this->assertResponseContains('turbo-frame id="editBestowalQuick"');
    }

    /**
     * @return void
     */
    public function testViewShowsLinkedRecommendationReasons(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        $firstReason = 'First linked bestowal court-note reason ' . uniqid('', true);
        $secondReason = 'Second linked bestowal herald reason ' . uniqid('', true);
        $firstRecommendation = $this->createRecommendation((int)$award->id, $firstReason);
        $secondRecommendation = $this->createRecommendation((int)$award->id, $secondReason);

        $bestowalRecommendations = $this->getTableLocator()->get('Awards.BestowalRecommendations');
        foreach ([$firstRecommendation, $secondRecommendation] as $recommendation) {
            $bestowalRecommendations->saveOrFail($bestowalRecommendations->newEntity([
                'bestowal_id' => $bestowal->id,
                'recommendation_id' => $recommendation->id,
            ]));
        }

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains('Linked Recommendations');
        $this->assertResponseContains('Reason:');
        $this->assertResponseContains(h($firstReason));
        $this->assertResponseContains(h($secondReason));
    }

    /**
     * @return void
     */
    public function testGridDataShowsLinkedRecommendationReasons(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        $firstReason = 'First grid linked recommendation reason ' . uniqid('', true);
        $secondReason = 'Second grid linked recommendation reason ' . uniqid('', true);
        $firstRecommendation = $this->createRecommendation((int)$award->id, $firstReason);
        $secondRecommendation = $this->createRecommendation((int)$award->id, $secondReason);

        $bestowalRecommendations = $this->getTableLocator()->get('Awards.BestowalRecommendations');
        foreach ([$firstRecommendation, $secondRecommendation] as $recommendation) {
            $bestowalRecommendations->saveOrFail($bestowalRecommendations->newEntity([
                'bestowal_id' => $bestowal->id,
                'recommendation_id' => $recommendation->id,
            ]));
        }

        $this->get('/awards/bestowals/grid-data?ignore_default=1');

        $this->assertResponseOk();
        $this->assertResponseContains('Recommendation Reasons');
        $this->assertResponseContains('Show 2 linked recommendation reasons');
        $this->assertResponseContains(h($firstReason));
        $this->assertResponseContains(h($secondReason));
    }

    /**
     * @return void
     */
    public function testGridDataShowsRoamingCourtInCourtSlotColumn(): void
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
            'roaming_court' => true,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]);
        $bestowals->saveOrFail($bestowal);

        $this->get('/awards/bestowals/grid-data?ignore_default=1');
        $this->assertResponseOk();
        $this->assertResponseContains(
            h((new BestowalCourtSlotService())->formatCourtSlotDisplay($bestowal)),
        );
    }

    /**
     * @return void
     */
    public function testAdHocDispatchesRegisteredWorkflowTrigger(): void
    {
        $this->ensureActiveWorkflow('awards-bestowal-ad-hoc');

        $events = [];
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$events) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->exactly(2))
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$events): array {
                    $events[] = $event;

                    if ($event === 'Awards.AdHocBestowalRequested') {
                        $this->assertArrayHasKey('data', $context);
                        $this->assertSame(self::ADMIN_MEMBER_ID, $context['actorId']);

                        return [$this->successfulWorkflowDispatchResult([
                            'bestowalId' => 123,
                            'eventPayload' => [
                                'bestowalId' => 123,
                                'actorId' => self::ADMIN_MEMBER_ID,
                            ],
                        ])];
                    }

                    $this->assertSame(BestowalCreationService::EVENT_NAME, $event);

                    return [];
                });

            return $mock;
        });

        $this->post('/awards/bestowals/ad-hoc', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_ids' => [1],
            'bestowed_at' => '2026-04-01 12:00:00',
            'gathering_id' => 1,
        ]);

        $this->assertSame(['Awards.AdHocBestowalRequested', BestowalCreationService::EVENT_NAME], $events);
        $this->assertRedirectContains('/awards/bestowals/view/123');
    }

    /**
     * Mock a service and clear its stale DI constructor arguments.
     *
     * @param string $class Class name
     * @param \Closure $factory Factory
     * @return void
     */
    private function mockServiceClean(string $class, Closure $factory): void
    {
        $this->mockService($class, $factory);
        $this->mockedServiceKeys[] = $class;
    }

    /**
     * @param string $slug Workflow slug
     * @return void
     */
    private function ensureActiveWorkflow(string $slug): void
    {
        TableRegistry::getTableLocator()->get('WorkflowDefinitions')
            ->updateAll(['is_active' => true], ['slug' => $slug]);
    }

    /**
     * @param int $awardId Award ID
     * @param string $reason Recommendation reason
     * @return \Awards\Model\Entity\Recommendation
     */
    private function createRecommendation(int $awardId, string $reason): Recommendation
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $states = Recommendation::getStates();
        $this->assertNotEmpty($states, 'Expected configured recommendation states');

        $recommendation = $recommendations->newEntity([
            'stack_rank' => 0,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'state' => $states[0],
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => $reason,
            'call_into_court' => 'Never',
            'court_availability' => 'Any',
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]);

        return $recommendations->saveOrFail($recommendation);
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
