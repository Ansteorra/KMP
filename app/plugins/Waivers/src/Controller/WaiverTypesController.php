<?php

declare(strict_types=1);

namespace Waivers\Controller;

use App\Controller\DataverseGridTrait;
use App\Services\CsvExportService;
use App\Services\DocumentService;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Waivers\KMP\GridColumns\WaiverTypesGridColumns;

/**
 * WaiverTypes Controller
 *
 * @property \Waivers\Model\Table\WaiverTypesTable $WaiverTypes
 */
class WaiverTypesController extends AppController
{
    use DataverseGridTrait;

    /**
     * Document service instance
     *
     * @var \App\Services\DocumentService
     */
    private DocumentService $DocumentService;

    /**
     * Initialize method
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
        $this->DocumentService = new DocumentService();
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Just render the page with the dv_grid element
        // The actual data will be loaded via gridData action
    }

    /**
     * Grid Data method - provides data for the Dataverse grid
     *
     * @param CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Build base query
        // Note: Documents association is commented out in the model, so we don't contain it
        $baseQuery = $this->WaiverTypes->find();

        // Get system views
        $systemViews = WaiverTypesGridColumns::getSystemViews();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Waivers.WaiverTypes.index.main',
            'gridColumnsClass' => WaiverTypesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'WaiverTypes',
            'defaultSort' => ['WaiverTypes.name' => 'asc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-waiver-types-active',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Post-process data to add computed fields
        $waiverTypes = $result['data'];
        foreach ($waiverTypes as $waiverType) {
            // Add has_template virtual field for grid display
            $waiverType->has_template = !empty($waiverType->template_path) || !empty($waiverType->document_id);
        }

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'waiver-types');
        }

        // Get row actions from grid columns
        $rowActions = WaiverTypesGridColumns::getRowActions();

        // Set view variables
        $this->set([
            'waiverTypes' => $waiverTypes,
            'rowActions' => $rowActions,
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => WaiverTypesGridColumns::getSearchableColumns(),
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

        // Override data for grid rendering
        $this->set('data', $waiverTypes);

        if ($turboFrame === 'waiver-types-grid-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', 'waiver-types-grid-table');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', 'waiver-types-grid');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * View method
     *
     * @param string|null $id Waiver Type id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        $waiverType = $this->WaiverTypes->get($id);
        if (!$waiverType) {
            throw new NotFoundException(__('Waiver type not found'));
        }
        $this->Authorization->authorize($waiverType);

        $this->set(compact('waiverType'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $waiverType = $this->WaiverTypes->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Remove file upload and URL fields from data before patching
            unset($data['template_file'], $data['template_url'], $data['template_source']);

            $waiverType = $this->WaiverTypes->patchEntity($waiverType, $data);
            $this->Authorization->authorize($waiverType);

            // Save first to get the ID for the document relationship
            if ($this->WaiverTypes->save($waiverType)) {
                // Now handle template file upload or external URL
                $templateResult = $this->_handleTemplateUpload($this->request->getData(), $waiverType);
                if ($templateResult !== null) {
                    $waiverType->document_id = $templateResult['document_id'];
                    $waiverType->template_path = $templateResult['template_path'];

                    // Update document entity_id if document was created
                    if (!empty($templateResult['document_id'])) {
                        $this->DocumentService->updateDocumentEntityId(
                            $templateResult['document_id'],
                            $waiverType->id
                        );
                    }

                    $this->WaiverTypes->save($waiverType);
                }

                $this->Flash->success(__('The waiver type has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(
                __('The waiver type could not be saved. Please, try again.')
            );
        }

        $this->set(compact('waiverType'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Waiver Type id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        // TODO: Add back 'Documents' contain when Documents table and document_id column are implemented
        $waiverType = $this->WaiverTypes->get($id);
        if (!$waiverType) {
            throw new NotFoundException(__('Waiver type not found'));
        }
        $this->Authorization->authorize($waiverType);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            $templateSource = $data['template_source'] ?? 'none';

            Log::debug('Edit - Template Source: ' . $templateSource);
            Log::debug('Edit - Has template_file: ' . (isset($data['template_file']) ? 'YES' : 'NO'));

            // Store the old document_id before making changes
            $oldDocumentId = $waiverType->document_id;
            Log::debug('Edit - Old document_id: ' . ($oldDocumentId ?? 'NULL'));

            // Remove file upload and URL fields from data before patching
            unset($data['template_file'], $data['template_url'], $data['template_source']);

            $waiverType = $this->WaiverTypes->patchEntity($waiverType, $data);

            if ($this->WaiverTypes->save($waiverType)) {
                // Handle template file upload or external URL
                $templateResult = $this->_handleTemplateUpload($this->request->getData(), $waiverType);

                Log::debug('Edit - Template Result: ' . ($templateResult ? json_encode($templateResult) : 'NULL'));

                if ($templateResult !== null) {
                    // Delete old document if we're replacing it
                    if (!empty($oldDocumentId) && $templateSource !== 'none') {
                        Log::debug('Edit - Deleting old document: ' . $oldDocumentId);
                        $deleteResult = $this->DocumentService->deleteDocument($oldDocumentId);
                        Log::debug('Edit - Delete result: ' . ($deleteResult->success ? 'SUCCESS' : 'FAILED'));
                    }

                    // Clear both fields, then set the new values
                    $waiverType->document_id = $templateResult['document_id'];
                    $waiverType->template_path = $templateResult['template_path'];

                    Log::debug('Edit - New document_id: ' . ($waiverType->document_id ?? 'NULL'));
                    Log::debug('Edit - New template_path: ' . ($waiverType->template_path ?? 'NULL'));

                    // Update document entity_id if document was created
                    if (!empty($templateResult['document_id'])) {
                        $updateResult = $this->DocumentService->updateDocumentEntityId(
                            $templateResult['document_id'],
                            $waiverType->id
                        );
                        Log::debug('Edit - Entity ID update result: ' . ($updateResult->success ? 'SUCCESS' : 'FAILED'));
                    }

                    $saveResult = $this->WaiverTypes->save($waiverType);
                    Log::debug('Edit - Final save result: ' . ($saveResult ? 'SUCCESS' : 'FAILED'));

                    // TODO: Add back Documents reload when Documents table and document_id column are implemented
                    // Reload the entity to get the fresh Document association
                    // if ($saveResult) {
                    //     $waiverType = $this->WaiverTypes->get($waiverType->id, contain: ['Documents']);
                    //     Log::debug('Edit - Reloaded entity, document_id: ' . ($waiverType->document_id ?? 'NULL'));
                    // }
                }

                $this->Flash->success(__('The waiver type has been saved.'));
                return $this->redirect(['action' => 'index']);
            }

            $this->Flash->error(
                __('The waiver type could not be saved. Please, try again.')
            );
        }

        $this->set(compact('waiverType'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Waiver Type id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $waiverType = $this->WaiverTypes->get($id);
        if (!$waiverType) {
            $this->Flash->error(__('Waiver type not found.'));

            return $this->redirect(['action' => 'index']);
        }

        $this->Authorization->authorize($waiverType);

        // Check if waiver type is referenced by any gathering activities
        $activityWaiverCount = 0;
        if ($this->WaiverTypes->hasAssociation('GatheringActivityWaivers')) {
            $activityWaiverCount = $this->WaiverTypes->GatheringActivityWaivers
                ->find()
                ->where(['waiver_type_id' => $id])
                ->count();
        }

        // Check if waiver type is referenced by any uploaded waivers
        $uploadedWaiverCount = 0;
        if ($this->WaiverTypes->hasAssociation('GatheringWaivers')) {
            $uploadedWaiverCount = $this->WaiverTypes->GatheringWaivers
                ->find()
                ->where(['waiver_type_id' => $id])
                ->count();
        }

        // Prevent deletion if referenced
        if ($activityWaiverCount > 0 || $uploadedWaiverCount > 0) {
            $messages = [];

            if ($activityWaiverCount > 0) {
                $messages[] = __(
                    '{0} gathering {1}',
                    $activityWaiverCount,
                    $activityWaiverCount === 1 ? 'activity' : 'activities'
                );
            }

            if ($uploadedWaiverCount > 0) {
                $messages[] = __(
                    '{0} uploaded {1}',
                    $uploadedWaiverCount,
                    $uploadedWaiverCount === 1 ? 'waiver' : 'waivers'
                );
            }

            $this->Flash->error(
                __(
                    'Cannot delete waiver type "{0}" because it is referenced by {1}. ' .
                        'Please remove these references first, or mark the waiver type as inactive instead.',
                    $waiverType->name,
                    implode(' and ', $messages)
                )
            );

            return $this->redirect(['action' => 'index']);
        }

        // No references - safe to delete
        if ($this->WaiverTypes->delete($waiverType)) {
            // Also delete the associated document if it exists
            if ($waiverType->document_id) {
                $result = $this->DocumentService->deleteDocument($waiverType->document_id);
                if (!$result->success) {
                    $this->log(
                        'Failed to delete waiver type template document: ' . $result->reason,
                        'warning'
                    );
                }
            }

            $this->Flash->success(__('The waiver type "{0}" has been deleted.', $waiverType->name));
        } else {
            $this->Flash->error(
                __('The waiver type could not be deleted. Please, try again.')
            );
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Toggle active status
     *
     * @param string|null $id Waiver Type id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function toggleActive(?string $id = null)
    {
        $this->request->allowMethod(['post']);

        $waiverType = $this->WaiverTypes->get($id);
        if (!$waiverType) {
            $this->Flash->error(__('Waiver type not found.'));

            return $this->redirect(['action' => 'index']);
        }

        $this->Authorization->authorize($waiverType, 'toggleActive');

        $waiverType->is_active = !$waiverType->is_active;

        if ($this->WaiverTypes->save($waiverType)) {
            $status = $waiverType->is_active ? 'activated' : 'deactivated';
            $this->Flash->success(__('The waiver type has been {0}.', $status));
        } else {
            $this->Flash->error(
                __('The waiver type status could not be changed. Please, try again.')
            );
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Download template file
     *
     * @param string|null $id Waiver Type id.
     * @return \Cake\Http\Response|null Sends file for download
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function downloadTemplate(?string $id = null)
    {
        // TODO: Add back 'Documents' contain when Documents table and document_id column are implemented
        $waiverType = $this->WaiverTypes->get($id);
        if (!$waiverType) {
            throw new NotFoundException(__('Waiver type not found'));
        }
        $this->Authorization->authorize($waiverType, 'downloadTemplate');

        Log::debug('Download - Waiver Type ID: ' . $id);
        Log::debug('Download - document_id: ' . ($waiverType->document_id ?? 'NULL'));
        Log::debug('Download - template_path: ' . ($waiverType->template_path ?? 'NULL'));
        if ($waiverType->document) {
            Log::debug('Download - Document file_path: ' . $waiverType->document->file_path);
            Log::debug('Download - Document original_filename: ' . $waiverType->document->original_filename);
        }

        // Check if external URL template exists
        if (!empty($waiverType->template_path)) {
            // If it's an external URL, redirect to it
            if (strpos($waiverType->template_path, 'http') === 0) {
                return $this->redirect($waiverType->template_path);
            }
        }

        // Check if document exists
        if (empty($waiverType->document_id) || !$waiverType->document) {
            $this->Flash->error(__('No template file available for this waiver type.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Use DocumentService to get download response
        $response = $this->DocumentService->getDocumentDownloadResponse(
            $waiverType->document,
            $waiverType->name . '_template.pdf'
        );

        if ($response === null) {
            $this->Flash->error(__('Template file not found.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        return $response;
    }

    /**
     * Handle template file upload or external URL
     *
     * @param array $data The form data
     * @param \Waivers\Model\Entity\WaiverType $waiverType The waiver type entity
     * @return array|null Array with keys 'document_id' or 'template_path', or null if not provided
     */
    protected function _handleTemplateUpload(array $data, $waiverType): ?array
    {
        $templateSource = $data['template_source'] ?? 'none';

        Log::debug('Template source: ' . $templateSource);

        // Handle external URL
        if ($templateSource === 'url' && !empty($data['template_url'])) {
            Log::debug('Using external URL: ' . $data['template_url']);
            return ['template_path' => $data['template_url'], 'document_id' => null];
        }

        // Handle file upload using DocumentService
        if ($templateSource === 'upload' && isset($data['template_file'])) {
            $file = $data['template_file'];

            Log::debug('File object type: ' . get_class($file));

            // Check if file is valid uploaded file object
            if (!is_object($file) || !method_exists($file, 'getSize')) {
                Log::error('Invalid file object');
                $this->Flash->error(__('Invalid file upload. Please try again.'));
                return null;
            }

            // Use DocumentService to create the document
            $result = $this->DocumentService->createDocument(
                $file,
                'Waivers.WaiverTypes',
                $waiverType->id ?? 0, // Will be updated after waiver type is saved
                $this->Authentication->getIdentity()->id,
                ['type' => 'waiver_template'],
                'waiver-templates',
                ['pdf']
            );

            if ($result->success) {
                $this->Flash->success(__('Template file uploaded successfully.'));
                return ['document_id' => $result->data, 'template_path' => null];
            } else {
                $this->Flash->error($result->reason);
                return null;
            }
        }

        return null;
    }
}