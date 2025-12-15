<?php

declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;

/**
 * Award Levels Controller - CRUD for award precedence/rank levels.
 *
 * Levels define progression_order for hierarchical award ranking.
 *
 * @property \Awards\Model\Table\LevelsTable $Levels
 */
class LevelsController extends AppController
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
     * List award levels ordered by progression_order.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Provide grid data for Levels listing via Dataverse grid.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(\App\Services\CsvExportService $csvExportService)
    {
        // Build base query
        $baseQuery = $this->Levels->find();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Levels.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\LevelsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Levels',
            'defaultSort' => ['Levels.progression_order' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'award-levels');
        }

        // Set view variables
        $this->set([
            'levels' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\LevelsGridColumns::getSearchableColumns(),
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

        // Use main app's element templates (not plugin templates)
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'levels-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'levels-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'levels-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display level details with associated awards.
     *
     * @param string|null $id Level ID
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When level not found
     */
    public function view($id = null)
    {
        $level = $this->Levels->get(
            $id,
            contain: [
                "Awards",
                "Awards.Domains" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Awards.Branches" => function ($q) {
                    return $q->select(["id", "name"]);
                },
            ]
        );
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($level);
        $this->set(compact("level"));
    }

    /**
     * Create a new award level.
     *
     * @return \Cake\Http\Response|null|void Redirects on success, renders form on GET/failure
     */
    public function add()
    {
        $level = $this->Levels->newEmptyEntity();
        if ($this->request->is("post")) {
            $level = $this->Levels->patchEntity(
                $level,
                $this->request->getData(),
            );
            if ($this->Levels->save($level)) {
                $this->Flash->success(
                    __("The Award Level has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $level->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Level could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("level"));
    }

    /**
     * Modify an existing award level.
     *
     * @param string|null $id Level ID
     * @return \Cake\Http\Response|null|void Redirects on success, renders form on GET/failure
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When level not found
     */
    public function edit($id = null)
    {
        $level = $this->Levels->get($id, contain: []);
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($level);
        if ($this->request->is(["patch", "post", "put"])) {
            $level = $this->Levels->patchEntity(
                $level,
                $this->request->getData(),
            );
            if ($this->Levels->save($level)) {
                $this->Flash->success(
                    __("The Award Level Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $level->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Level could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("level"));
    }

    /**
     * Delete an award level.
     *
     * Prevents deletion if level has associated awards.
     *
     * @param string|null $id Level ID
     * @return \Cake\Http\Response|null Redirects to index on success, view on failure
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When level not found
     * @throws \Cake\Http\Exception\MethodNotAllowedException When invalid HTTP method used
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $level = $this->Levels->get(
            $id,
            contain: ["Awards"],
        );
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($level->awards) {
            $this->Flash->error(
                __("The Award Level could not be deleted because it has associated Awards."),
            );
            return $this->redirect(["action" => "view", $level->id]);
        }
        $this->Authorization->authorize($level);
        $level->name = "Deleted: " . $level->name;
        if ($this->Levels->delete($level)) {
            $this->Flash->success(
                __("The Award Level has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Award Level could not be deleted. Please, try again.",
                ),
            );

            return $this->redirect(["action" => "view", $level->id]);
        }

        return $this->redirect(["action" => "index"]);
    }
}
