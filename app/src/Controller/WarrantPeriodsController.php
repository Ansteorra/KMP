<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\CsvExportService;

/**
 * Manages warrant period templates for standardized duration definitions.
 *
 * Provides administrative interface for creating, listing, and deleting period templates
 * used in warrant management. All operations require administrative authorization.
 *
 * @property \App\Model\Table\WarrantPeriodsTable $WarrantPeriods
 */
class WarrantPeriodsController extends AppController
{
    use DataverseGridTrait;

    /**
     * Configure authorization for period template operations.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authorization.Authorization');
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
    }

    /**
     * Display Dataverse grid for warrant periods.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action

        // Keep empty entity for add modal
        $emptyWarrantPeriod = $this->WarrantPeriods->newEmptyEntity();
        $this->set(compact('emptyWarrantPeriod'));
    }

    /**
     * Grid Data method - Provides Dataverse grid data for warrant periods
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'WarrantPeriods.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\WarrantPeriodsGridColumns::class,
            'baseQuery' => $this->WarrantPeriods->find(),
            'tableName' => 'WarrantPeriods',
            'defaultSort' => ['WarrantPeriods.start_date' => 'desc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'warrant-periods');
        }

        // Set view variables
        $this->set([
            'warrantPeriods' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\WarrantPeriodsGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'warrant-periods-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'warrant-periods-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'warrant-periods-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Create new warrant period template.
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $warrantPeriod = $this->WarrantPeriods->newEmptyEntity();
        $this->Authorization->authorize($warrantPeriod);
        if ($this->request->is('post')) {
            $warrantPeriod = $this->WarrantPeriods->patchEntity($warrantPeriod, $this->request->getData());
            if ($this->WarrantPeriods->save($warrantPeriod)) {
                $this->Flash->success(__('The warrant period has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant period could not be saved. Please, try again.'));
        }
        $this->set(compact('warrantPeriod'));
    }

    /**
     * Delete warrant period template.
     *
     * @param string|null $id Warrant Period id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $warrantPeriod = $this->WarrantPeriods->get($id);
        $this->Authorization->authorize($warrantPeriod);
        if ($this->WarrantPeriods->delete($warrantPeriod)) {
            $this->Flash->success(__('The warrant period has been deleted.'));
        } else {
            $this->Flash->error(__('The warrant period could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
