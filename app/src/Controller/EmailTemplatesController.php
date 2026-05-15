<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\EmailTemplatesGridColumns;
use App\Services\CsvExportService;
use App\Services\EmailTemplateRendererService;

/**
 * EmailTemplates Controller
 *
 * @property \App\Model\Table\EmailTemplatesTable $EmailTemplates
 */
class EmailTemplatesController extends AppController
{
    use DataverseGridTrait;

    /**
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->Authorization->authorizeModel('index', 'add', 'edit', 'delete', 'gridData', 'view');
    }

    /**
     * @return \Cake\Http\Response|null|void
     */
    public function index()
    {
        // Renders the Dataverse grid shell; gridData lazy-loads the rows.
    }

    /**
     * @param \App\Services\CsvExportService $csvExportService
     * @return \Cake\Http\Response|null|void
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $result = $this->processDataverseGrid([
            'gridKey' => 'EmailTemplates.index.main',
            'gridColumnsClass' => EmailTemplatesGridColumns::class,
            'baseQuery' => $this->EmailTemplates->find(),
            'tableName' => 'EmailTemplates',
            'defaultSort' => ['EmailTemplates.slug' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'email-templates');
        }

        $this->set([
            'emailTemplates' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => EmailTemplatesGridColumns::getSearchableColumns(),
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
        if ($turboFrame === 'email-templates-grid-table') {
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'email-templates-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');

            return;
        }

        $this->set('data', $result['data']);
        $this->set('frameId', 'email-templates-grid');
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('../element/dv_grid_content');
    }

    /**
     * @param string|null $id
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException
     */
    public function view($id = null)
    {
        $emailTemplate = $this->EmailTemplates->get($id);
        $this->Authorization->authorize($emailTemplate, 'view');

        $rendererService = new EmailTemplateRendererService();
        $preview = $rendererService->preview($emailTemplate);

        $pluginViewCells = [];
        $recordId = $id;
        $recordModel = 'EmailTemplates';

        $this->set(compact('emailTemplate', 'preview', 'pluginViewCells', 'recordId', 'recordModel'));
    }

    /**
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $emailTemplate = $this->EmailTemplates->newEmptyEntity();
        $this->Authorization->authorize($emailTemplate, 'create');

        if ($this->request->is('post')) {
            $emailTemplate = $this->EmailTemplates->patchEntity($emailTemplate, $this->request->getData());

            if ($this->EmailTemplates->save($emailTemplate)) {
                $this->Flash->success(__('The email template has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The email template could not be saved. Please, try again.'));
        }

        $this->set(compact('emailTemplate'));
    }

    /**
     * @param string|null $id
     * @return \Cake\Http\Response|null|void
     * @throws \Cake\Datasource\Exception\RecordNotFoundException
     */
    public function edit($id = null)
    {
        $emailTemplate = $this->EmailTemplates->get($id);
        $this->Authorization->authorize($emailTemplate, 'update');

        if ($this->request->is(['patch', 'post', 'put'])) {
            $emailTemplate = $this->EmailTemplates->patchEntity($emailTemplate, $this->request->getData());

            if ($this->EmailTemplates->save($emailTemplate)) {
                $this->Flash->success(__('The email template has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The email template could not be saved. Please, try again.'));
        }

        $this->set(compact('emailTemplate'));
    }

    /**
     * @param string|null $id
     * @return \Cake\Http\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $emailTemplate = $this->EmailTemplates->get($id);
        $this->Authorization->authorize($emailTemplate, 'delete');

        if ($this->EmailTemplates->delete($emailTemplate)) {
            $this->Flash->success(__('The email template has been deleted.'));
        } else {
            $this->Flash->error(__('The email template could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * @return void
     */
    public function options(): void
    {
        $this->request->allowMethod(['get']);
        $this->Authorization->skipAuthorization();

        $templates = $this->EmailTemplates->find()
            ->where(['is_active' => true])
            ->select(['id', 'slug', 'name', 'subject_template', 'available_vars', 'variables_schema'])
            ->orderBy(['name' => 'ASC', 'slug' => 'ASC'])
            ->all();

        $options = [];
        foreach ($templates as $template) {
            $parsedPlaceholders = [];
            if (!empty($template->subject_template)) {
                preg_match_all('/\{\{(\w+)\}\}/', $template->subject_template, $matches);
                $parsedPlaceholders = array_values(array_unique($matches[1] ?? []));
            }

            $options[] = [
                'value' => $template->id,
                'label' => $template->display_name,
                'slug' => $template->slug,
                'isWorkflowNative' => true,
                'availableVars' => $template->available_vars,
                'variablesSchema' => $template->variables_schema,
                'parsedPlaceholders' => $parsedPlaceholders,
                'subjectPreview' => $template->subject_template,
            ];
        }

        $this->set('options', $options);
        $this->viewBuilder()->setClassName('Json');
        $this->viewBuilder()->setOption('serialize', ['options']);
    }

    /**
     * @param string|null $id
     * @return \Cake\Http\Response|null|void
     */
    public function preview($id = null)
    {
        $emailTemplate = $this->EmailTemplates->get($id);
        $this->Authorization->authorize($emailTemplate, 'preview');

        $rendererService = new EmailTemplateRendererService();
        $sampleVars = [];
        if ($this->request->is(['post', 'put'])) {
            $sampleVars = $this->request->getData('sample_vars', []);
        }

        $preview = $rendererService->preview($emailTemplate, $sampleVars);

        if ($this->request->is('json')) {
            $this->set([
                'preview' => $preview,
                '_serialize' => ['preview'],
            ]);

            return;
        }

        $this->set(compact('emailTemplate', 'preview', 'sampleVars'));
    }
}
