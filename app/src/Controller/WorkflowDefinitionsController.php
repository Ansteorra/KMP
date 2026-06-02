<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\ServiceResult;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Services\WorkflowEngine\WorkflowVersionManagerInterface;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowApproverResolverRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use Cake\Controller\ComponentRegistry;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use ReflectionClass;
use ReflectionMethod;

/**
 * WorkflowDefinitions Controller
 *
 * Manages workflow definitions, visual designer, and versioning.
 *
 * @property \App\Model\Table\WorkflowDefinitionsTable $WorkflowDefinitions
 */
class WorkflowDefinitionsController extends AppController
{
    protected ?string $defaultTable = 'WorkflowDefinitions';

    private WorkflowEngineInterface $engine;
    private WorkflowVersionManagerInterface $versionManager;

    /**
     * Constructor.
     *
     * @param \Cake\Http\ServerRequest $request Request instance
     * @param \App\Services\WorkflowEngine\WorkflowEngineInterface $engine Workflow engine
     * @param \App\Services\WorkflowEngine\WorkflowVersionManagerInterface $versionManager Version manager
     * @param \Cake\Controller\ComponentRegistry|null $components Component registry
     */
    public function __construct(
        ServerRequest $request,
        WorkflowEngineInterface $engine,
        WorkflowVersionManagerInterface $versionManager,
        ?ComponentRegistry $components = null,
    ) {
        parent::__construct($request, null, null, $components);
        $this->engine = $engine;
        $this->versionManager = $versionManager;
    }

    /**
     * Initialize controller.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'index',
            'designer',
            'loadVersion',
            'registry',
            'save',
            'updateMetadata',
            'publish',
            'add',
            'versions',
            'compareVersions',
            'toggleActive',
            'archive',
            'delete',
            'createDraft',
            'migrateInstances',
        );
    }

    /**
     * List all workflow definitions.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $instancesTable = $this->fetchTable('WorkflowInstances');
        $instanceCountQuery = $instancesTable->find()
            ->select(['count' => $instancesTable->find()->func()->count('*')])
            ->where(['WorkflowInstances.workflow_definition_id = WorkflowDefinitions.id']);

        $workflows = $definitionsTable->find()
            ->select(['instance_count' => $instanceCountQuery])
            ->enableAutoFields(true)
            ->contain(['CurrentVersion'])
            ->where(['WorkflowDefinitions.deleted IS' => null])
            ->orderBy(['WorkflowDefinitions.name' => 'ASC'])
            ->all();
        $this->set(compact('workflows'));
    }

    /**
     * Visual workflow designer page.
     *
     * @param int|null $id Workflow definition ID
     * @return \Cake\Http\Response|null|void
     */
    public function designer(?int $id = null)
    {
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $versionsTable = $this->fetchTable('WorkflowVersions');

        if ($id) {
            $workflow = $definitionsTable->get($id, contain: ['CurrentVersion']);
            $draftVersion = $versionsTable->find()
                ->where(['workflow_definition_id' => $id, 'status' => 'draft'])
                ->first();

            if (!$draftVersion && $workflow->current_version) {
                $versionManager = $this->getVersionManager();
                $result = $versionManager->createDraft(
                    $id,
                    $workflow->current_version->definition ?? ['nodes' => []],
                    $workflow->current_version->canvas_layout,
                    'Cloned from v' . $workflow->current_version->version_number,
                );
                if ($result->isSuccess()) {
                    $draftVersion = $versionsTable->get($result->data['versionId']);
                }
            }
        } else {
            $workflow = null;
            $draftVersion = null;
        }

        $this->set(compact('workflow', 'draftVersion'));
    }

