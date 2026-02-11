<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\CsvExportService;
use App\Services\MailerDiscoveryService;
use App\Services\EmailTemplateRendererService;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;

/**
 * EmailTemplates Controller
 *
 * @property \App\Model\Table\EmailTemplatesTable $EmailTemplates
 */
class EmailTemplatesController extends AppController
{
    use DataverseGridTrait;

    /**
     * Initialize method
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Authorization handled by AppController for basic CRUD
        $this->Authorization->authorizeModel('index', 'add', 'edit', 'delete', 'gridData');
    }

    /**
     * Before filter callback
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        // Add authorization checks for custom actions
        $action = $this->request->getParam('action');

        if (in_array($action, ['discover', 'sync', 'preview'])) {
            // These methods need manual authorization
            // Will be checked in the action methods themselves
        }
    }

    /**
     * Index method - Display Dataverse grid for email templates
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Grid Data method - Provides Dataverse grid data for email templates
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Get system views from GridColumns

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'EmailTemplates.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\EmailTemplatesGridColumns::class,
            'baseQuery' => $this->EmailTemplates->find(),
            'tableName' => 'EmailTemplates',
            'defaultSort' => ['EmailTemplates.mailer_class' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'email-templates');
        }

        // Set view variables
        $this->set([
            'emailTemplates' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\EmailTemplatesGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'email-templates-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'email-templates-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'email-templates-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * View method
     *
     * @param string|null $id Email Template id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $emailTemplate = $this->EmailTemplates->get($id);
        $this->Authorization->authorize($emailTemplate, 'view');

        // Generate preview with sample data
        $rendererService = new EmailTemplateRendererService();
        $preview = $rendererService->preview($emailTemplate);

        // Set variables required by view_record layout
        $pluginViewCells = [];
        $recordId = $id;
        $recordModel = 'EmailTemplates';

        $this->set(compact('emailTemplate', 'preview', 'pluginViewCells', 'recordId', 'recordModel'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
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

        // Get discovered mailers for selection
        $discoveryService = new MailerDiscoveryService();
        $allMailers = $discoveryService->discoverAllMailers();

        // Pre-populate if mailer and action are specified in query
        if ($this->request->getQuery('mailer_class') && $this->request->getQuery('action_method')) {
            $mailerClass = $this->request->getQuery('mailer_class');
            $actionMethod = $this->request->getQuery('action_method');

            $methodInfo = $discoveryService->getMailerMethodInfo($mailerClass, $actionMethod);

            if ($methodInfo) {
                $emailTemplate->mailer_class = $mailerClass;
                $emailTemplate->action_method = $actionMethod;
                $emailTemplate->available_vars = $methodInfo['availableVars'];

                if ($methodInfo['defaultSubject']) {
                    $emailTemplate->subject_template = $methodInfo['defaultSubject'];
                }

                // Try to load existing file-based template content
                $this->prefillFromFileTemplates($emailTemplate);
            }
        }

        $this->set(compact('emailTemplate', 'allMailers'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Email Template id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
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

        // Get discovered mailers for reference
        $discoveryService = new MailerDiscoveryService();
        $allMailers = $discoveryService->discoverAllMailers();

        // Get current method info for reference
        $methodInfo = $discoveryService->getMailerMethodInfo(
            $emailTemplate->mailer_class,
            $emailTemplate->action_method
        );

        $this->set(compact('emailTemplate', 'allMailers', 'methodInfo'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Email Template id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
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
     * Sync method - discover all mailer methods and create templates for missing ones
     *
     * @return \Cake\Http\Response|null Redirects to index.
     */
    public function sync()
    {
        $this->request->allowMethod(['post']);

        // Authorize sync action on the table
        $this->Authorization->authorize($this->EmailTemplates, 'sync');

        $discoveryService = new MailerDiscoveryService();
        $allMailers = $discoveryService->discoverAllMailers();

        $created = 0;
        $skipped = 0;

        foreach ($allMailers as $mailer) {
            foreach ($mailer['methods'] as $method) {
                // Check if template already exists
                $existing = $this->EmailTemplates->findForMailer(
                    $mailer['class'],
                    $method['name']
                );

                if ($existing !== null) {
                    $skipped++;
                    continue;
                }

                // Create new template
                $emailTemplate = $this->EmailTemplates->newEntity([
                    'mailer_class' => $mailer['class'],
                    'action_method' => $method['name'],
                    'subject_template' => $method['defaultSubject'] ?? 'Email from ' . $mailer['shortName'],
                    'available_vars' => $method['availableVars'],
                    'is_active' => true, // Active by default when synced from files
                ]);

                // Try to load content from file-based templates
                $this->prefillFromFileTemplates($emailTemplate);

                // Convert subject variables from $var to {{var}}
                if (!empty($emailTemplate->subject_template)) {
                    $emailTemplate->subject_template = $this->convertSubjectVariables($emailTemplate->subject_template);
                }

                if ($this->EmailTemplates->save($emailTemplate)) {
                    $created++;
                }
            }
        }

        $this->Flash->success(__(
            'Synchronization complete. Created {0} new templates, skipped {1} existing templates.',
            $created,
            $skipped
        ));

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Preview method - generate a preview of a template
     *
     * @param string|null $id Email Template id.
     * @return \Cake\Http\Response|null|void
     */
    public function preview($id = null)
    {
        $emailTemplate = $this->EmailTemplates->get($id);
        $this->Authorization->authorize($emailTemplate, 'preview');

        $rendererService = new EmailTemplateRendererService();

        // Use sample vars from request or generate defaults
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
        } else {
            $this->set(compact('emailTemplate', 'preview', 'sampleVars'));
        }
    }

