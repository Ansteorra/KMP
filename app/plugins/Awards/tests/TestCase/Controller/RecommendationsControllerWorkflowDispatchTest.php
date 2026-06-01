<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationSubmissionService;
use Awards\Services\RecommendationTransitionService;
use Awards\Services\RecommendationUpdateService;
use Cake\Core\ContainerInterface as CakeContainerInterface;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use ReflectionProperty;
use Throwable;

class RecommendationsControllerWorkflowDispatchTest extends HttpIntegrationTestCase
{
    private $workflowDefinitions;
    private $workflowVersions;
    private $recommendations;
    private $awards;
    private $members;

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

        $locator = TableRegistry::getTableLocator();
        $this->workflowDefinitions = $locator->get('WorkflowDefinitions');
        $this->workflowVersions = $locator->get('WorkflowVersions');
        $this->recommendations = $locator->get('Awards.Recommendations');
        $this->awards = $locator->get('Awards.Awards');
        $this->members = $locator->get('Members');

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

    public function testAddFailsWhenWorkflowInactive(): void
    {
        $this->deactivateWorkflows(['awards-recommendation-submitted']);
        $reason = 'Workflow-only add should not fall back';

        $this->mockServiceClean(RecommendationSubmissionService::class, function () {
            $mock = $this->createMock(RecommendationSubmissionService::class);
            $mock->expects($this->never())
                ->method('submitAuthenticated');

            return $mock;
        });
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $data = $this->buildAuthenticatedSubmissionData();
        $data['reason'] = $reason;
        $this->post($this->recommendationsUrl('add'), $data);

        $this->assertResponseOk();
        $this->assertFlashMessage('The recommendation workflow is not currently available.', 'flash');
        $this->assertFalse($this->recommendations->exists(['reason' => $reason]));
    }