    /**
     * API: Return a workflow version's definition as JSON.
     *
     * @param int $versionId Version ID
     * @return \Cake\Http\Response|null|void
     */
    public function loadVersion(int $versionId)
    {
        $this->request->allowMethod(['get']);
        $versionsTable = $this->fetchTable('WorkflowVersions');
        $version = $versionsTable->get($versionId);

        $data = [
            'definition' => $version->definition ?? ['nodes' => []],
            'canvasLayout' => $version->canvas_layout,
        ];
        $this->set('data', $data);
        $this->viewBuilder()->setOption('serialize', 'data');
        $this->response = $this->response->withType('application/json');
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * API: Return registry data for the designer palette.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function registry()
    {
        $this->request->allowMethod(['get']);
        $data = [
            'triggers' => WorkflowTriggerRegistry::getForDesigner(),
            'actions' => WorkflowActionRegistry::getForDesigner(),
            'conditions' => WorkflowConditionRegistry::getForDesigner(),
            'entities' => WorkflowEntityRegistry::getForDesigner(true),
            'resolvers' => WorkflowApproverResolverRegistry::getForDesigner(),
            'approvalOutputSchema' => WorkflowActionRegistry::APPROVAL_OUTPUT_SCHEMA,
            'builtinContext' => [
                ['path' => '$.instance.id', 'label' => 'Instance ID', 'type' => 'integer'],
                ['path' => '$.instance.created', 'label' => 'Instance Created', 'type' => 'datetime'],
                ['path' => '$.triggeredBy', 'label' => 'Triggered By (member ID)', 'type' => 'integer'],
            ],
        ];
        $this->set('data', $data);
        $this->viewBuilder()->setOption('serialize', 'data');
        $this->response = $this->response->withType('application/json');
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * API: Save a workflow definition and draft version.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function save()
    {
        $this->request->allowMethod(['post', 'put']);
        $data = $this->request->getData();
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');

        $versionManager = $this->getVersionManager();

        if (!empty($data['workflowId'])) {
            $workflowId = (int)$data['workflowId'];
            if (!empty($data['versionId'])) {
                $result = $versionManager->updateDraft(
                    (int)$data['versionId'],
                    $data['definition'] ?? [],
                    $data['canvasLayout'] ?? null,
                    $data['changeNotes'] ?? null,
                );
            } else {
                $result = $versionManager->createDraft(
                    $workflowId,
                    $data['definition'] ?? [],
                    $data['canvasLayout'] ?? null,
                    $data['changeNotes'] ?? null,
                );
            }
        } else {
            $workflow = $definitionsTable->newEntity([
                'name' => $data['name'] ?? 'New Workflow',
                'slug' => $data['slug'] ?? 'workflow-' . time(),
                'description' => $data['description'] ?? '',
                'trigger_type' => $data['triggerType'] ?? 'event',
                'trigger_config' => $data['triggerConfig'] ?? null,
                'entity_type' => $data['entityType'] ?? null,
                'execution_mode' => $data['executionMode'] ?? 'durable',
            ]);
            if ($definitionsTable->save($workflow)) {
                $result = $versionManager->createDraft(
                    $workflow->id,
                    $data['definition'] ?? ['nodes' => []],
                    $data['canvasLayout'] ?? null,
                    'Initial draft',
                );
                $result->data['workflowId'] = $workflow->id;
            } else {
                $result = new ServiceResult(false, 'Failed to create workflow definition');
            }
        }

        return $this->buildServiceResultResponse($result);
    }

    /**
     * API: Update workflow definition metadata from the designer.
     *
     * @param int $id Workflow definition ID
     * @return \Cake\Http\Response
     */
    public function updateMetadata(int $id): Response
    {
        $this->request->allowMethod(['post', 'put']);
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $workflow = $definitionsTable->get($id);
        $workflow = $definitionsTable->patchEntity($workflow, $this->request->getData(), [
            'fields' => [
                'name',
                'slug',
                'description',
                'trigger_type',
                'entity_type',
                'execution_mode',
            ],
        ]);

        if ($workflow->hasErrors()) {
            return $this->buildServiceResultResponse(new ServiceResult(
                false,
                $this->formatValidationErrors($workflow->getErrors()),
            ));
        }

        if (!$definitionsTable->save($workflow)) {
            return $this->buildServiceResultResponse(new ServiceResult(
                false,
                $this->formatValidationErrors($workflow->getErrors()) ?: __('Could not update workflow details.'),
            ));
        }

        return $this->buildServiceResultResponse(new ServiceResult(true, null, [
            'workflow' => [
                'id' => (int)$workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'description' => $workflow->description,
                'triggerType' => $workflow->trigger_type,
                'entityType' => $workflow->entity_type,
                'executionMode' => $workflow->execution_mode,
            ],
        ]));
    }

    /**
     * API: Publish a draft workflow version.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function publish()
    {
        $this->request->allowMethod(['post']);
        $versionId = (int)$this->request->getData('versionId');
        $currentUser = $this->request->getAttribute('identity');

        $versionManager = $this->getVersionManager();
        $result = $versionManager->publish($versionId, $currentUser->id);

        return $this->buildServiceResultResponse($result);
    }

    /**
     * Form to create a new workflow definition.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $workflow = $definitionsTable->newEmptyEntity();

        if ($this->request->is('post')) {
            $workflow = $definitionsTable->patchEntity($workflow, $this->request->getData());
            if ($definitionsTable->save($workflow)) {
                $this->Flash->success(__('Workflow definition created.'));

                return $this->redirect(['action' => 'designer', $workflow->id]);
            }
            $this->Flash->error(__('Could not save workflow definition.'));
        }
        $this->set(compact('workflow'));
    }

    /**
     * Show version history for a workflow definition.
     *
     * @param int $definitionId Workflow definition ID
     * @return \Cake\Http\Response|null|void
     */
    public function versions(int $definitionId)
    {
        $workflow = $this->fetchTable('WorkflowDefinitions')->get($definitionId);
        $versionManager = $this->getVersionManager();
        $versions = $versionManager->getVersionHistory($definitionId);
        $this->set(compact('workflow', 'versions'));
    }

    /**
     * API: Compare two workflow versions and return their diff.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function compareVersions()
    {
        $this->request->allowMethod(['get']);
        $v1 = (int)$this->request->getQuery('v1');
        $v2 = (int)$this->request->getQuery('v2');
        $versionManager = $this->getVersionManager();
        $diff = $versionManager->compareVersions($v1, $v2);
        $this->set('diff', $diff);
        $this->viewBuilder()->setOption('serialize', 'diff');
        $this->response = $this->response->withType('application/json');
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * API: Toggle a workflow definition's is_active flag.
     *
     * @param int $id Workflow definition ID
     * @return \Cake\Http\Response|null|void
     */
    public function toggleActive(int $id)
    {
        $this->request->allowMethod(['post']);
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $workflow = $definitionsTable->get($id);
        if ($workflow->deleted !== null) {
            if ($this->request->is('ajax')) {
                $result = [
                    'success' => false,
                    'message' => __('Archived workflows cannot be activated.'),
                ];
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', 'result');
                $this->response = $this->response->withType('application/json');
                $this->viewBuilder()->setClassName('Json');

                return;
            }

            $this->Flash->error(__('Archived workflows cannot be activated.'));

            return $this->redirect(['action' => 'index']);
        }

        $workflow->is_active = !$workflow->is_active;
        $definitionsTable->save($workflow);

        if ($this->request->is('ajax')) {
            $result = ['success' => true, 'is_active' => $workflow->is_active];
            $this->set('result', $result);
            $this->viewBuilder()->setOption('serialize', 'result');
            $this->response = $this->response->withType('application/json');
            $this->viewBuilder()->setClassName('Json');
        } else {
            $this->Flash->success(__('Workflow status updated.'));

            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Archive a workflow definition and keep its run history.
     *
     * @param int $id Workflow definition ID
     * @return \Cake\Http\Response
     */
    public function archive(int $id): Response
    {
        $this->request->allowMethod(['post']);
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $workflow = $definitionsTable->get($id);

        if ($definitionsTable->archiveDefinition($workflow)) {
            $this->Flash->success(__('Workflow "{0}" was archived.', $workflow->name));
        } else {
            $this->Flash->error(__('Workflow "{0}" could not be archived.', $workflow->name));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Delete an unused workflow definition.
     *
     * @param int $id Workflow definition ID
     * @return \Cake\Http\Response
     */
    public function delete(int $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $workflow = $definitionsTable->get($id);
        $workflowName = $workflow->name;

        if ($definitionsTable->hasExecutionHistory($id)) {
            $this->Flash->error(__(
                'Workflow "{0}" has run history and must be archived instead of deleted.',
                $workflowName,
            ));

            return $this->redirect(['action' => 'index']);
        }

        if ($definitionsTable->deleteUnusedDefinition($workflow)) {
            $this->Flash->success(__('Workflow "{0}" was deleted.', $workflowName));
        } else {
            $this->Flash->error(__('Workflow "{0}" could not be deleted.', $workflowName));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * API: Create a new draft version from the current published version.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function createDraft()
    {
        $this->request->allowMethod(['post']);
        $workflowId = (int)$this->request->getData('workflowId');
        $definitionsTable = $this->fetchTable('WorkflowDefinitions');
        $workflow = $definitionsTable->get($workflowId, contain: ['CurrentVersion']);

        $versionManager = $this->getVersionManager();
        $definition = $workflow->current_version->definition ?? ['nodes' => []];
        $canvasLayout = $workflow->current_version->canvas_layout ?? null;
        $result = $versionManager->createDraft(
            $workflowId,
            $definition,
            $canvasLayout,
            'Cloned from v' . ($workflow->current_version->version_number ?? '0'),
        );

        return $this->buildServiceResultResponse($result);
    }

    /**
     * API: Migrate running instances to a specified version.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function migrateInstances()
    {
        $this->request->allowMethod(['post']);
        $versionId = (int)$this->request->getData('versionId');
        $instancesTable = $this->fetchTable('WorkflowInstances');
        $version = $this->fetchTable('WorkflowVersions')->get($versionId);
        $currentUser = $this->request->getAttribute('identity');

        $instances = $instancesTable->find()
            ->where([
                'workflow_definition_id' => $version->workflow_definition_id,
                'status IN' => ['active', 'waiting'],
            ])
            ->all();

        $versionManager = $this->getVersionManager();
        $migrated = 0;
        $errors = [];

        foreach ($instances as $instance) {
            $migrationResult = $versionManager->migrateInstance(
                $instance->id,
                $versionId,
                $currentUser->id,
            );
            if ($migrationResult->isSuccess()) {
                $migrated++;
            } else {
                $errors[] = "Instance {$instance->id}: " . $migrationResult->getError();
            }
        }

        $result = [
            'success' => empty($errors),
            'message' => __('Migrated {0} running instance(s) to version {1}.', $migrated, $version->version_number),
            'errors' => $errors,
        ];
        $this->set('result', $result);
        $this->viewBuilder()->setOption('serialize', 'result');
        $this->response = $this->response->withType('application/json');
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Return JSON list of entity policy classes in the system.
     *
     * @return void
     */
    public function policyClasses()
    {
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);

        $results = [];

        $appPolicyDir = APP . 'Policy' . DS;
        if (is_dir($appPolicyDir)) {
            foreach (glob($appPolicyDir . '*Policy.php') as $file) {
                $className = basename($file, '.php');
                if ($this->isEntityPolicy($className)) {
                    $fqcn = 'App\\Policy\\' . $className;
                    $results[] = [
                        'class' => $fqcn,
                        'label' => $this->policyLabel($className),
                    ];
                }
            }
        }

        $pluginsDir = ROOT . DS . 'plugins' . DS;
        if (is_dir($pluginsDir)) {
            foreach (glob($pluginsDir . '*/src/Policy/*Policy.php') as $file) {
                $className = basename($file, '.php');
                if ($this->isEntityPolicy($className)) {
                    $relative = str_replace($pluginsDir, '', $file);
                    $pluginName = explode(DS, $relative)[0];
                    $fqcn = $pluginName . '\\Policy\\' . $className;
                    $results[] = [
                        'class' => $fqcn,
                        'label' => $this->policyLabel($className),
                    ];
                }
            }
        }

        sort($results);
        $this->set('policyClasses', $results);
        $this->viewBuilder()->setOption('serialize', ['policyClasses']);
        $this->response = $this->response->withType('application/json');
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Return JSON list of public 'can*' methods for a given policy class.
     *
     * @return void
     */
    public function policyActions()
    {
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);

        $className = $this->request->getQuery('class');
        $results = [];

        if (
            $className
            && class_exists($className)
            && str_ends_with($className, 'Policy')
            && str_contains($className, '\\Policy\\')
        ) {
            $reflection = new ReflectionClass($className);
            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (
                    str_starts_with($method->getName(), 'can')
                    && $method->getDeclaringClass()->getName() === $className
                ) {
                    $action = $method->getName();
                    $results[] = [
                        'action' => $action,
                        'label' => $this->actionLabel($action),
                    ];
                }
            }
        }

        $this->set('policyActions', $results);
        $this->viewBuilder()->setOption('serialize', ['policyActions']);
        $this->response = $this->response->withType('application/json');
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Return JSON list of app settings for the workflow designer.
     *
     * @return void
     */
    public function appSettings(): void
    {
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);

        $settingsTable = $this->fetchTable('AppSettings');
        $settings = $settingsTable->find()
            ->select(['name', 'value', 'type'])
            ->orderBy(['name' => 'ASC'])
            ->all();

        $results = [];
        foreach ($settings as $setting) {
            $results[] = [
                'name' => $setting->name,
                'value' => $setting->value,
                'type' => $setting->type,
            ];
        }

        $this->set('appSettings', $results);
        $this->viewBuilder()->setOption('serialize', ['appSettings']);
        $this->response = $this->response->withType('application/json');
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Build a flat JSON response from a ServiceResult.
     */
    private function buildServiceResultResponse(ServiceResult $result): Response
    {
        $responseData = ['success' => $result->success];
        if ($result->reason) {
            $responseData['reason'] = $result->reason;
        }
        if ($result->data) {
            $responseData = array_merge($responseData, (array)$result->data);
        }
        $response = $this->response->withType('application/json')
            ->withStringBody(json_encode($responseData));
        if (!$result->success) {
            $response = $response->withStatus(422);
        }

        return $response;
    }

    /**
     * Flatten Cake validation errors for JSON API responses.
     *
     * @param array<string, mixed> $errors Entity validation/rule errors
     * @return string
     */
    private function formatValidationErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $field => $fieldErrors) {
            if (!is_array($fieldErrors)) {
                continue;
            }
            foreach ($fieldErrors as $message) {
                if (is_array($message)) {
                    continue;
                }
                $messages[] = sprintf('%s: %s', $field, $message);
            }
        }

        return implode('; ', $messages);
    }

    /**
     * Get workflow version manager.
     *
     * @return \App\Services\WorkflowEngine\WorkflowVersionManagerInterface
     */
    private function getVersionManager(): WorkflowVersionManagerInterface
    {
        return $this->versionManager;
    }

    /**
     * Check whether a policy class belongs to an entity.
     *
     * @param string $className Class name
     * @return bool
     */
    private function isEntityPolicy(string $className): bool
    {
        if ($className === 'BasePolicy') {
            return false;
        }
        if (str_ends_with($className, 'TablePolicy')) {
            return false;
        }
        if (str_ends_with($className, 'ControllerPolicy')) {
            return false;
        }

        return true;
    }

    /**
     * Convert a policy class name to a human-readable label.
     *
     * @param string $className Class name
     * @return string
     */
    private function policyLabel(string $className): string
    {
        return trim(preg_replace('/([a-z])([A-Z])/', '$1 $2', $className));
    }

    /**
     * Convert a policy action name to a human-readable label.
     *
     * @param string $action Action method name
     * @return string
     */
    private function actionLabel(string $action): string
    {
        return trim(ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $action)));
    }
}
