<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\AppSettingsGridColumns;
use App\KMP\GridRowDomId;
use App\KMP\StaticHelpers;
use Cake\Http\Response;
use App\Services\CsvExportService;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * AppSettings Controller
 *
 * @property \App\Model\Table\AppSettingsTable $AppSettings
 */
class AppSettingsController extends AppController
{
    use DataverseGridTrait;

    /**
     * CSV export service dependency injection
     *
     * @var array<string> Service injection configuration
     */
    public static array $inject = [CsvExportService::class];

    /**
     * CSV export service instance
     *
     * @var \App\Services\CsvExportService
     */
    protected CsvExportService $csvExportService;

    /**
     * Set up this component.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'gridData', 'toYaml');
    }

    /**
     * Allow public reads of stored app setting assets.
     *
     * @param \Cake\Event\EventInterface $event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['asset']);
    }

    /**
     * Index method - Renders dv_grid element that lazy-loads grid data
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Set empty entity for the add modal form
        $emptyAppSetting = $this->AppSettings->newEmptyEntity();
        $this->set(compact('emptyAppSetting'));
    }

    /**
     * Grid Data - Returns grid content with toolbar and table
     *
     * This action is called by turbo-frame to load the complete grid or just the table.
     * Also supports CSV export when export=csv query parameter is present.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Use unified trait for grid processing (saved views mode)
        $result = $this->processDataverseGrid([
            'gridKey' => 'AppSettings.index.main',
            'gridColumnsClass' => AppSettingsGridColumns::class,
            'baseQuery' => $this->AppSettings->find(),
            'tableName' => 'AppSettings',
            'defaultSort' => ['AppSettings.name' => 'ASC'],
            'defaultPageSize' => 50,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'app_settings');
        }

        // Set view variables
        $this->set([
            'appSettings' => $result['data'],
            'gridState' => $result['gridState'],
            'emptyAppSetting' => $this->AppSettings->newEmptyEntity(),
            'rowActions' => AppSettingsGridColumns::getRowActions(),
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'app-settings-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'app-settings-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'app-settings-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $appSetting = $this->AppSettings->newEmptyEntity();
        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $settingType = (string)($data['type'] ?? 'string');
            if (in_array($settingType, ['file', 'image'], true)) {
                $uploadedFile = $this->request->getData('asset_file');
                if (
                    !$uploadedFile instanceof UploadedFileInterface
                    || $uploadedFile->getError() === UPLOAD_ERR_NO_FILE
                ) {
                    $this->Flash->error(__('Please upload a file for image and file app settings.'));

                    return $this->redirect(['action' => 'index']);
                }

                try {
                    $data['value'] = $this->AppSettings->assetValueFromUpload($settingType, $uploadedFile);
                } catch (InvalidArgumentException $exception) {
                    $this->Flash->error(__($exception->getMessage()));

                    return $this->redirect(['action' => 'index']);
                }
            }
            $appSetting = $this->AppSettings->patchEntity(
                $appSetting,
                $data,
            );
            $this->Authorization->authorize($appSetting);
            if ($this->AppSettings->save($appSetting)) {
                $this->Flash->success(__('The app setting has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(
                __('The app setting could not be saved. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Edit method - Supports turbo-frame modal display
     *
     * @param string|null $id App Setting id.
     * @return \Cake\Http\Response|null|void Renders modal or redirects on POST
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        $appSetting = $this->AppSettings->get($id, contain: []);
        if (!$appSetting) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($appSetting);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $settingType = $appSetting->name === 'Backup.encryptionKey' ? 'password' : ($appSetting->type ?? 'string');
            if (in_array($settingType, ['file', 'image'], true)) {
                $uploadedFile = $this->request->getData('asset_file');
                if (
                    !$uploadedFile instanceof UploadedFileInterface
                    || $uploadedFile->getError() === UPLOAD_ERR_NO_FILE
                ) {
                    $this->Flash->success(__('No changes were made.'));

                    return $this->renderAppSettingsGridTurboResponse(
                        $this->getPageContextUrl(),
                        (int)$appSetting->id,
                    );
                }

                try {
                    $value = $this->AppSettings->assetValueFromUpload($settingType, $uploadedFile);
                } catch (InvalidArgumentException $exception) {
                    $this->Flash->error(__($exception->getMessage()));
                    $this->set(compact('appSetting'));
                    $this->viewBuilder()->setLayout('turbo_frame');

                    return;
                }
            } else {
                $value = (string)$this->request->getData('raw_value', '');
            }

            if ($settingType === 'password' && trim($value) === '') {
                $this->Flash->success(__('No changes were made.'));

                return $this->renderAppSettingsGridTurboResponse(
                    $this->getPageContextUrl(),
                    (int)$appSetting->id,
                );
            }

            $result = StaticHelpers::setAppSetting($appSetting->name, $value, $settingType, $appSetting->required);
            if ($result) {
                $this->Flash->success(__('The app setting has been saved.'));

                return $this->renderAppSettingsGridTurboResponse(
                    $this->getPageContextUrl(),
                    (int)$appSetting->id,
                );
            }
            $this->Flash->error(
                __('The app setting could not be saved. Please, try again.'),
            );
        }

        // Render modal form (GET request or validation error)
        $this->set(compact('appSetting'));
        $this->viewBuilder()->setLayout('turbo_frame');
    }

    /**
     * Publicly serve a database-backed app setting asset.
     *
     * @param string|null $name App setting name
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function asset(?string $name = null)
    {
        $this->Authorization->skipAuthorization();
        if ($name === null || $name === '') {
            throw new NotFoundException();
        }

        $payload = $this->AppSettings->getAssetPayload($name);
        if ($payload === null) {
            throw new NotFoundException();
        }

        $body = base64_decode((string)$payload['data'], true);
        if ($body === false) {
            throw new NotFoundException();
        }

        $etag = '"' . (string)$payload['sha256'] . '"';
        if ($this->request->getHeaderLine('If-None-Match') === $etag) {
            return $this->response
                ->withStatus(304)
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
        }

        return $this->response
            ->withHeader('Content-Type', (string)$payload['mime'])
            ->withHeader('Content-Length', (string)strlen($body))
            ->withHeader('Content-Disposition', 'inline; filename="' . addslashes((string)$payload['filename']) . '"')
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable')
            ->withStringBody($body);
    }

    /**
     * Delete method
     *
     * @param string|null $id App Setting id.
     * @return void Renders turbo-stream to close modal and refresh grid.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $appSetting = $this->AppSettings->get($id);
        if (!$appSetting) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($appSetting);
        if ($appSetting->required) {
            $this->Flash->error(
                __('The app setting is required and cannot be deleted.'),
            );
        } elseif ($this->AppSettings->deleteAppSetting($appSetting->name)) {
            $this->Flash->success(__('The app setting has been deleted.'));

            return $this->renderAppSettingsGridTurboResponse(
                $this->getPageContextUrl(),
                (int)$appSetting->id,
                true,
            );
        } else {
            $this->Flash->error(
                __('The app setting could not be deleted. Please, try again.'),
            );
        }

        return $this->renderAppSettingsGridTurboResponse(
            $this->getPageContextUrl(),
            (int)$appSetting->id,
        );
    }

    /**
     * Resolve targeted row sync after a single app setting save.
     *
     * @return array{action: string, rowDomId: string, rowHtml?: string}|null Null → full table refresh
     */
    private function resolveAppSettingGridRowSync(int $appSettingId, ?string $pageContextUrl): ?array
    {
        if (!$this->matchesGridIndexPath($pageContextUrl, '#/app-settings/?$#')) {
            return null;
        }

        $tableFrameId = 'app-settings-grid-table';
        $rowDomId = GridRowDomId::fromTableFrameId($tableFrameId, $appSettingId);

        return $this->withPageContextQuery($pageContextUrl, function () use (
            $appSettingId,
            $rowDomId,
            $tableFrameId,
        ): ?array {
            $baseQuery = $this->AppSettings->find()->where(['AppSettings.id' => $appSettingId]);
            $result = $this->processDataverseGrid([
                'gridKey' => 'AppSettings.index.main',
                'gridColumnsClass' => AppSettingsGridColumns::class,
                'baseQuery' => $baseQuery,
                'tableName' => 'AppSettings',
                'defaultSort' => ['AppSettings.name' => 'ASC'],
                'defaultPageSize' => 50,
                'showAllTab' => false,
                'canAddViews' => false,
                'canFilter' => true,
                'canExportCsv' => false,
            ]);

            $gridData = $result['data'];
            if (is_array($gridData)) {
                $appSettings = $gridData;
            } elseif ($gridData instanceof \Traversable) {
                $appSettings = iterator_to_array($gridData, false);
            } else {
                $appSettings = [];
            }
            if ($appSettings === []) {
                return [
                    'action' => 'remove',
                    'rowDomId' => $rowDomId,
                ];
            }

            $appSetting = $appSettings[0];
            $rowActions = AppSettingsGridColumns::getRowActions();
            $gridState = $result['gridState'];
            $visibleColumns = $gridState['columns']['visible'];
            if (!is_array($visibleColumns)) {
                $visibleColumns = array_values($visibleColumns);
            }

            $rowHtml = $this->renderDataverseTableRowElement([
                'row' => $appSetting,
                'columns' => $gridState['columns']['all'],
                'visibleColumns' => $visibleColumns,
                'controllerName' => 'grid-view',
                'primaryKey' => $gridState['config']['primaryKey'],
                'gridKey' => $gridState['config']['gridKey'],
                'rowActions' => $rowActions,
                'user' => $this->request->getAttribute('identity'),
                'enableBulkSelection' => false,
                'rowDomIdPrefix' => preg_replace('/-table$/', '', $tableFrameId),
                'showActionsColumn' => $rowActions !== [],
            ]);

            return [
                'action' => 'replace',
                'rowDomId' => $rowDomId,
                'rowHtml' => $rowHtml,
            ];
        });
    }