    public function testAddDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-submitted');
        $savedRecommendation = $this->createExistingRecommendation();

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched, $savedRecommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched, $savedRecommendation): array {
                    $dispatched = true;
                    $this->assertSame('Awards.RecommendationCreateRequested', $event);
                    $this->assertSame('authenticated', $context['submissionMode']);
                    $this->assertArrayHasKey('data', $context);
                    $this->assertArrayHasKey('requesterContext', $context);

                    return [$this->successfulWorkflowDispatchResult([
                        'recommendationId' => (int)$savedRecommendation->id,
                    ])];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationSubmissionService::class, function () {
            $mock = $this->createMock(RecommendationSubmissionService::class);
            $mock->expects($this->never())->method('submitAuthenticated');

            return $mock;
        });

        $this->post($this->recommendationsUrl('add'), $this->buildAuthenticatedSubmissionData());

        $this->assertTrue($dispatched);
        $this->assertRedirectContains('/awards/recommendations/view/' . $savedRecommendation->id);
    }

    public function testSubmitRecommendationDispatchesWorkflowWhenActiveForGuestSubmission(): void
    {
        $this->logout();
        $this->ensureActiveWorkflow('awards-recommendation-submitted');

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context, ?int $triggeredBy) use (&$dispatched): array {
                    $dispatched = true;
                    $this->assertSame('Awards.RecommendationCreateRequested', $event);
                    $this->assertNull($triggeredBy);
                    $this->assertSame('public', $context['submissionMode']);
                    $this->assertSame(self::KINGDOM_BRANCH_ID, $context['kingdom_id']);
                    $this->assertSame(self::TEST_BRANCH_STARGATE_ID, (int)$context['data']['branch_id']);

                    return [$this->successfulWorkflowDispatchResult([
                        'recommendationId' => 999,
                    ])];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationSubmissionService::class, function () {
            $mock = $this->createMock(RecommendationSubmissionService::class);
            $mock->expects($this->never())->method('submitPublic');

            return $mock;
        });

        $this->post($this->recommendationsUrl('submitRecommendation'), $this->buildPublicSubmissionData());

        $this->assertTrue($dispatched);
        $this->assertResponseOk();
        $this->assertResponseContains('The recommendation has been submitted.');
    }

    public function testEditFailsWhenWorkflowInactive(): void
    {
        $this->deactivateWorkflows(['awards-recommendation-updated']);
        $savedRecommendation = $this->createExistingRecommendation();
        $originalReason = $savedRecommendation->reason;

        $this->mockServiceClean(RecommendationUpdateService::class, function () {
            $mock = $this->createMock(RecommendationUpdateService::class);
            $mock->expects($this->never())
                ->method('update');

            return $mock;
        });
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post($this->recommendationsUrl('edit', [(string)$savedRecommendation->id]), [
            'reason' => 'Updated through legacy service',
        ]);

        $this->assertFlashMessage('The recommendation workflow is not currently available.', 'flash');
        $this->assertRedirectContains('/awards/recommendations/view/' . $savedRecommendation->id);
        $freshRecommendation = $this->recommendations->get((int)$savedRecommendation->id);
        $this->assertSame($originalReason, $freshRecommendation->reason);
    }

    public function testEditDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-updated');
        $savedRecommendation = $this->createExistingRecommendation();

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched, $savedRecommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched, $savedRecommendation): array {
                    $dispatched = true;
                    $this->assertSame('Awards.RecommendationUpdateRequested', $event);
                    $this->assertSame((int)$savedRecommendation->id, $context['recommendationId']);
                    $this->assertArrayHasKey('data', $context);

                    return [$this->successfulWorkflowDispatchResult([
                        'recommendationId' => (int)$savedRecommendation->id,
                    ])];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationUpdateService::class, function () {
            $mock = $this->createMock(RecommendationUpdateService::class);
            $mock->expects($this->never())->method('update');

            return $mock;
        });

        $this->post($this->recommendationsUrl('edit', [(string)$savedRecommendation->id]), [
            'reason' => 'Updated through workflow',
        ]);

        $this->assertTrue($dispatched);
        $this->assertRedirectContains('/awards/recommendations/view/' . $savedRecommendation->id);
    }

    public function testEditFromGridReturnsRenderedTurboStreamWhenActive(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-updated');
        $savedRecommendation = $this->createExistingRecommendation();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($savedRecommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use ($savedRecommendation): array {
                    $this->assertSame('Awards.RecommendationUpdateRequested', $event);
                    $this->assertSame((int)$savedRecommendation->id, $context['recommendationId']);

                    return [$this->successfulWorkflowDispatchResult([
                        'recommendationId' => (int)$savedRecommendation->id,
                    ])];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationUpdateService::class, function () {
            $mock = $this->createMock(RecommendationUpdateService::class);
            $mock->expects($this->never())->method('update');

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post($this->recommendationsUrl('edit', [(string)$savedRecommendation->id]), [
            'reason' => 'Updated through workflow',
            'page_context_url' => '/awards/recommendations',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="replace" target="recommendations-grid-row-' . $savedRecommendation->id . '"',
        );
        $this->assertResponseNotContains('target="recommendations-grid-table"');
    }

    public function testEditFromMemberSubmittedGridReturnsRowReplaceStream(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-updated');
        $savedRecommendation = $this->createExistingRecommendation();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($savedRecommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturn([$this->successfulWorkflowDispatchResult([
                    'recommendationId' => (int)$savedRecommendation->id,
                ])]);

            return $mock;
        });
        $this->mockServiceClean(RecommendationUpdateService::class, function () {
            $mock = $this->createMock(RecommendationUpdateService::class);
            $mock->expects($this->never())->method('update');

            return $mock;
        });

        $memberId = (int)$savedRecommendation->requester_id;
        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post($this->recommendationsUrl('edit', [(string)$savedRecommendation->id]), [
            'reason' => 'Updated on member submitted grid',
            'page_context_url' => '/members/view/' . $memberId . '?tab=member-submitted-recs',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            '<turbo-stream action="replace" target="member-submitted-recs-grid-' . $memberId . '-row-' . $savedRecommendation->id . '"',
        );
    }

    public function testEditFromGridFallsBackToTableRefreshOutsideMainIndex(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-updated');
        $savedRecommendation = $this->createExistingRecommendation();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($savedRecommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturn([$this->successfulWorkflowDispatchResult([
                    'recommendationId' => (int)$savedRecommendation->id,
                ])]);

            return $mock;
        });
        $this->mockServiceClean(RecommendationUpdateService::class, function () {
            $mock = $this->createMock(RecommendationUpdateService::class);
            $mock->expects($this->never())->method('update');

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post($this->recommendationsUrl('edit', [(string)$savedRecommendation->id]), [
            'reason' => 'Updated through workflow',
            'page_context_url' => '/awards/recommendations/table/draft',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-stream action="replace" target="recommendations-grid-table"');
    }

    public function testEditFromGridWithoutTurboAcceptRedirectsToPageContext(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-updated');
        $savedRecommendation = $this->createExistingRecommendation();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($savedRecommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use ($savedRecommendation): array {
                    $this->assertSame('Awards.RecommendationUpdateRequested', $event);
                    $this->assertSame((int)$savedRecommendation->id, $context['recommendationId']);

                    return [$this->successfulWorkflowDispatchResult([
                        'recommendationId' => (int)$savedRecommendation->id,
                    ])];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationUpdateService::class, function () {
            $mock = $this->createMock(RecommendationUpdateService::class);
            $mock->expects($this->never())->method('update');

            return $mock;
        });

        $this->post($this->recommendationsUrl('edit', [(string)$savedRecommendation->id]), [
            'reason' => 'Updated through workflow',
            'page_context_url' => '/awards/recommendations?search=needle',
        ]);

        $this->assertRedirectContains('/awards/recommendations?search=needle');
    }

    public function testUpdateStatesFailWhenWorkflowInactive(): void
    {
        $this->deactivateWorkflows(['awards-recommendation-bulk-transition']);
        $recommendation = $this->createExistingRecommendation();
        $originalState = $recommendation->state;

        $this->mockServiceClean(RecommendationTransitionService::class, function () {
            $mock = $this->createMock(RecommendationTransitionService::class);
            $mock->expects($this->never())
                ->method('transitionMany');

            return $mock;
        });
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post($this->recommendationsUrl('updateStates'), [
            'ids' => (string)$recommendation->id,
            'newState' => 'In Consideration',
            'view' => 'Index',
            'status' => 'All',
        ]);

        $this->assertFlashMessage('The recommendation workflow is not currently available.', 'flash');
        $this->assertRedirect();
        $freshRecommendation = $this->recommendations->get((int)$recommendation->id);
        $this->assertSame($originalState, $freshRecommendation->state);
    }

    public function testUpdateStatesDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-bulk-transition');

        $dispatched = false;
        $events = [];
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched, &$events) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->exactly(2))
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched, &$events): array {
                    $events[] = $event;
                    if ($event === 'Awards.RecommendationStateChanged') {
                        $this->assertSame(123, $context['recommendationId']);
                        $this->assertSame('In Consideration', $context['newState']);

                        return [];
                    }

                    $dispatched = true;
                    $this->assertSame('Awards.RecommendationBulkTransitionRequested', $event);
                    $this->assertSame(['123'], $context['recommendationIds']);
                    $this->assertSame('In Consideration', $context['targetState']);

                    return [$this->successfulWorkflowDispatchResult([
                        'processedCount' => 1,
                        'recommendationIds' => [123],
                        'results' => [],
                    ])];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationTransitionService::class, function () {
            $mock = $this->createMock(RecommendationTransitionService::class);
            $mock->expects($this->never())->method('transitionMany');

            return $mock;
        });

        $this->post($this->recommendationsUrl('updateStates'), [
            'ids' => '123',
            'newState' => 'In Consideration',
            'view' => 'Index',
            'status' => 'All',
        ]);

        $this->assertTrue($dispatched);
        $this->assertSame(
            ['Awards.RecommendationBulkTransitionRequested', 'Awards.RecommendationStateChanged'],
            $events,
        );
        $this->assertRedirect();
    }

    public function testUpdateStatesTurboStreamShowsSingleSuccessFlash(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-bulk-transition');
        $recommendation = $this->createExistingRecommendation();

        $this->mockServiceClean(TriggerDispatcher::class, function () use ($recommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->exactly(2))
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use ($recommendation): array {
                    if ($event === 'Awards.RecommendationBulkTransitionRequested') {
                        $this->assertSame([(string)$recommendation->id], $context['recommendationIds']);

                        return [$this->successfulWorkflowDispatchResult([
                            'processedCount' => 1,
                            'recommendationIds' => [(int)$recommendation->id],
                            'results' => [],
                        ])];
                    }

                    if ($event === 'Awards.RecommendationStateChanged') {
                        $this->assertSame((int)$recommendation->id, $context['recommendationId']);
                    }

                    return [];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationTransitionService::class, function () {
            $mock = $this->createMock(RecommendationTransitionService::class);
            $mock->expects($this->never())->method('transitionMany');

            return $mock;
        });

        $this->configRequest([
            'headers' => [
                'Accept' => 'text/vnd.turbo-stream.html',
            ],
        ]);
        $this->post($this->recommendationsUrl('updateStates'), [
            'ids' => (string)$recommendation->id,
            'newState' => 'In Consideration',
            'page_context_url' => '/awards/recommendations?sort=member_sca_name&direction=desc',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('<turbo-stream action="replace" target="recommendations-grid-table"');
        $this->assertSame(
            1,
            substr_count((string)$this->_response->getBody(), 'The recommendations have been updated.'),
        );
    }

    public function testGroupRecommendationsFailWhenWorkflowInactive(): void
    {
        $this->deactivateWorkflows(['awards-recommendations-group']);
        $head = $this->createExistingRecommendation();
        $child = $this->createExistingRecommendation();

        $this->mockServiceClean(RecommendationGroupingService::class, function () {
            $mock = $this->createMock(RecommendationGroupingService::class);
            $mock->expects($this->never())
                ->method('groupRecommendations');

            return $mock;
        });
        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post($this->recommendationsUrl('groupRecommendations'), [
            'recommendation_ids' => [(string)$head->id, (string)$child->id],
        ]);

        $this->assertFlashMessage('The recommendation workflow is not currently available.', 'flash');
        $this->assertRedirectContains('/awards/recommendations');
        $freshChild = $this->recommendations->get((int)$child->id);
        $this->assertNull($freshChild->recommendation_group_id);
    }

    public function testRemoveFromGroupDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-remove-from-group');

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched): array {
                    $dispatched = true;
                    $this->assertSame('Awards.RecommendationRemoveFromGroupRequested', $event);
                    $this->assertSame(55, $context['recommendationId']);

                    return [$this->successfulWorkflowDispatchResult([
                        'recommendationId' => 55,
                        'formerHeadId' => 77,
                    ])];
                });

            return $mock;
        });
        $this->mockServiceClean(RecommendationGroupingService::class, function () {
            $mock = $this->createMock(RecommendationGroupingService::class);
            $mock->expects($this->never())->method('removeFromGroup');

            return $mock;
        });

        $this->post($this->recommendationsUrl('removeFromGroup'), [
            'recommendation_id' => '55',
        ]);

        $this->assertTrue($dispatched);
        $this->assertRedirectContains('/awards/recommendations/view/77');
    }

    public function testDeleteDispatchesWorkflowWhenActive(): void
    {
        $this->ensureActiveWorkflow('awards-recommendation-deleted');
        $recommendation = $this->createExistingRecommendation();

        $dispatched = false;
        $this->mockServiceClean(TriggerDispatcher::class, function () use (&$dispatched, $recommendation) {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->once())
                ->method('dispatch')
                ->willReturnCallback(function (string $event, array $context) use (&$dispatched, $recommendation): array {
                    $dispatched = true;
                    $this->assertSame('Awards.RecommendationDeleteRequested', $event);
                    $this->assertSame((int)$recommendation->id, $context['recommendationId']);

                    return [$this->successfulWorkflowDispatchResult([
                        'recommendationId' => (int)$recommendation->id,
                        'restoredChildCount' => 0,
                    ])];
                });

            return $mock;
        });

        $this->post($this->recommendationsUrl('delete', [(string)$recommendation->id]));

        $this->assertTrue($dispatched);
        $this->assertFlashMessage('The recommendation has been deleted.', 'flash');
        $this->assertRedirectContains('/awards/recommendations');
    }

    public function testDeleteFailsWhenWorkflowInactive(): void
    {
        $this->deactivateWorkflows(['awards-recommendation-deleted']);
        $recommendation = $this->createExistingRecommendation();

        $this->mockServiceClean(TriggerDispatcher::class, function () {
            $mock = $this->createMock(TriggerDispatcher::class);
            $mock->expects($this->never())->method('dispatch');

            return $mock;
        });

        $this->post($this->recommendationsUrl('delete', [(string)$recommendation->id]));

        $this->assertFlashMessage('The recommendation workflow is not currently available.', 'flash');
        $this->assertRedirectContains('/awards/recommendations');
        $this->assertNotNull($this->recommendations->get((int)$recommendation->id)->id);
    }

    /**
     * Mock a service and clear its stale DI constructor arguments.
     */
    private function mockServiceClean(string $class, Closure $factory): void
    {
        $this->mockService($class, $factory);
        $this->mockedServiceKeys[] = $class;
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

    /**
     * @param array<int, string> $slugs
     */
    private function deactivateWorkflows(array $slugs): void
    {
        $this->workflowDefinitions->updateAll(['is_active' => false], ['slug IN' => $slugs]);
    }

    private function recommendationsUrl(string $action, array $pass = []): string
    {
        $suffix = $pass === [] ? '' : '/' . implode('/', $pass);

        return match ($action) {
            'add' => '/awards/recommendations/add',
            'edit' => '/awards/recommendations/edit' . $suffix,
            'submitRecommendation' => '/awards/recommendations/submit-recommendation',
            'updateStates' => '/awards/recommendations/update-states',
            'groupRecommendations' => '/awards/recommendations/group-recommendations',
            'removeFromGroup' => '/awards/recommendations/remove-from-group',
            'delete' => '/awards/recommendations/delete' . $suffix,
            default => throw new InvalidArgumentException("Unsupported recommendations action {$action}"),
        };
    }

    private function createExistingRecommendation(): Recommendation
    {
        $member = $this->members->get(self::ADMIN_MEMBER_ID);
        $award = $this->awards->find()->select(['id'])->firstOrFail();
        $statuses = Recommendation::getStatuses();
        $status = array_key_first($statuses);
        $state = $statuses[$status][0];

        $recommendation = $this->recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$member->branch_id,
            'award_id' => (int)$award->id,
            'status' => $status,
            'state' => $state,
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)$member->sca_name,
            'member_sca_name' => (string)$member->sca_name,
            'contact_email' => (string)$member->email_address,
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Workflow dispatch integration test',
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'not_found' => false,
        ]);

        return $this->recommendations->saveOrFail($recommendation);
    }

    /**
     * @return array<string, mixed>
     */
    private function successfulLegacyMutationResult(Recommendation $recommendation): array
    {
        return [
            'success' => true,
            'recommendation' => $recommendation,
            'output' => [
                'recommendationId' => (int)$recommendation->id,
            ],
            'eventName' => null,
            'eventPayload' => null,
            'errorCode' => null,
            'message' => null,
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $data
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

    /**
     * @return array<string, mixed>
     */
    private function buildAuthenticatedSubmissionData(): array
    {
        $member = $this->members->get(self::TEST_MEMBER_AGATHA_ID, select: ['public_id', 'sca_name']);
        $award = $this->awards->find()->select(['id'])->firstOrFail();

        return [
            'award_id' => (int)$award->id,
            'member_sca_name' => (string)$member->sca_name,
            'member_public_id' => (string)$member->public_id,
            'reason' => 'Workflow dispatch integration test',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPublicSubmissionData(): array
    {
        $award = $this->awards->find()->select(['id'])->firstOrFail();

        return [
            'requester_sca_name' => 'Guest Requester',
            'contact_email' => 'guest@example.com',
            'contact_number' => '555-1212',
            'member_sca_name' => 'Unknown Candidate',
            'branch_id' => self::TEST_BRANCH_STARGATE_ID,
            'award_id' => (int)$award->id,
            'reason' => 'Guest workflow dispatch integration test',
            'not_found' => 'on',
        ];
    }
}
