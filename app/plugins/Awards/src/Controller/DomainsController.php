<?php

declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;

/**
 * CRUD operations for award domain management.
 *
 * Domains are the top-level categorization in the award hierarchy.
 *
 * @property \Awards\Model\Table\DomainsTable $Domains
 * @see /docs/5.2-awards-plugin.md For award hierarchy documentation
 */
class DomainsController extends AppController
{
    use DataverseGridTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add", "gridData");
    }

    /**
     * List all award domains.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Grid data for Domains listing via Turbo Frame requests.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(\App\Services\CsvExportService $csvExportService)
    {
        $baseQuery = $this->Domains->find();

        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Domains.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\DomainsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Domains',
            'defaultSort' => ['Domains.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'award-domains');
        }

        $this->set([
            'domains' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\DomainsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'domains-grid-table') {
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'domains-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('data', $result['data']);
            $this->set('frameId', 'domains-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * View domain details with associated awards.
     *
     * @param string|null $id Domain ID
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When domain not found
     */
    public function view($id = null)
    {
        $domain = $this->Domains->get(
            $id,
            contain: [
                "Awards",
                "Awards.Levels" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Awards.Branches" => function ($q) {
                    return $q->select(["id", "name"]);
                },
            ]
        );
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($domain);
        $this->set(compact("domain"));
    }

    /**
     * Create a new domain.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $domain = $this->Domains->newEmptyEntity();
        if ($this->request->is("post")) {
            $domain = $this->Domains->patchEntity(
                $domain,
                $this->request->getData(),
            );
            if ($this->Domains->save($domain)) {
                $this->Flash->success(__("The Award Domain has been saved."));
                return $this->redirect(["action" => "view", $domain->id]);
            }
            $this->Flash->error(__("The Award Domain could not be saved. Please, try again."));
        }
        $this->set(compact("domain"));
    }

    /**
     * Edit an existing domain.
     *
     * @param string|null $id Domain ID
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When domain not found
     */
    public function edit($id = null)
    {
        $domain = $this->Domains->get($id, contain: []);
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($domain);
        if ($this->request->is(["patch", "post", "put"])) {
            $domain = $this->Domains->patchEntity(
                $domain,
                $this->request->getData(),
            );
            if ($this->Domains->save($domain)) {
                $this->Flash->success(__("The Award Domain Group has been saved."));
                return $this->redirect(["action" => "view", $domain->id]);
            }
            $this->Flash->error(__("The Award Domain could not be saved. Please, try again."));
        }
        $this->set(compact("domain"));
    }

    /**
     * Delete a domain. Blocked if domain has associated awards.
     *
     * @param string|null $id Domain ID
     * @return \Cake\Http\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When domain not found
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $domain = $this->Domains->get($id, contain: ["Awards"]);
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($domain->awards) {
            $this->Flash->error(__("The Award Domain could not be deleted because it has associated Awards."));
            return $this->redirect(["action" => "view", $domain->id]);
        }
        $this->Authorization->authorize($domain);
        $domain->name = "Deleted: " . $domain->name;
        if ($this->Domains->delete($domain)) {
            $this->Flash->success(__("The Award Domain has been deleted."));
        } else {
            $this->Flash->error(__("The Award Domain could not be deleted. Please, try again."));
            return $this->redirect(["action" => "view", $domain->id]);
        }

        return $this->redirect(["action" => "index"]);
    }
}
