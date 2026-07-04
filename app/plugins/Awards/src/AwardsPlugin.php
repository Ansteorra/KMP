<?php
declare(strict_types=1);

namespace Awards;

use App\KMP\KMPPluginInterface;
use App\KMP\StaticHelpers;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\ActionItems\ActionItemService;
use App\Services\ApprovalContext\ApprovalContextRendererRegistry;
use App\Services\NavigationRegistry;
use App\Services\ViewCellRegistry;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowApprovalManagerInterface;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Awards\Command\MaterializeBestowalTodosCommand;
use Awards\Command\MigrateAwardRecommendationsCommand;
use Awards\Command\ReconcileRecommendationStateCommand;
use Awards\Event\BestowalTodoCompletionListener;
use Awards\Event\RecommendationFeedbackApprovalListener;
use Awards\Services\AdHocBestowalService;
use Awards\Services\AwardApprovalResolverService;
use Awards\Services\AwardsNavigationProvider;
use Awards\Services\AwardsViewCellProvider;
use Awards\Services\AwardsWorkflowActions;
use Awards\Services\AwardsWorkflowConditions;
use Awards\Services\BestowalCancellationService;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalFinalizationService;
use Awards\Services\BestowalFormService;
use Awards\Services\BestowalGatheringLookupService;
use Awards\Services\BestowalNotificationVarsService;
use Awards\Services\BestowalQueryService;
use Awards\Services\BestowalRecommendationLinkService;
use Awards\Services\BestowalRecommendationSyncService;
use Awards\Services\BestowalTodoCompletionFormProvider;
use Awards\Services\BestowalTodoMaterializationService;
use Awards\Services\BestowalUpdateService;
use Awards\Services\CourtAgendaService;
use Awards\Services\RecommendationApprovalContextRenderer;
use Awards\Services\RecommendationApprovalDecisionService;
use Awards\Services\RecommendationApprovalProcessService;
use Awards\Services\RecommendationFeedbackContextRenderer;
use Awards\Services\RecommendationFeedbackService;
use Awards\Services\RecommendationFormService;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationMigrationService;
use Awards\Services\RecommendationQueryService;
use Awards\Services\RecommendationStateLogService;
use Awards\Services\RecommendationSubmissionService;
use Awards\Services\RecommendationTransitionService;
use Awards\Services\RecommendationUpdateService;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;

/**
 * Awards Plugin - Award recommendation management with state machine workflow.
 *
 * @package Awards
 * @see /docs/5.2-awards-plugin.md For complete documentation
 */
class AwardsPlugin extends BasePlugin implements KMPPluginInterface
{
    /**
     * @var int Migration order priority for database setup
     */
    protected int $_migrationOrder = 0;

    /**
     * @return int Migration order priority
     */
    public function getMigrationOrder(): int
    {
        return $this->_migrationOrder;
    }

    /**
     * @param array $config Plugin configuration including migrationOrder
     */
    public function __construct($config = [])
    {
        if (!isset($config['migrationOrder'])) {
            $config['migrationOrder'] = 0;
        }
        $this->_migrationOrder = $config['migrationOrder'];
    }

