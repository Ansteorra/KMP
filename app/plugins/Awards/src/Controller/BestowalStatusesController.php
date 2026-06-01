<?php
declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;
use App\Services\CsvExportService;
use Awards\KMP\GridColumns\BestowalStatusesGridColumns;
use Awards\Model\Entity\Bestowal;
use Cake\Http\Exception\NotFoundException;

/**
 * CRUD operations for bestowal status management.
 *
 * @property \Awards\Model\Table\BestowalStatusesTable $BestowalStatuses
 */
class BestowalStatusesController extends AppController
{
    use DataverseGridTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
    }

    /**
     * List all bestowal statuses.
     *
     * @return void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Grid data endpoint for statuses listing.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $baseQuery = $this->BestowalStatuses->find()
            ->contain(['BestowalStates']);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.BestowalStatuses.index.main',
            'gridColumnsClass' => BestowalStatusesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'BestowalStatuses',
            'defaultSort' => ['BestowalStatuses.sort_order' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => false,
            'canExportCsv' => false,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'bestowal-statuses');
        }

        foreach ($result['data'] as $status) {
            $status->state_count = count($status->bestowal_states ?? []);
        }

        $this->set([
            'statuses' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => BestowalStatusesGridColumns::getSearchableColumns(),
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

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'bestowal-statuses-grid-table') {
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'bestowal-statuses-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('data', $result['data']);
            $this->set('frameId', 'bestowal-statuses-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * View status details with associated states.
     *
     * @param string|null $id Status ID
     * @return void
     */
    public function view(?string $id = null): void
    {
        $status = $this->BestowalStatuses->get($id, contain: [
            'BestowalStates' => function ($q) {
                return $q->orderBy(['BestowalStates.sort_order' => 'ASC']);
            },
        ]);
        if (!$status) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($status);
        $this->set(compact('status'));
    }

    /**
     * Create a new status.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $status = $this->BestowalStatuses->newEmptyEntity();
        if ($this->request->is('post')) {
            $status = $this->BestowalStatuses->patchEntity(
                $status,
                $this->request->getData(),
            );
            if ($this->BestowalStatuses->save($status)) {
                Bestowal::clearCache();
                $this->Flash->success(__('The Bestowal Status has been saved.'));

                return $this->redirect(['action' => 'view', $status->id]);
            }
            $this->Flash->error(__('The Bestowal Status could not be saved. Please, try again.'));
        }
        $this->set(compact('status'));
    }

    /**
     * Edit an existing status.
     *
     * @param string|null $id Status ID
     * @return \Cake\Http\Response|null|void
     */
    public function edit($id = null)
    {
        $status = $this->BestowalStatuses->get($id);
        if (!$status) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($status);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $oldName = $status->name;
            $status = $this->BestowalStatuses->patchEntity(
                $status,
                $this->request->getData(),
            );

            if ($this->BestowalStatuses->save($status)) {
                if ($oldName !== $status->name) {
                    $this->cascadeStatusRename($oldName, $status->name);
                }
                Bestowal::clearCache();
                $this->Flash->success(__('The Bestowal Status has been saved.'));

                return $this->redirect(['action' => 'view', $status->id]);
            }
            $this->Flash->error(__('The Bestowal Status could not be saved. Please, try again.'));
        }
        $this->set(compact('status'));
    }

    /**
     * Delete a status.
     *
     * @param string|null $id Status ID
     * @return \Cake\Http\Response|null
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $status = $this->BestowalStatuses->get($id, contain: ['BestowalStates']);
        if (!$status) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($status);

        if (!empty($status->bestowal_states)) {
            $this->Flash->error(__(
                'Cannot delete this status because it still has {0} state(s). Remove or reassign all states first.',
                count($status->bestowal_states),
            ));

            return $this->redirect(['action' => 'view', $status->id]);
        }

        if ($this->BestowalStatuses->delete($status)) {
            Bestowal::clearCache();
            $this->Flash->success(__('The Bestowal Status has been deleted.'));
        } else {
            $this->Flash->error(__('The Bestowal Status could not be deleted. Please, try again.'));

            return $this->redirect(['action' => 'view', $status->id]);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Cascade a status name change to bestowals and state transition logs.
     *
     * @param string $oldName Previous status name
     * @param string $newName New status name
     * @return void
     */
    private function cascadeStatusRename(string $oldName, string $newName): void
    {
        $bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $bestowalsTable->updateAll(
            ['status' => $newName],
            ['status' => $oldName],
        );

        $logsTable = $this->fetchTable('Awards.BestowalsStatesLogs');
        $logsTable->updateAll(
            ['from_status' => $newName],
            ['from_status' => $oldName],
        );
        $logsTable->updateAll(
            ['to_status' => $newName],
            ['to_status' => $oldName],
        );
    }
}
