<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Services\CsvExportService;
use Cake\Http\Exception\NotFoundException;

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

    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'gridData', 'toYaml');
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
            'gridColumnsClass' => \App\KMP\GridColumns\AppSettingsGridColumns::class,
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
            'rowActions' => \App\KMP\GridColumns\AppSettingsGridColumns::getRowActions(),
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
            $appSetting = $this->AppSettings->patchEntity(
                $appSetting,
                $this->request->getData(),
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
            $value = $this->request->getData('raw_value');
            $result = StaticHelpers::setAppSetting($appSetting->name, $value, $appSetting->type, $appSetting->required);
            if ($result) {
                $this->Flash->success(__('The app setting has been saved.'));

                // Read and clear flash messages before rendering turbo-stream
                $flashMessages = $this->request->getSession()->read('Flash');
                $this->request->getSession()->delete('Flash');

                // Return turbo-stream response to close modal and refresh grid
                $this->response = $this->response->withType('text/vnd.turbo-stream.html');
                $this->viewBuilder()->disableAutoLayout();
                $this->viewBuilder()->setTemplate('turbo_close_modal');
                $this->set('refreshFrame', 'app-settings-grid-table');
                $this->set('flashMessages', $flashMessages);
                return;
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
     * Delete method
     *
     * @param string|null $id App Setting id.
     * @return \Cake\Http\Response|null Redirects to index.
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
        } else {
            $this->Flash->error(
                __('The app setting could not be deleted. Please, try again.'),
            );
        }

        // Read and clear flash messages before rendering turbo-stream
        $flashMessages = $this->request->getSession()->read('Flash');
        $this->request->getSession()->delete('Flash');

        // Return turbo-stream response to refresh grid in-place
        $this->response = $this->response->withType('text/vnd.turbo-stream.html');
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('turbo_close_modal');
        $this->set('refreshFrame', 'app-settings-grid-table');
        $this->set('flashMessages', $flashMessages);
    }
}