    /**
     * Initialize navigation, view cells, and version-controlled configuration.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        // Register navigation items instead of using event handlers
        NavigationRegistry::register(
            'Awards',
            [], // Static items (none for Awards)
            function ($user, $params) {
                return AwardsNavigationProvider::getNavigationItems($user, $params);
            },
        );

        // Register view cells with ViewCellRegistry
        ViewCellRegistry::register(
            'Awards',
            [], // Static cells (none for Awards)
            function ($urlParams, $user) {
                return AwardsViewCellProvider::getViewCells($urlParams, $user);
            },
        );

        ApprovalContextRendererRegistry::register(
            'AwardsFeedback',
            new RecommendationFeedbackContextRenderer(),
        );
        ApprovalContextRendererRegistry::register(
            'AwardsRecommendations',
            new RecommendationApprovalContextRenderer(),
        );
        ActionItemCompletionFormRegistry::register(
            'AwardsBestowals',
            new BestowalTodoCompletionFormProvider(),
        );

        EventManager::instance()->on(new RecommendationFeedbackApprovalListener());
        EventManager::instance()->on(new BestowalTodoCompletionListener());

        $currentConfigVersion = '26.02.22.a'; // Removed Awards.ViewConfig.* settings (unused)

        $configVersion = StaticHelpers::getAppSetting('Awards.configVersion', '0.0.0', null, true);
        if ($configVersion != $currentConfigVersion) {
            StaticHelpers::setAppSetting('Awards.configVersion', $currentConfigVersion, null, true);
        } // end if ($configVersion != $currentConfigVersion)

        // Always ensure default settings exist (idempotent — only creates if missing).
        StaticHelpers::getAppSetting('Awards.RecButtonClass', 'btn-warning', null, true);
        StaticHelpers::getAppSetting(
            'Member.AdditionalInfo.CallIntoCourt',
            'select:Never,With Notice,Without Notice|user|public',
            null,
            true,
        );
        StaticHelpers::getAppSetting(
            'Member.AdditionalInfo.CourtAvailability',
            'select:None,Morning,Evening,Any|user|public',
            null,
            true,
        );
        StaticHelpers::getAppSetting('Member.AdditionalInfo.PersonToGiveNoticeTo', 'text|user|public', null, true);
        StaticHelpers::getAppSetting('Plugin.Awards.Active', 'yes', null, true);
        StaticHelpers::getAppSetting('Awards.RecommendationStatesRequireCanViewHidden', yaml_emit([
            'No Action',
            'Linked',
            'Linked - Closed',
        ]), 'yaml', true);
        StaticHelpers::getAppSetting('Awards.RecommendationStatuses', yaml_emit([
            'In Progress' => [
                'Submitted',
                'In Consideration',
                'Awaiting Feedback',
                'Deferred till Later',
                'King Approved',
                'Queen Approved',
                'Linked',
            ],
            'Scheduling' => [
                'Need to Schedule',
            ],
            'To Give' => [
                'Scheduled',
                'Announced Not Given',
            ],
            'Closed' => [
                'Given',
                'No Action',
                'Linked - Closed',
            ],
        ]), 'yaml', true);
        StaticHelpers::getAppSetting('Awards.RecommendationStateRules', yaml_emit([
            'Need to Schedule' => [
                'Visible' => [
                    'planToGiveBlockTarget',
                ],
                'Disabled' => [
                    'domainTarget',
                    'awardTarget',
                    'specialtyTarget',
                    'scaMemberTarget',
                    'branchTarget',
                    'scaMemberTarget',
                ],
            ],
            'Scheduled' => [
                'Required' => [
                    'planToGiveEventTarget',
                ],
                'Visible' => [
                    'planToGiveBlockTarget',
                ],
                'Disabled' => [
                    'domainTarget',
                    'awardTarget',
                    'specialtyTarget',
                    'scaMemberTarget',
                    'branchTarget',
                    'scaMemberTarget',
                ],
            ],
            'Given' => [
                'Required' => [
                    'planToGiveEventTarget',
                    'givenDateTarget',
                ],
                'Visible' => [
                    'planToGiveBlockTarget',
                    'givenBlockTarget',
                ],
                'Disabled' => [
                    'domainTarget',
                    'awardTarget',
                    'specialtyTarget',
                    'scaMemberTarget',
                    'branchTarget',
                    'scaMemberTarget',
                ],
                'Set' => [
                    'close_reason' => 'Given',
                ],
            ],
            'No Action' => [
                'Required' => [
                    'closeReasonTarget',
                ],
                'Visible' => [
                    'closeReasonBlockTarget',
                    'closeReasonTarget',
                ],
                'Disabled' => [
                    'domainTarget',
                    'awardTarget',
                    'specialtyTarget',
                    'scaMemberTarget',
                    'branchTarget',
                    'courtAvailabilityTarget',
                    'callIntoCourtTarget',
                    'scaMemberTarget',
                ],
            ],
            'Linked' => [
                'Disabled' => [
                    'domainTarget',
                    'awardTarget',
                    'specialtyTarget',
                    'scaMemberTarget',
                    'branchTarget',
                    'courtAvailabilityTarget',
                    'callIntoCourtTarget',
                    'reasonTarget',
                    'contactEmailTarget',
                    'contactNumberTarget',
                    'personToNotifyTarget',
                ],
            ],
            'Linked - Closed' => [
                'Disabled' => [
                    'domainTarget',
                    'awardTarget',
                    'specialtyTarget',
                    'scaMemberTarget',
                    'branchTarget',
                    'courtAvailabilityTarget',
                    'callIntoCourtTarget',
                    'reasonTarget',
                    'contactEmailTarget',
                    'contactNumberTarget',
                    'personToNotifyTarget',
                ],
            ],
        ]), 'yaml', true);
    }

    /**
     * Configure plugin routes with JSON, PDF, and CSV format support.
     *
     * @param \Cake\Routing\RouteBuilder $routes The route builder to update
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->plugin(
            'Awards',
            ['path' => '/awards'],
            function (RouteBuilder $builder): void {
                $builder->setExtensions(['json', 'pdf', 'csv']);
                $builder->scope('/bestowals', function (RouteBuilder $bestowals): void {
                    $bestowals->connect(
                        '/bulk-complete-todo',
                        ['controller' => 'Bestowals', 'action' => 'bulkCompleteTodo'],
                    );
                    $bestowals->connect(
                        '/bulk-assign-gathering',
                        ['controller' => 'Bestowals', 'action' => 'bulkAssignGathering'],
                    );
                    $bestowals->connect(
                        '/gatherings-for-bestowal-auto-complete',
                        ['controller' => 'Bestowals', 'action' => 'gatheringsForBestowalAutoComplete'],
                    );
                    $bestowals->connect(
                        '/gatherings-for-bestowal-auto-complete/{id}',
                        ['controller' => 'Bestowals', 'action' => 'gatheringsForBestowalAutoComplete'],
                    )->setPass(['id']);
                });
                $builder->fallbacks();
            },
        );
        parent::routes($routes);
    }

    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update
     * @return \Cake\Http\MiddlewareQueue Updated middleware queue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    /**
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection Updated command collection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands = parent::console($commands);
        $commands->add('awards migrate_award_recommendations', MigrateAwardRecommendationsCommand::class);
        $commands->add('awards materialize_bestowal_todos', MaterializeBestowalTodosCommand::class);
        $commands->add('awards reconcile_recommendation_state', ReconcileRecommendationStateCommand::class);

        return $commands;
    }

    /**
     * @param \Cake\Core\ContainerInterface $container The container to update
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->add(MigrateAwardRecommendationsCommand::class)
            ->addArgument(TriggerDispatcher::class);
        $container->add(BestowalTodoMaterializationService::class)
            ->addArgument(ActionItemService::class);
        $container->add(RecommendationMigrationService::class)
            ->addArgument(TriggerDispatcher::class);
        $container->add(MaterializeBestowalTodosCommand::class)
            ->addArgument(BestowalTodoMaterializationService::class);
        $container->add(ReconcileRecommendationStateCommand::class)
            ->addArgument(RecommendationMigrationService::class);
        $container->add(RecommendationFormService::class);
        $container->add(AwardApprovalResolverService::class);
        $container->add(RecommendationApprovalProcessService::class);
        $container->add(RecommendationApprovalDecisionService::class)
            ->addArgument(WorkflowApprovalManagerInterface::class)
            ->addArgument(WorkflowEngineInterface::class);
        $container->add(RecommendationFeedbackService::class)
            ->addArgument(TriggerDispatcher::class);
        $container->add(RecommendationFeedbackContextRenderer::class);
        $container->add(RecommendationQueryService::class);
        $container->add(RecommendationSubmissionService::class);
        $container->add(RecommendationUpdateService::class);
        $container->add(RecommendationTransitionService::class);
        $container->add(RecommendationGroupingService::class);
        $container->add(RecommendationStateLogService::class);
        $container->add(BestowalCreationService::class);
        $container->add(BestowalRecommendationSyncService::class);
        $container->add(BestowalFinalizationService::class)
            ->addArgument(ActionItemService::class)
            ->addArgument(BestowalRecommendationSyncService::class);
        $container->add(BestowalCancellationService::class);
        $container->add(AdHocBestowalService::class);
        $container->add(BestowalQueryService::class);
        $container->add(CourtAgendaService::class);
        $container->add(BestowalNotificationVarsService::class);
        $container->add(BestowalFormService::class);
        $container->add(BestowalGatheringLookupService::class);
        $container->add(BestowalRecommendationLinkService::class);
        $container->add(BestowalUpdateService::class);

        // Register workflow actions and conditions for Awards plugin
        $container->add(AwardsWorkflowActions::class);
        $container->add(AwardsWorkflowConditions::class);
    }
}
