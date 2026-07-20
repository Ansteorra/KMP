<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Services\WorkflowEngine\Conditions\CoreConditions;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\AwardsWorkflowActions;
use Awards\Services\BestowalCourtSlotService;
use Awards\Services\BestowalCreationService;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\ORM\TableRegistry;
use Closure;
use Exception;

/**
 * BestowalsController integration tests.
 */
class BestowalsControllerTest extends HttpIntegrationTestCase
{
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
            $container = $this->createMock(CakeContainerInterface::class);
            $actions = new AwardsWorkflowActions();
            $conditions = new CoreConditions();

            $container->method('has')->willReturnCallback(
                static fn($class): bool => in_array($class, [AwardsWorkflowActions::class, CoreConditions::class], true),
            );
            $container->method('get')->willReturnCallback(
                static fn($class): object => match ($class) {
                    AwardsWorkflowActions::class => $actions,
                    CoreConditions::class => $conditions,
                    default => throw new Exception("Unexpected workflow service {$class}"),
                },
            );

            return $container;
        });
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
            ->firstOrFail();
        $award->specialties = ['Scribal Arts', 'Court Heraldry'];
        $this->getTableLocator()->get('Awards.Awards')->saveOrFail($award);

        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'specialty' => 'Scribal Arts',
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
        $this->assertResponseContains('data-awards-bestowal-edit-target="specialtyBlock"');
        $this->assertResponseContains('name="specialty_hidden"');
        $this->assertResponseContains('name="specialty"');
        $this->assertResponseContains('Select a configured specialty or type the specialty to record.');

        $body = (string)$this->_response->getBody();
        $awardPosition = strpos($body, 'Award to Bestow');
        $specialtyPosition = strpos($body, 'Specialty');
        $courtPosition = strpos($body, 'Court Planning');
        $this->assertNotFalse($awardPosition);
        $this->assertNotFalse($specialtyPosition);
        $this->assertNotFalse($courtPosition);
        $this->assertGreaterThan($awardPosition, $specialtyPosition);
        $this->assertLessThan($courtPosition, $specialtyPosition);
    }

    public function testTurboEditFormHandlesEmptySpecialtyCombo(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->firstOrFail();
        $award->specialties = ['Scribal Arts', 'Court Heraldry'];
        $this->getTableLocator()->get('Awards.Awards')->saveOrFail($award);

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
        $this->assertResponseContains('data-awards-bestowal-edit-target="specialtyBlock"');
        $this->assertResponseContains('name="specialty_hidden"');
        $this->assertResponseContains('name="specialty"');
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

    public function testViewLinksGatheringToAwardBestowalsTab(): void
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id', 'public_id'])
            ->firstOrFail();
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 0,
        ]));

        $this->get('/awards/bestowals/view/' . $bestowal->id);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '/gatherings/view/' . $gathering->public_id . '?tab=gathering-bestowals',
        );
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

    public function testEditUsesEphemeralWorkflowWithoutNestedTransactionFailure(): void
    {
        $this->ensureActiveWorkflow('awards-bestowal-update');

        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->firstOrFail();
        $recommendation = $this->createRecommendation((int)$award->id, 'Bestowal update test');
        $createResult = (new BestowalCreationService())->createFromRecommendation(
            (int)$recommendation->id,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->get((int)$createResult['data']['bestowalId']);
        $bestowal->noble_notes = 'Original note';
        $bestowals->saveOrFail($bestowal);

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post('/awards/bestowals/edit/' . $bestowal->id, [
            'id' => $bestowal->id,
            'award_id' => $award->id,
            'page_context_url' => '/awards/bestowals',
            'gathering_scheduled_activity_id' => BestowalCourtSlotService::ROAMING_COURT_VALUE,
            'noble_notes' => 'Updated note',
        ]);

        $this->assertResponseOk();
        $this->assertResponseNotContains('Cannot commit transaction');

        $saved = $bestowals->get($bestowal->id);
        $this->assertSame('Updated note', $saved->noble_notes);
        $this->assertTrue((bool)$saved->roaming_court);
        $this->assertNull($saved->gathering_scheduled_activity_id);
    }

    /**
     * Mock a service with CakePHP's integration-test container.
     *
     * @param string $class Class name
     * @param \Closure $factory Factory
     * @return void
     */
    private function mockServiceClean(string $class, Closure $factory): void
    {
        if ($class !== TriggerDispatcher::class) {
            $this->mockService($class, $factory);

            return;
        }

        $dispatcher = $factory();
        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->method('dispatchTrigger')
            ->willReturnCallback(
                fn(string $eventName, array $eventData = [], ?int $triggeredBy = null): array => $dispatcher->dispatch(
                    $eventName,
                    $eventData,
                    $triggeredBy,
                ),
            );
        $this->mockService(WorkflowEngineInterface::class, static fn() => $engine);
    }

    /**
     * @param string $slug Workflow slug
     * @return void
     */
    private function ensureActiveWorkflow(string $slug): void
    {
        $definitions = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $definition = $definitions->find()->where(['slug' => $slug])->firstOrFail();
        if (!$definition->current_version_id) {
            $versions = TableRegistry::getTableLocator()->get('WorkflowVersions');
            $version = $versions->saveOrFail($versions->newEntity([
                'workflow_definition_id' => $definition->id,
                'version_number' => 1,
                'status' => 'published',
                'definition' => ['nodes' => [], 'edges' => []],
                'created_by' => self::ADMIN_MEMBER_ID,
                'modified_by' => self::ADMIN_MEMBER_ID,
            ]));
            $definition->current_version_id = $version->id;
        }

        $definition->is_active = true;
        $definitions->saveOrFail($definition);
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