    /**
     * Discover method - list all discoverable mailer methods
     *
     * @return void
     */
    public function discover()
    {
        // Authorize discover action on the table
        $this->Authorization->authorize($this->EmailTemplates, 'discover');

        $discoveryService = new MailerDiscoveryService();
        $allMailers = $discoveryService->discoverAllMailers();

        // Get existing templates for comparison
        // Create a lookup map using mailer_class::action_method as the key
        $templates = $this->EmailTemplates->find()->all();
        $existingTemplates = [];
        foreach ($templates as $template) {
            $key = $template->mailer_class . '::' . $template->action_method;
            $existingTemplates[$key] = $template;
        }

        $this->set(compact('allMailers', 'existingTemplates'));
    }

    /**
     * Prefill template from existing file-based templates
     *
     * @param \App\Model\Entity\EmailTemplate $emailTemplate
     * @return void
     */
    protected function prefillFromFileTemplates($emailTemplate): void
    {
        // Determine template paths based on mailer class
        $mailerClass = $emailTemplate->mailer_class;
        $actionMethod = $emailTemplate->action_method;

        // Convert action method from camelCase to snake_case for file names
        $templateName = \Cake\Utility\Inflector::underscore($actionMethod);

        // Convert class name to path
        if (str_starts_with($mailerClass, 'App\\Mailer\\')) {
            $templatesPath = ROOT . DS . 'templates' . DS . 'email';
        } elseif (preg_match('/^([^\\\\]+)\\\\Mailer\\\\/', $mailerClass, $matches)) {
            $pluginName = $matches[1];
            $templatesPath = ROOT . DS . 'plugins' . DS . $pluginName . DS . 'templates' . DS . 'email';
        } else {
            return;
        }

        // Try to read HTML template
        $htmlPath = $templatesPath . DS . 'html' . DS . $templateName . '.php';
        if (file_exists($htmlPath)) {
            $htmlContent = file_get_contents($htmlPath);
            // Convert CakePHP template syntax to our variable syntax
            $htmlContent = $this->convertTemplateVariables($htmlContent);
            $emailTemplate->html_template = $htmlContent;
        }

        // Try to read text template
        $textPath = $templatesPath . DS . 'text' . DS . $templateName . '.php';
        if (file_exists($textPath)) {
            $textContent = file_get_contents($textPath);
            // Convert CakePHP template syntax to our variable syntax
            $textContent = $this->convertTemplateVariables($textContent);
            $emailTemplate->text_template = $textContent;
        }

        // If we only have a text template, copy it to HTML as well
        // This ensures emails can be sent as HTML (wrapped in basic formatting)
        if (empty($emailTemplate->html_template) && !empty($emailTemplate->text_template)) {
            $emailTemplate->html_template = $emailTemplate->text_template;
        }
    }

    /**
     * Convert CakePHP template variable syntax to our variable syntax
     * Converts from PHP echo tags to double curly braces and
     * PHP conditionals to {{#if}} blocks.
     *
     * @param string $content Template content
     * @return string Converted content
     */
    protected function convertTemplateVariables(string $content): string
    {
        // Replace PHP short echo tags with variable name to double curly braces
        // Pattern matches: opening tag, optional whitespace, dollar sign, variable name, optional whitespace, closing tag
        $content = preg_replace('/\<\?=\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\?\>/', '{{$1}}', $content);

        // Also handle cases with h() helper for escaping
        $content = preg_replace('/\<\?=\s*h\(\s*\$([a-zA-Z_][a-zA-Z0-9_]*)\s*\)\s*\?\>/', '{{$1}}', $content);

        // Convert PHP conditionals to {{#if}} syntax
        // Match: PHP if blocks → {{#if ...}}
        $content = preg_replace_callback(
            '/<\?php\s+if\s*\((.+?)\)\s*:\s*\?>/s',
            function ($matches) {
                // Strip $ prefix from variable names in the condition
                $condition = preg_replace('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', '$1', $matches[1]);
                return '{{#if ' . trim($condition) . '}}';
            },
            $content,
        );

        // Convert PHP endif blocks → {{/if}}
        $content = preg_replace('/<\?php\s+endif;\s*\?>/', '{{/if}}', $content);

        // Convert PHP else blocks → {{else}} (or strip if DSL doesn't support else)
        if (preg_match('/<\?php\s+else\s*:\s*\?>/', $content)) {
            Log::warning('Email template conversion encountered <?php else : ?> block — converting to {{else}}');
            $content = preg_replace('/<\?php\s+else\s*:\s*\?>/', '{{else}}', $content);
        }

        // Strip PHP elseif blocks and warn — not supported in the safe DSL
        if (preg_match('/<\?php\s+elseif\s*\((.+?)\)\s*:\s*\?>/', $content)) {
            Log::warning('Email template conversion encountered <?php elseif (...) : ?> block — stripping (not supported in safe DSL)');
            $content = preg_replace('/<\?php\s+elseif\s*\((.+?)\)\s*:\s*\?>/', '', $content);
        }

        return $content;
    }

    /**
     * Convert subject string variables to our variable syntax
     * Converts $variableName to {{variableName}} in subject strings
     *
     * @param string $subject Subject string
     * @return string Converted subject
     */
    protected function convertSubjectVariables(string $subject): string
    {
        // Replace $variableName with {{variableName}}
        // Pattern matches: dollar sign followed by variable name (letters, numbers, underscores)
        $subject = preg_replace('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', '{{$1}}', $subject);

        return $subject;
    }
}