    /**
     * Turbo-stream response for grid-origin app setting saves.
     */
    private function renderAppSettingsGridTurboResponse(
        ?string $pageContext,
        int $appSettingId,
        bool $forceRemove = false,
    ): Response {
        $gridRoute = ['controller' => 'AppSettings', 'action' => 'gridData'];

        if (
            $this->wantsTurboStreamRequest()
            && $pageContext !== null
            && ($this->isGridOriginRequest($pageContext) || $this->matchesGridIndexPath($pageContext, '#/app-settings/?$#'))
        ) {
            if ($forceRemove && $this->matchesGridIndexPath($pageContext, '#/app-settings/?$#')) {
                $rowDomId = GridRowDomId::fromTableFrameId('app-settings-grid-table', $appSettingId);

                return $this->renderTurboRemoveGridRow($rowDomId);
            }

            $sync = $this->resolveAppSettingGridRowSync($appSettingId, $pageContext);
            if ($sync !== null) {
                if ($sync['action'] === 'remove') {
                    return $this->renderTurboRemoveGridRow($sync['rowDomId']);
                }

                return $this->renderTurboReplaceGridRow(
                    $sync['rowDomId'],
                    $sync['rowHtml'] ?? '',
                );
            }
        }

        return $this->renderTurboCloseModal('app-settings-grid-table', $gridRoute, $pageContext);
    }
}
