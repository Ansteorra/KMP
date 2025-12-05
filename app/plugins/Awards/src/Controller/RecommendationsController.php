<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;
use Awards\Model\Entity\Recommendation;
use Awards\KMP\GridColumns\RecommendationsGridColumns;
use Cake\I18n\DateTime;
use Cake\Routing\Router;
use App\KMP\StaticHelpers;
use App\Controller\DataverseGridTrait;
use Authorization\Exception\ForbiddenException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;
use PhpParser\Node\Stmt\TryCatch;
use App\Services\CsvExportService;
use Cake\Error\Debugger;


/**
 * Recommendations Controller
 * 
 * Manages the complete award recommendation lifecycle from submission through final
 * disposition. Implements state machine-based workflow with table and kanban views.
 * Supports authenticated and public submission workflows.
 * 
 * Uses DataverseGridTrait for table-based data display.
 * 
 * @property \Awards\Model\Table\RecommendationsTable $Recommendations
 * @package Awards\Controller
 */
class RecommendationsController extends AppController
{
    use DataverseGridTrait;

    /**
     * Configure authentication - allows unauthenticated submitRecommendation.
     * 
     * @param \Cake\Event\EventInterface $event The beforeFilter event instance
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): ?\Cake\Http\Response
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            'submitRecommendation'
        ]);

        return null;
    }

    /**
     * Recommendation system landing page.
     * 
     * Primary entry point rendering the Dataverse grid interface.
     * Grid data is loaded lazily via gridData() action.
     * 
     * @return \Cake\Http\Response|null|void
     */
    public function index(): ?\Cake\Http\Response
    {
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $user = $this->request->getAttribute('identity');
        $this->Authorization->authorize($emptyRecommendation, 'index');

        // The new index uses dv_grid with lazy loading - no need for complex config
        // Just render the page, the grid will load data via gridData() action
        return null;
    }

    /**
     * Grid Data method - provides data for the Dataverse grid
     *
     * This method handles the AJAX requests from the dv_grid element, providing
     * recommendation data with proper filtering, sorting, pagination, and authorization.
     * It supports status-based system views and permission-based state filtering.
     *
     * @param CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'index');

        // Check if user can view hidden states
        $user = $this->request->getAttribute('identity');
        $canViewHidden = $user->checkCan('ViewHidden', $emptyRecommendation);

        // Build base query with containments
        $baseQuery = $this->Recommendations->find()
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation', 'branch_id']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedGathering' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Apply hidden state filter if user doesn't have permission
        if (!$canViewHidden) {
            $hiddenStates = RecommendationsGridColumns::getHiddenStates();
            if (!empty($hiddenStates)) {
                $baseQuery->where(['Recommendations.state NOT IN' => $hiddenStates]);
            }
        }

        // Apply authorization scope
        $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');

        // Get system views with proper state filter options
        $systemViews = RecommendationsGridColumns::getSystemViews();

        // Update state column filter options based on permissions
        $stateFilterOptions = RecommendationsGridColumns::getStateFilterOptions($canViewHidden);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Recommendations.index.main',
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.created' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-all',
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Post-process data to add computed fields for display
        $recommendations = $result['data'];
        foreach ($recommendations as $recommendation) {
            // Build OP links HTML
            $recommendation->op_links = $this->buildOpLinksHtml($recommendation);

            // Build gatherings HTML (member attendance)
            $recommendation->gatherings = $this->buildGatheringsHtml($recommendation);

            // Build notes HTML
            $recommendation->notes = $this->buildNotesHtml($recommendation);

            // Build reason HTML with truncation
            $recommendation->reason = $this->buildReasonHtml($recommendation);
        }

        // Handle CSV export using trait's unified method with data mode
        if (!empty($result['isCsvExport'])) {
            // Fetch all data from query (not paginated) and process computed fields
            $exportData = $this->prepareRecommendationsForExport($result['query']);
            return $this->handleCsvExport($result, $csvExportService, 'recommendations', 'Awards.Recommendations', $exportData);
        }

        // Get row actions from grid columns
        $rowActions = RecommendationsGridColumns::getRowActions();

        // Merge dynamic state filter options
        $columns = $result['columnsMetadata'];
        if (isset($columns['state'])) {
            $columns['state']['filterOptions'] = $stateFilterOptions;
        }

        // Set view variables
        $this->set([
            'recommendations' => $recommendations,
            'data' => $recommendations,
            'rowActions' => $rowActions,
            'gridState' => $result['gridState'],
            'columns' => $columns,
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => RecommendationsGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'canViewHidden' => $canViewHidden,
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'recommendations-grid-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', 'recommendations-grid-table');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', 'recommendations-grid');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Build OP (Order of Precedence) links HTML for a recommendation
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity
     * @return string HTML string with OP links
     */
    protected function buildOpLinksHtml($recommendation): string
    {
        if (!$recommendation->member) {
            return '';
        }

        $links = [];
        $externalLinks = $recommendation->member->publicLinks();
        if ($externalLinks) {
            foreach ($externalLinks as $name => $link) {
                $links[] = '<a href="' . h($link) . '" target="_blank" title="' . h($name) . '">' .
                    '<i class="bi bi-box-arrow-up-right"></i></a>';
            }
        }

        return implode(' ', $links);
    }

    /**
     * Build gatherings HTML showing member attendance as comma-separated list
     *
     * Shows up to 3 gatherings, with a "more" link to expand if there are more.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity
     * @return string HTML string with gathering information
     */
    protected function buildGatheringsHtml($recommendation): string
    {
        if (empty($recommendation->gatherings)) {
            return '';
        }

        $gatherings = $recommendation->gatherings;
        $total = count($gatherings);
        $maxVisible = 3;

        // Build list of gathering names
        $names = [];
        foreach ($gatherings as $gathering) {
            $names[] = h($gathering->name);
        }

        if ($total <= $maxVisible) {
            // Show all as comma-separated list
            return implode(', ', $names);
        }

        // Show first 3 with expandable "more"
        $visibleNames = array_slice($names, 0, $maxVisible);
        $hiddenNames = array_slice($names, $maxVisible);
        $hiddenCount = count($hiddenNames);

        $uniqueId = 'gatherings-' . $recommendation->id;
        $html = '<span class="gatherings-list">';
        $html .= implode(', ', $visibleNames);
        $html .= '<span id="' . $uniqueId . '-hidden" style="display:none;">, ' . implode(', ', $hiddenNames) . '</span>';
        $html .= ' <a href="#" class="text-primary small" onclick="';
        $html .= "var el=document.getElementById('" . $uniqueId . "-hidden');";
        $html .= "var link=this;";
        $html .= "if(el.style.display==='none'){el.style.display='inline';link.textContent='less';}";
        $html .= "else{el.style.display='none';link.textContent='+" . $hiddenCount . " more';}";
        $html .= 'return false;">+' . $hiddenCount . ' more</a>';
        $html .= '</span>';

        return $html;
    }

    /**
     * Build notes HTML for recommendation with popover showing all notes
     *
     * Displays note count with a popover button that shows all notes when clicked.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity
     * @return string HTML string with notes count and popover
     */
    protected function buildNotesHtml($recommendation): string
    {
        if (empty($recommendation->notes)) {
            return '';
        }

        $count = count($recommendation->notes);
        if ($count === 0) {
            return '';
        }

        // Build popover title with note count
        $title = $count === 1 ? '1 Note' : $count . ' Notes';

        // Build popover content with header containing title and close button
        $popoverContent = '<div class="popover-header-bar d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">';
        $popoverContent .= '<strong>' . h($title) . '</strong>';
        $popoverContent .= '<button type="button" class="btn-close popover-close-btn" aria-label="Close"></button>';
        $popoverContent .= '</div>';

        foreach ($recommendation->notes as $note) {
            $authorName = $note->author ? $note->author->sca_name : __('Unknown');
            $noteDate = $note->created ? $note->created->format('M j, Y g:i A') : '';
            $popoverContent .= '<div class="border-bottom pb-2 mb-2">';
            $popoverContent .= '<div class="fw-bold">' . htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') . '</div>';
            $popoverContent .= '<div class="text-muted small">' . htmlspecialchars($noteDate, ENT_QUOTES, 'UTF-8') . '</div>';
            $popoverContent .= '<div>' . nl2br(htmlspecialchars($note->body ?? '', ENT_QUOTES, 'UTF-8')) . '</div>';
            $popoverContent .= '</div>';
        }
        // Remove trailing border from last note
        $popoverContent = preg_replace('/border-bottom pb-2 mb-2([^"]*)">\s*$/', 'pb-0 mb-0$1">', $popoverContent);

        $escapedContent = htmlspecialchars($popoverContent, ENT_QUOTES, 'UTF-8');

        $html = '<button type="button" class="btn btn-link text-primary p-0" ';
        $html .= 'style="font-size: inherit;" ';
        $html .= 'data-controller="popover" ';
        $html .= 'data-bs-toggle="popover" ';
        $html .= 'data-bs-trigger="click" ';
        $html .= 'data-bs-placement="auto" ';
        $html .= 'data-bs-html="true" ';
        $html .= 'data-bs-custom-class="notes-popover" ';
        $html .= 'data-bs-content="' . $escapedContent . '" ';
        $html .= 'data-turbo="false">';
        $html .= '<span class="badge bg-secondary">' . $count . '</span>';
        $html .= '</button>';

        return $html;
    }

    /**
     * Build reason HTML with popover for full text if it exceeds 50 characters
     *
     * Uses Bootstrap popover to show full text without expanding the column.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity
     * @return string HTML string with reason, truncated with popover if needed
     */
    protected function buildReasonHtml($recommendation): string
    {
        $reason = $recommendation->reason ?? '';
        if ($reason === '') {
            return '';
        }

        $maxLength = 50;
        if (mb_strlen($reason) <= $maxLength) {
            return h($reason);
        }

        // Truncate and add popover "more" button
        // Using a button element to prevent Turbo from intercepting clicks
        $truncated = mb_substr($reason, 0, $maxLength) . '...';

        // Build popover content with header containing title and close button
        $popoverContent = '<div class="popover-header-bar d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">';
        $popoverContent .= '<strong>' . __('Full Reason') . '</strong>';
        $popoverContent .= '<button type="button" class="btn-close popover-close-btn" aria-label="Close"></button>';
        $popoverContent .= '</div>';
        $popoverContent .= '<div>' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</div>';
        $escapedContent = htmlspecialchars($popoverContent, ENT_QUOTES, 'UTF-8');

        $html = '<span class="reason-text">';
        $html .= h($truncated);
        $html .= ' <button type="button" class="btn btn-link text-primary small p-0 ms-1 align-baseline" ';
        $html .= 'style="font-size: inherit; vertical-align: baseline;" ';
        $html .= 'data-controller="popover" ';
        $html .= 'data-bs-toggle="popover" ';
        $html .= 'data-bs-trigger="click" ';
        $html .= 'data-bs-placement="auto" ';
        $html .= 'data-bs-html="true" ';
        $html .= 'data-bs-custom-class="reason-popover" ';
        $html .= 'data-bs-content="' . $escapedContent . '" ';
        $html .= 'data-turbo="false" ';
        $html .= 'tabindex="0">more</button>';
        $html .= '</span>';

        return $html;
    }

    /**
     * Prepare recommendations for CSV export with computed fields
     *
     * Fetches all data from the query (not paginated) and populates computed fields
     * that can't be calculated via SQL (virtual properties, formatted strings, etc.)
     *
     * @param \Cake\ORM\Query $query The filtered query from processDataverseGrid
     * @return \Cake\ORM\ResultSet Recommendations with computed fields populated
     */
    protected function prepareRecommendationsForExport($query): iterable
    {
        // Execute query to get all matching records (not paginated)
        $recommendations = $query->all();

        // Process each recommendation to populate computed fields for export
        foreach ($recommendations as $recommendation) {
            // Note: For export, we don't need HTML formatting.
            // The trait will use renderField paths to access nested properties like member.name_for_herald
            // We just need to ensure the associations are loaded, which they should be from the query
        }

        return $recommendations;
    }

    /**
     * Grid Data for "Submitted By Member" context
     *
     * Provides recommendation data for recommendations submitted by a specific member.
     * Used in the member profile's "Submitted Award Recs" tab.
     *
     * @param CsvExportService $csvExportService Injected CSV export service
     * @param int|null $memberId The member ID whose submissions to show (-1 for current user)
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function memberSubmittedRecsGridData(CsvExportService $csvExportService, ?int $memberId = null)
    {
        // Resolve member ID
        if ($memberId === null || $memberId === -1) {
            $memberId = $this->request->getAttribute('identity')->id;
        }

        $user = $this->request->getAttribute('identity');
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $emptyRecommendation->requester_id = $memberId;

        $this->Authorization->authorize($emptyRecommendation, 'ViewSubmittedByMember');

        // Build base query filtered by requester
        $baseQuery = $this->Recommendations->find()
            ->where(['Recommendations.requester_id' => $memberId])
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
            ]);

        // Get system views for this context
        $systemViews = RecommendationsGridColumns::getSubmittedByMemberViews();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Recommendations.memberSubmitted.' . $memberId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.created' => 'desc'],
            'defaultPageSize' => 15,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-submitted-by',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => false,
            'canExportCsv' => false,
            'enableColumnPicker' => false
        ]);

        // Post-process data
        $recommendations = $result['data'];
        foreach ($recommendations as $recommendation) {
            $recommendation->gatherings = $this->buildGatheringsHtml($recommendation);
            $recommendation->reason = $this->buildReasonHtml($recommendation);
        }

        // Handle CSV export using trait's unified method with data mode
        if (!empty($result['isCsvExport'])) {
            $exportData = $this->prepareRecommendationsForExport($result['query']);
            return $this->handleCsvExport($result, $csvExportService, 'recommendations-submitted', 'Awards.Recommendations', $exportData);
        }

        // Set view variables
        $this->set([
            'recommendations' => $recommendations,
            'data' => $recommendations,
            'rowActions' => [],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => RecommendationsGridColumns::getSearchableColumns(),
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

        // Render grid content
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $frameId = 'member-submitted-recs-grid-' . $memberId;

        if ($turboFrame === $frameId . '-table') {
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('frameId', $frameId);
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Grid Data for "Recs For Member" context
     *
     * Provides recommendation data for recommendations about a specific member.
     * Used in the member profile's "Recs For Member" tab.
     *
     * @param CsvExportService $csvExportService Injected CSV export service
     * @param int|null $memberId The member ID whose received recommendations to show
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function recsForMemberGridData(CsvExportService $csvExportService, ?int $memberId = null)
    {
        // Resolve member ID
        if ($memberId === null || $memberId === -1) {
            $memberId = $this->request->getAttribute('identity')->id;
        }

        $user = $this->request->getAttribute('identity');
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();



        $this->Authorization->authorize($emptyRecommendation, 'ViewSubmittedForMember');

        // Build base query filtered by member (subject of recommendation)
        $baseQuery = $this->Recommendations->find()
            ->where(['Recommendations.member_id' => $memberId])
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'AssignedGathering' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ]);

        // Apply authorization scope
        $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');

        // Get system views for this context
        $systemViews = RecommendationsGridColumns::getRecsForMemberViews();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Recommendations.forMember.' . $memberId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.created' => 'desc'],
            'defaultPageSize' => 15,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-for-member',
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => false,
            'canExportCsv' => false,
        ]);

        // Post-process data
        $recommendations = $result['data'];
        foreach ($recommendations as $recommendation) {
            $recommendation->gatherings = $this->buildGatheringsHtml($recommendation);
            $recommendation->reason = $this->buildReasonHtml($recommendation);
        }

        // Set view variables
        $this->set([
            'recommendations' => $recommendations,
            'data' => $recommendations,
            'rowActions' => [],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => RecommendationsGridColumns::getSearchableColumns(),
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

        // Render grid content
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $frameId = 'recs-for-member-grid-' . $memberId;

        if ($turboFrame === $frameId . '-table') {
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('frameId', $frameId);
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Grid Data for "Gathering Awards" context
     *
     * Provides recommendation data for recommendations scheduled at a specific gathering.
     * Used in the gathering detail's "Awards" tab.
     *
     * @param CsvExportService $csvExportService Injected CSV export service
     * @param int|null $gatheringId The gathering ID whose scheduled recommendations to show
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gatheringAwardsGridData(CsvExportService $csvExportService, ?int $gatheringId = null)
    {
        if ($gatheringId === null) {
            throw new \Cake\Http\Exception\BadRequestException(__('Gathering ID is required.'));
        }

        $user = $this->request->getAttribute('identity');

        // Load gathering for permission check
        $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
        $gathering = $gatheringsTable->get($gatheringId);

        $this->Authorization->authorize($gathering, 'ViewGatheringRecommendations');

        // Build base query filtered by gathering
        $baseQuery = $this->Recommendations->find()
            ->where(['Recommendations.gathering_id' => $gatheringId])
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation', 'branch_id']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedGathering' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Get system views for this context
        $systemViews = RecommendationsGridColumns::getGatheringAwardsViews();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Recommendations.gathering.' . $gatheringId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Recommendations',
            'defaultSort' => ['Recommendations.member_sca_name' => 'asc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-gathering',
            'showAllTab' => false,
            'showViewTabs' => false,
            'canAddViews' => false,
            'canFilter' => false,
            'canExportCsv' => true,
        ]);

        // Handle CSV export using trait's unified method with data mode
        if (!empty($result['isCsvExport'])) {
            $exportData = $this->prepareRecommendationsForExport($result['query']);
            return $this->handleCsvExport($result, $csvExportService, 'gathering-awards', 'Awards.Recommendations', $exportData);
        }

        // Post-process paginated data to add computed fields for display
        $recommendations = $result['data'];
        foreach ($recommendations as $recommendation) {
            // Build OP links HTML
            $recommendation->op_links = $this->buildOpLinksHtml($recommendation);

            // Build gatherings HTML (member attendance)
            $recommendation->gatherings = $this->buildGatheringsHtml($recommendation);

            // Build notes HTML
            $recommendation->notes = $this->buildNotesHtml($recommendation);

            // Build reason HTML with truncation
            $recommendation->reason = $this->buildReasonHtml($recommendation);
        }

        // Set view variables
        $this->set([
            'recommendations' => $recommendations,
            'data' => $recommendations,
            'rowActions' => RecommendationsGridColumns::getRowActions(),
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => RecommendationsGridColumns::getSearchableColumns(),
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

        // Render grid content
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $frameId = 'gathering-awards-grid-' . $gatheringId;

        // Build URLs for grid
        $queryParams = $this->request->getQueryParams();
        $dataUrl = Router::url([
            'plugin' => 'Awards',
            'controller' => 'Recommendations',
            'action' => 'gatheringAwardsGridData',
            $gatheringId,
        ]);
        $tableDataUrl = $dataUrl;
        if (!empty($queryParams)) {
            $tableDataUrl .= '?' . http_build_query($queryParams);
        }

        if ($turboFrame === $frameId . '-table') {
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            $this->set('frameId', $frameId);
            $this->set('dataUrl', $dataUrl);
            $this->set('tableDataUrl', $tableDataUrl);
            $this->viewBuilder()->setPlugin(null);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Perform a transactional bulk state transition and related updates for multiple recommendations.
     *
     * Updates the selected recommendations' state and corresponding status, and optionally sets
     * gathering assignment, given date, and close reason. When a note is provided, an administrative
     * note is created for each affected recommendation attributed to the current user. All changes
     * are applied inside a transaction and will be committed on success or rolled back on failure.
     *
     * Redirects to the provided current_page request value when present, otherwise redirects to the
     * table view for the current view/status. Flash messages are set to indicate success or failure.
     *
     * @return \Cake\Http\Response|null Redirects to configured page or back to table view
     * @see \Awards\Model\Entity\Recommendation::getStatuses() For state → status mapping
     * @see \Awards\Model\Table\NotesTable For note creation and management
     */
    public function updateStates(): ?\Cake\Http\Response
    {
        $view = $this->request->getData('view') ?? 'Index';
        $status = $this->request->getData('status') ?? 'All';

        $this->request->allowMethod(['post', 'get']);
        $user = $this->request->getAttribute('identity');
        $recommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($recommendation);

        $ids = explode(',', $this->request->getData('ids'));
        $newState = $this->request->getData('newState');
        $gathering_id = $this->request->getData('gathering_id');
        $given = $this->request->getData('given');
        $note = $this->request->getData('note');
        $close_reason = $this->request->getData('close_reason');

        if (empty($ids) || empty($newState)) {
            $this->Flash->error(__('No recommendations selected or new state not specified.'));
        } else {
            $this->Recommendations->getConnection()->begin();
            try {
                $statusList = Recommendation::getStatuses();
                $newStatus = '';

                // Find the status corresponding to the new state
                foreach ($statusList as $key => $value) {
                    foreach ($value as $state) {
                        if ($state === $newState) {
                            $newStatus = $key;
                            break 2;
                        }
                    }
                }

                // Build flat associative array for updateAll
                $updateFields = [
                    'state' => $newState,
                    'status' => $newStatus
                ];

                if ($gathering_id) {
                    $updateFields['gathering_id'] = $gathering_id;
                }

                if ($given) {
                    // Create DateTime at midnight UTC to preserve the exact date
                    $updateFields['given'] = new DateTime($given . ' 00:00:00', new \DateTimeZone('UTC'));
                }

                if ($close_reason) {
                    $updateFields['close_reason'] = $close_reason;
                }

                if (!$this->Recommendations->updateAll($updateFields, ['id IN' => $ids])) {
                    throw new \Exception('Failed to update recommendations');
                }

                if ($note) {
                    foreach ($ids as $id) {
                        $newNote = $this->Recommendations->Notes->newEmptyEntity();
                        $newNote->entity_id = $id;
                        $newNote->subject = 'Recommendation Bulk Updated';
                        $newNote->entity_type = 'Awards.Recommendations';
                        $newNote->body = $note;
                        $newNote->author_id = $user->id;

                        if (!$this->Recommendations->Notes->save($newNote)) {
                            throw new \Exception('Failed to save note');
                        }
                    }
                }

                $this->Recommendations->getConnection()->commit();
                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->success(__('The recommendations have been updated.'));
                }
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error updating recommendations: ' . $e->getMessage());

                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->error(__('The recommendations could not be updated. Please, try again.'));
                }
            }
        }
        $currentPage = $this->request->getData('current_page');
        if ($currentPage) {
            return $this->redirect($currentPage);
        }

        return $this->redirect(['action' => 'table', $view, $status]);
    }

    /**
     * Display a single recommendation with its workflow context and related entities.
     *
     * Loads the recommendation together with related data (requester, member, branch, award, gatherings)
     * and authorizes view access before exposing the entity to the view layer.
     *
     * @param string|null $id The recommendation ID to display.
     * @return \Cake\Http\Response|null The response for the rendered view, or null if the controller does not return a response.
     * @throws \Cake\Http\Exception\NotFoundException If the recommendation does not exist or is inaccessible.
     */
    public function view(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: ['Requesters', 'Members', 'Branches', 'Awards', 'Gatherings', 'AssignedGathering']);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;
            $this->set(compact('recommendation'));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Display the recommendation submission form for authenticated users and process posted submissions.
     *
     * Creates a new Recommendation linked to the current user, optionally associates member data and preferences,
     * initializes workflow state fields and court preference defaults, saves the record within a database transaction,
     * and either redirects on success or re-renders the form with dropdown data (awards, domains, levels, branches, gatherings).
     *
     * @return \Cake\Http\Response|null|void Redirects on successful submission or renders the form on GET / validation failure
     * @see submitRecommendation() For the unauthenticated public submission workflow
     * @see view() For recommendation detail display after submission
     * @see \Awards\Model\Entity\Recommendation For recommendation entity structure
     */
    public function add(): ?\Cake\Http\Response
    {
        try {
            $user = $this->request->getAttribute('identity');
            $recommendation = $this->Recommendations->newEmptyEntity();
            $this->Authorization->authorize($recommendation);

            if ($this->request->is('post')) {
                $data = $this->request->getData();

                // Convert member_public_id to member_id if provided
                if (!empty($data['member_public_id'])) {
                    $member = $this->Recommendations->Members->find('byPublicId', [$data['member_public_id']])->first();
                    if (!$member) {
                        $this->Flash->error(__('Member with provided public_id not found.'));
                        $this->response = $this->response->withStatus(400);
                        return $this->response;
                    }
                    $data['member_id'] = $member->id;
                    unset($data['member_public_id']);
                }

                $recommendation = $this->Recommendations->patchEntity($recommendation, $data, [
                    'associated' => ['Gatherings']
                ]);
                $recommendation->requester_id = $user->id;
                $recommendation->requester_sca_name = $user->sca_name;
                $recommendation->contact_email = $user->email_address;
                $recommendation->contact_number = $user->phone_number;

                $statuses = Recommendation::getStatuses();
                $recommendation->status = array_key_first($statuses);
                $recommendation->state = $statuses[$recommendation->status][0];
                $recommendation->state_date = DateTime::now();
                $recommendation->not_found = $this->request->getData('not_found') === 'on';

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                if ($recommendation->not_found) {
                    $recommendation->member_id = null;
                } else {
                    $this->Recommendations->getConnection()->begin();
                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;
                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }
                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }
                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        $this->Recommendations->getConnection()->rollback();
                        Log::error('Error loading member data: ' . $e->getMessage());
                        $this->Flash->error(__('Could not load member information. Please try again.'));
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->Recommendations->save($recommendation)) {
                    $this->Recommendations->getConnection()->commit();
                    $this->Flash->success(__('The recommendation has been saved.'));

                    if ($user->checkCan('view', $recommendation)) {
                        return $this->redirect(['action' => 'view', $recommendation->id]);
                    }

                    return $this->redirect([
                        'controller' => 'members',
                        'plugin' => null,
                        'action' => 'view',
                        $user->id
                    ]);
                }
                $this->Recommendations->getConnection()->rollback();
                $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
            }

            // Get data for dropdowns
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();

            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            $gatheringsData = $gatheringsTable->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where([
                    'start_date >' => DateTime::now(),
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            $gatherings = [];
            foreach ($gatheringsData as $gathering) {
                $gatherings[$gathering->id] = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();
            }

            $this->set(compact('recommendation', 'branches', 'awards', 'gatherings', 'awardsDomains', 'awardsLevels'));
            return null;
        } catch (\Exception $e) {
            $this->Recommendations->getConnection()->rollback();
            Log::error('Error in add recommendation: ' . $e->getMessage());
            $this->Flash->error(__('An unexpected error occurred. Please try again.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Render and process the public recommendation submission form for guest users.
     *
     * Presents a guest-facing form to submit award recommendations and handles form
     * submissions, creating a new recommendation record while applying necessary
     * defaults and minimal member-data integration when a matching member is provided.
     *
     * @return \Cake\Http\Response|null Redirects authenticated users or after successful redirects; otherwise renders the form.
     * @see add() For authenticated member submission workflow
     * @see beforeFilter() For authentication bypass configuration
     */
    public function submitRecommendation(): ?\Cake\Http\Response
    {
        $this->Authorization->skipAuthorization();
        $user = $this->request->getAttribute('identity');

        if ($user !== null) {
            return $this->redirect(['action' => 'add']);
        }

        $recommendation = $this->Recommendations->newEmptyEntity();

        if ($this->request->is(['post', 'put'])) {
            try {
                $this->Recommendations->getConnection()->begin();

                $data = $this->request->getData();

                // Convert member_public_id to member_id if provided
                if (!empty($data['member_public_id'])) {
                    $member = $this->Recommendations->Members->find('byPublicId', [$data['member_public_id']])->first();
                    if (!$member) {
                        $this->Flash->error(__('Member with provided public_id not found.'));
                        $this->response = $this->response->withStatus(400);
                        $this->Recommendations->getConnection()->rollback();
                        return $this->response;
                    }
                    $data['member_id'] = $member->id;
                    unset($data['member_public_id']);
                }

                $recommendation = $this->Recommendations->patchEntity($recommendation, $data, [
                    'associated' => ['Gatherings']
                ]);

                if ($recommendation->requester_id !== null) {
                    $requester = $this->Recommendations->Requesters->get(
                        $recommendation->requester_id,
                        fields: ['sca_name']
                    );
                    $recommendation->requester_sca_name = $requester->sca_name;
                }

                $statuses = Recommendation::getStatuses();
                $recommendation->status = array_key_first($statuses);
                $recommendation->state = $statuses[$recommendation->status][0];
                $recommendation->state_date = DateTime::now();

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                $recommendation->not_found = $this->request->getData('not_found') === 'on';

                if ($recommendation->not_found) {
                    $recommendation->member_id = null;
                } else {
                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;

                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }

                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }

                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error loading member data: ' . $e->getMessage());
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->Recommendations->save($recommendation)) {
                    $this->Recommendations->getConnection()->commit();
                    $this->Flash->success(__('The recommendation has been submitted.'));
                } else {
                    $this->Recommendations->getConnection()->rollback();
                    $this->Flash->error(__('The recommendation could not be submitted. Please, try again.'));
                }
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error submitting recommendation: ' . $e->getMessage());
                $this->Flash->error(__('An error occurred while submitting the recommendation. Please try again.'));
            }
        }

        // Load data for the form
        $headerImage = StaticHelpers::getAppSetting('KMP.Login.Graphic');
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

        $branches = $this->Recommendations->Awards->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])
            ->toArray();

        $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();

        $gatheringsTable = $this->fetchTable('Gatherings');
        $gatheringsData = $gatheringsTable->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(['start_date >' => DateTime::now()])
            ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
            ->orderBy(['start_date' => 'ASC'])
            ->all();

        $gatherings = [];
        foreach ($gatheringsData as $gathering) {
            $gatherings[$gathering->id] = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();
        }

        $this->set(compact(
            'recommendation',
            'branches',
            'awards',
            'gatherings',
            'awardsDomains',
            'awardsLevels',
            'headerImage'
        ));
        return null;
    }

    /**
     * Edit an existing recommendation with member data synchronization.
     *
     * Handles recommendation updates including member assignment changes, court preference
     * synchronization, and optional note creation within a database transaction.
     *
     * @param string|null $id Recommendation ID to edit
     * @return \Cake\Http\Response|null|void Redirects on successful edit or to current page
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     */
    public function edit(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'edit');

            if ($this->request->is(['patch', 'post', 'put'])) {
                $beforeMemberId = $recommendation->member_id;

                $data = $this->request->getData();

                // Convert member_public_id to member_id if provided
                if (!empty($data['member_public_id'])) {
                    $member = $this->Recommendations->Members->find('byPublicId', [$data['member_public_id']])->first();
                    if (!$member) {
                        $this->Flash->error(__('Member with provided public_id not found.'));
                        $this->response = $this->response->withStatus(400);
                        return $this->response;
                    }
                    $data['member_id'] = $member->id;
                    unset($data['member_public_id']);
                }

                $recommendation = $this->Recommendations->patchEntity($recommendation, $data, [
                    'associated' => ['Gatherings']
                ]);

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                // Handle member related fields
                if ($recommendation->member_id == 0 || $recommendation->member_id == null) {
                    $recommendation->member_id = null;
                    $recommendation->call_into_court = null;
                    $recommendation->court_availability = null;
                    $recommendation->person_to_notify = null;
                } elseif ($recommendation->member_id != $beforeMemberId) {
                    // Reset member-related fields when member changes
                    $recommendation->call_into_court = null;
                    $recommendation->court_availability = null;
                    $recommendation->person_to_notify = null;

                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;
                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }
                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }
                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error loading member data in edit: ' . $e->getMessage());
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                // Handle given date - treat as midnight UTC to avoid timezone shifts
                // Since the form input is date-only, we store it as the date at midnight UTC
                if ($this->request->getData('given') !== null && $this->request->getData('given') !== '') {
                    $dateString = $this->request->getData('given');
                    // Create DateTime at midnight UTC to preserve the exact date
                    $recommendation->given = new DateTime($dateString . ' 00:00:00', new \DateTimeZone('UTC'));
                } else {
                    $recommendation->given = null;
                }

                // Begin transaction
                $this->Recommendations->getConnection()->begin();

                try {
                    if (!$this->Recommendations->save($recommendation)) {
                        throw new \Exception('Failed to save recommendation');
                    }

                    $note = $this->request->getData('note');
                    if ($note) {
                        $newNote = $this->Recommendations->Notes->newEmptyEntity();
                        $newNote->entity_id = $recommendation->id;
                        $newNote->subject = 'Recommendation Updated';
                        $newNote->entity_type = 'Awards.Recommendations';
                        $newNote->body = $note;
                        $newNote->author_id = $this->request->getAttribute('identity')->id;

                        if (!$this->Recommendations->Notes->save($newNote)) {
                            throw new \Exception('Failed to save note');
                        }
                    }

                    $this->Recommendations->getConnection()->commit();

                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->success(__('The recommendation has been saved.'));
                    }
                } catch (\Exception $e) {
                    $this->Recommendations->getConnection()->rollback();
                    Log::error('Error saving recommendation: ' . $e->getMessage());

                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
                    }
                }
            }

            if ($this->request->getData('current_page')) {
                return $this->redirect($this->request->getData('current_page'));
            }

            return $this->redirect(['action' => 'view', $id]);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        } catch (\Exception $e) {
            Log::error('Error in edit recommendation: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while editing the recommendation.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Handle AJAX kanban board state transitions via drag-and-drop.
     *
     * Updates recommendation state and position within kanban columns.
     * Supports placeBefore/placeAfter positioning for stack ranking.
     *
     * @param string|null $id Recommendation ID to update
     * @return \Cake\Http\Response JSON response indicating success or failure
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     */
    public function kanbanUpdate(?string $id = null): \Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'edit');
            $message = 'failed';

            if ($this->request->is(['patch', 'post', 'put'])) {
                $recommendation->state = $this->request->getData('newCol');
                $placeBefore = $this->request->getData('placeBefore');
                $placeAfter = $this->request->getData('placeAfter');

                $placeAfter = $placeAfter ?? -1;
                $placeBefore = $placeBefore ?? -1;

                $recommendation->state_date = DateTime::now();
                $this->Recommendations->getConnection()->begin();

                try {
                    $failed = false;

                    if (!$this->Recommendations->save($recommendation)) {
                        throw new \Exception('Failed to save recommendation state');
                    }

                    if ($placeBefore != -1) {
                        if (!$this->Recommendations->moveBefore($id, $placeBefore)) {
                            throw new \Exception('Failed to move recommendation before target');
                        }
                    }

                    if ($placeAfter != -1) {
                        if (!$this->Recommendations->moveAfter($id, $placeAfter)) {
                            throw new \Exception('Failed to move recommendation after target');
                        }
                    }

                    $this->Recommendations->getConnection()->commit();
                    $message = 'success';
                } catch (\Exception $e) {
                    $this->Recommendations->getConnection()->rollback();
                    Log::error('Error updating kanban: ' . $e->getMessage());
                    $message = 'failed';
                }
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode($message));
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            Log::error('Kanban update failed - recommendation not found: ' . $id);
            return $this->response
                ->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode('not_found'));
        }
    }

    /**
     * Delete a recommendation with transaction safety.
     *
     * Performs soft deletion of the recommendation after authorization validation.
     *
     * @param string|null $id Recommendation ID to delete
     * @return \Cake\Http\Response|null Redirects to index page after deletion
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     */
    public function delete(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $this->request->allowMethod(['post', 'delete']);

            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation);

            $this->Recommendations->getConnection()->begin();
            try {
                if (!$this->Recommendations->delete($recommendation)) {
                    throw new \Exception('Failed to delete recommendation');
                }

                $this->Recommendations->getConnection()->commit();
                $this->Flash->success(__('The recommendation has been deleted.'));
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error deleting recommendation: ' . $e->getMessage());
                $this->Flash->error(__('The recommendation could not be deleted. Please, try again.'));
            }

            return $this->redirect(['action' => 'index']);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    #region JSON calls
    /**
     * Render a populated edit form for a recommendation intended for Turbo Frame partial updates.
     *
     * Loads the recommendation and related lookup data (awards, domains, branches, gatherings, state rules)
     * and exposes them to the view so the Turbo Frame can display an in-place edit form.
     *
     * @param string|null $id Recommendation ID to load for the form
     * @return \Cake\Http\Response|null A Response when the action issues an explicit response, or null after setting view variables for rendering
     * @throws \Cake\Http\Exception\NotFoundException If the recommendation cannot be found
     * @see edit() For form submission handling
     * @see turboQuickEditForm() For a streamlined quick-edit variant
     */
    public function turboEditForm(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Gatherings',
                'AssignedGathering',
                'Awards.Domains'
            ]);

            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;

            // Get data for form dropdowns and options
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('all', limit: 200)
                ->select(['id', 'name', 'specialties'])
                ->where(['domain_id' => $recommendation->domain_id])
                ->all();

            // Get filtered gatherings for this award
            // If status is "Given", show all gatherings (past and future) for retroactive entry
            $futureOnly = ($recommendation->status !== 'Given');
            $gatheringList = $this->getFilteredGatheringsForAward(
                $recommendation->award_id,
                $recommendation->member_id,
                $futureOnly,
                $recommendation->gathering_id  // Include the currently assigned gathering
            );

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'gatheringList',
                'awardsDomains',
                'awardsLevels',
                'statusList'
            ));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Render a streamlined Turbo Frame quick-edit form for a recommendation.
     *
     * Provides a minimal edit form containing the most commonly changed fields,
     * populated with essential dropdowns (awards, branches, gatherings, status)
     * and state rules to support rapid in-place edits.
     *
     * @param string|null $id Recommendation ID to load for the quick-edit form.
     * @return \Cake\Http\Response|null Renders the quick-edit template or null when rendering within a Turbo Frame.
     * @throws \Cake\Http\Exception\NotFoundException If the recommendation cannot be found.
     * @see turboEditForm() For the full edit interface
     * @see edit() For form submission handling
     * @see kanbanUpdate() For drag-and-drop state transitions
     */
    public function turboQuickEditForm(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Gatherings',
                'AssignedGathering',
                'Awards.Domains'
            ]);

            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;

            // Get data for form dropdowns and options
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('all', limit: 200)
                ->select(['id', 'name', 'specialties'])
                ->where(['domain_id' => $recommendation->domain_id])
                ->all();

            // Get filtered gatherings for this award
            // If status is "Given", show all gatherings (past and future) for retroactive entry
            $futureOnly = ($recommendation->status !== 'Given');
            $gatheringList = $this->getFilteredGatheringsForAward(
                $recommendation->award_id,
                $recommendation->member_id,
                $futureOnly,
                $recommendation->gathering_id  // Include the currently assigned gathering
            );

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'gatheringList',
                'awardsDomains',
                'awardsLevels',
                'statusList'
            ));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Render the Turbo Frame bulk edit form for modifying multiple recommendations at once.
     *
     * Prepares dropdowns and supporting data for bulk operations (branches, gatherings,
     * state/status options and state rules) and exposes them to the view as
     * `rules`, `branches`, `gatheringList`, and `statusList`.
     *
     * @return \Cake\Http\Response|null|void The response when rendering the bulk edit form template, or null when rendering proceeds in-controller.
     * @throws \Cake\Http\Exception\InternalErrorException When preparation of bulk form data fails.
     * @see updateStates() For bulk state transition processing
     * @see turboEditForm() For individual recommendation editing
     * @see \App\KMP\StaticHelpers::getAppSetting() For configuration loading
     */
    public function turboBulkEditForm(): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->newEmptyEntity();
            $this->Authorization->authorize($recommendation, 'view');

            // Get branch list for dropdown
            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            // Get gatherings data
            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            $gatheringsData = $gatheringsTable->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            // Format gathering list for dropdown
            $gatheringList = [];
            foreach ($gatheringsData as $gathering) {
                $gatheringList[$gathering->id] = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact('rules', 'branches', 'gatheringList', 'statusList'));
            return null;
        } catch (\Exception $e) {
            Log::error('Error in bulk edit form: ' . $e->getMessage());
            throw new \Cake\Http\Exception\InternalErrorException(__('An error occurred while preparing the bulk edit form.'));
        }
    }
    #endregion

    /**
     * Retrieve gatherings linked to an award and format them for display, optionally marking member attendance.
     *
     * @param int $awardId The award ID whose linked gatherings should be returned.
     * @param int|null $memberId Optional member ID; when provided, gatherings the member attends with `share_with_crown` enabled are marked.
     * @param bool $futureOnly When true, include only gatherings with a start date in the future.
     * @param int|null $includeGatheringId If provided, ensure this gathering ID is included in the results even if it would be excluded by the activity or date filters.
     * @return array Associative array mapping gathering ID => formatted display string ("Name in Branch on YYYY-MM-DD - YYYY-MM-DD"); entries with an asterisk indicate the member is attending and sharing with crown.
     */
    protected function getFilteredGatheringsForAward(int $awardId, ?int $memberId = null, bool $futureOnly = true, ?int $includeGatheringId = null): array
    {
        // Get all gathering activities linked to this award
        $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
        $linkedActivities = $awardGatheringActivitiesTable->find()
            ->where(['award_id' => $awardId])
            ->select(['gathering_activity_id'])
            ->toArray();

        $activityIds = array_map(function ($row) {
            return $row->gathering_activity_id;
        }, $linkedActivities);

        // If no activities are linked to the award, return empty array
        if (empty($activityIds)) {
            return [];
        }

        // Get gatherings that have these activities
        $gatheringsTable = $this->fetchTable('Gatherings');
        $query = $gatheringsTable->find()
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
            ->orderBy(['start_date' => 'DESC']);

        // Only filter by date if futureOnly is true
        if ($futureOnly) {
            $query->where(['start_date >' => DateTime::now()]);
            $query->orderBy(['start_date' => 'ASC']);
        }

        // Filter by linked activities
        $query->matching('GatheringActivities', function ($q) use ($activityIds) {
            return $q->where(['GatheringActivities.id IN' => $activityIds]);
        });

        $gatheringsData = $query->all();

        // Get attendance information for the member if member_id provided
        $attendanceMap = [];
        if ($memberId) {
            $attendanceTable = $this->fetchTable('GatheringAttendances');
            $attendances = $attendanceTable->find()
                ->where([
                    'member_id' => $memberId,
                    'deleted IS' => null
                ])
                ->select(['gathering_id', 'share_with_crown'])
                ->toArray();

            foreach ($attendances as $attendance) {
                $attendanceMap[$attendance->gathering_id] = $attendance->share_with_crown;
            }
        }

        // Build the response array
        $gatherings = [];
        foreach ($gatheringsData as $gathering) {
            $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

            $hasAttendance = isset($attendanceMap[$gathering->id]);
            $shareWithCrown = $hasAttendance && $attendanceMap[$gathering->id];

            // Add indicator if member is attending and sharing with crown
            if ($shareWithCrown) {
                $displayName .= ' *';
            }

            $gatherings[$gathering->id] = $displayName;
        }

        // If a specific gathering ID should be included (e.g., already assigned to recommendation)
        // and it's not already in the list, add it
        if ($includeGatheringId && !isset($gatherings[$includeGatheringId])) {
            $gatheringsTable = $this->fetchTable('Gatherings');
            $specificGathering = $gatheringsTable->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['Gatherings.id' => $includeGatheringId])
                ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
                ->first();

            if ($specificGathering) {
                $displayName = $specificGathering->name . ' in ' . $specificGathering->branch->name . ' on '
                    . $specificGathering->start_date->toDateString() . ' - ' . $specificGathering->end_date->toDateString();

                $hasAttendance = isset($attendanceMap[$specificGathering->id]);
                $shareWithCrown = $hasAttendance && $attendanceMap[$specificGathering->id];

                if ($shareWithCrown) {
                    $displayName .= ' *';
                }

                // Add to the beginning of the array so it appears first
                $gatherings = [$specificGathering->id => $displayName] + $gatherings;
            }
        }

        return $gatherings;
    }

    /**
     * Return gatherings linked to an award, optionally including attendance indicators for a member.
     *
     * Renders a JSON array where each item contains: `id`, `name`, `display` (formatted branch and date range,
     * with an appended `*` when the member is attending and sharing with crown), `has_attendance`, and
     * `share_with_crown`.
     *
     * @param string|null $awardId The award ID to filter gatherings for.
     * @throws \Cake\Http\Exception\NotFoundException When the specified award cannot be found.
     */
    public function gatheringsForAward(?string $awardId = null): void
    {
        $this->request->allowMethod(['get']);

        // Skip authorization - this is a data endpoint for add/edit forms
        // Authorization is handled at the form action level
        $this->Authorization->skipAuthorization();

        try {
            // Get member_id from query params if provided
            $memberId = $this->request->getQuery('member_id');

            // Get status from query params to determine if we should show all gatherings
            $status = $this->request->getQuery('status');
            $futureOnly = ($status !== 'Given');

            // Get the award to verify it exists
            $awardsTable = $this->fetchTable('Awards.Awards');
            $award = $awardsTable->get($awardId);

            // Get all gathering activities linked to this award
            $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
            $linkedActivities = $awardGatheringActivitiesTable->find()
                ->where(['award_id' => $awardId])
                ->select(['gathering_activity_id'])
                ->toArray();

            $activityIds = array_map(function ($row) {
                return $row->gathering_activity_id;
            }, $linkedActivities);

            // Get gatherings that have these activities
            $gatheringsTable = $this->fetchTable('Gatherings');
            $query = $gatheringsTable->find()
                ->contain([
                    'Branches' => function ($q) {
                        return $q->select(['id', 'name']);
                    }
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id']);

            // Only filter by date if futureOnly is true
            if ($futureOnly) {
                $query->where(['start_date >' => DateTime::now()])
                    ->orderBy(['start_date' => 'ASC']);
            } else {
                $query->orderBy(['start_date' => 'DESC']);
            }

            // If there are linked activities, filter by them
            if (!empty($activityIds)) {
                $query->matching('GatheringActivities', function ($q) use ($activityIds) {
                    return $q->where(['GatheringActivities.id IN' => $activityIds]);
                });
            } else {
                // If no activities are linked to the award, return empty array
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            $gatheringsData = $query->all();

            // Get attendance information for the member if member_id provided
            $attendanceMap = [];
            if ($memberId) {
                // member_id is passed as a public_id, so we need to look up the internal ID
                $membersTable = $this->fetchTable('Members');
                $member = $membersTable->find('byPublicId', publicId: $memberId)->first();

                if ($member) {
                    $attendanceTable = $this->fetchTable('GatheringAttendances');
                    $attendances = $attendanceTable->find()
                        ->where([
                            'member_id' => $member->id,
                            'deleted IS' => null
                        ])
                        ->select(['gathering_id', 'share_with_crown'])
                        ->toArray();

                    foreach ($attendances as $attendance) {
                        $attendanceMap[$attendance->gathering_id] = $attendance->share_with_crown;
                    }
                }
            }

            // Build the response array
            $gatherings = [];
            foreach ($gatheringsData as $gathering) {
                $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

                $hasAttendance = isset($attendanceMap[$gathering->id]);
                $shareWithCrown = $hasAttendance && $attendanceMap[$gathering->id];

                // Add indicator if member is attending and sharing with crown
                if ($shareWithCrown) {
                    $displayName .= ' *';
                }

                $gatherings[] = [
                    'id' => $gathering->id,
                    'name' => $gathering->name,
                    'display' => $displayName,
                    'has_attendance' => $hasAttendance,
                    'share_with_crown' => $shareWithCrown
                ];
            }

            $this->set([
                'gatherings' => $gatherings,
                '_serialize' => ['gatherings']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['gatherings']);
        } catch (\Exception $e) {
            Log::error('Error in gatheringsForAward: ' . $e->getMessage());
            $this->set([
                'error' => 'An error occurred while fetching gatherings',
                '_serialize' => ['error']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);
            $this->response = $this->response->withStatus(500);
        }
    }

    /**
     * Build and return a JSON list of gatherings suitable for bulk-editing the selected recommendations.
     *
     * Determines gatherings that offer activities common to all awards referenced by the provided recommendation IDs,
     * optionally limits to future gatherings depending on the supplied status, and includes attendance counts for members
     * referenced by the recommendations when they share attendance with Crown. Expects a POST request with `ids` (array
     * of recommendation ids) and optional `status`; skips authorization and renders a JSON payload with a `gatherings`
     * array (each item contains `id`, `name`, `display`, and `attendance_count`).
     */
    public function gatheringsForBulkEdit(): void
    {
        $this->request->allowMethod(['post']);

        // Skip authorization - this is a data endpoint for bulk edit form
        $this->Authorization->skipAuthorization();

        try {
            // Get recommendation IDs from request
            $ids = $this->request->getData('ids');
            if (empty($ids) || !is_array($ids)) {
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setClassName('Json');
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            // Get status from request to determine if we should show all gatherings
            $status = $this->request->getData('status');
            $futureOnly = ($status !== 'Given');

            // Fetch the selected recommendations with their awards and members
            $recommendations = $this->Recommendations->find()
                ->where(['Recommendations.id IN' => $ids])
                ->contain(['Awards', 'Members'])
                ->all();

            if ($recommendations->isEmpty()) {
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setClassName('Json');
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            // Get all unique award IDs and member IDs
            $awardIds = [];
            $memberIds = [];
            foreach ($recommendations as $rec) {
                $awardIds[] = $rec->award_id;
                if ($rec->member_id) {
                    $memberIds[] = $rec->member_id;
                }
            }
            $awardIds = array_unique($awardIds);
            $memberIds = array_unique($memberIds);

            // For each award, get the gathering activities that can give it out
            $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
            $activityIdsByAward = [];

            foreach ($awardIds as $awardId) {
                $linkedActivities = $awardGatheringActivitiesTable->find()
                    ->where(['award_id' => $awardId])
                    ->select(['gathering_activity_id'])
                    ->toArray();

                $activityIds = array_map(function ($row) {
                    return $row->gathering_activity_id;
                }, $linkedActivities);

                $activityIdsByAward[$awardId] = $activityIds;
            }

            // Find intersection - gatherings must have activities for ALL awards
            $commonActivityIds = null;
            foreach ($activityIdsByAward as $awardId => $activityIds) {
                if ($commonActivityIds === null) {
                    $commonActivityIds = $activityIds;
                } else {
                    // Keep only activities that exist in both arrays
                    $commonActivityIds = array_intersect($commonActivityIds, $activityIds);
                }
            }

            // If no common activities, return empty
            if (empty($commonActivityIds)) {
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setClassName('Json');
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            // Get gatherings that have these activities
            $gatheringsTable = $this->fetchTable('Gatherings');
            $query = $gatheringsTable->find()
                ->contain([
                    'Branches' => function ($q) {
                        return $q->select(['id', 'name']);
                    }
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
                ->orderBy(['start_date' => 'DESC']);

            // Only filter by date if futureOnly is true
            if ($futureOnly) {
                $query->where(['start_date >' => DateTime::now()]);
                $query->orderBy(['start_date' => 'ASC']);
            }

            // Filter by common activities
            $query->matching('GatheringActivities', function ($q) use ($commonActivityIds) {
                return $q->where(['GatheringActivities.id IN' => $commonActivityIds]);
            });

            $gatheringsData = $query->all();

            // Get attendance information for all members if any provided
            $attendanceMap = [];
            if (!empty($memberIds)) {
                $attendanceTable = $this->fetchTable('GatheringAttendances');
                $attendances = $attendanceTable->find()
                    ->where([
                        'member_id IN' => $memberIds,
                        'deleted IS' => null,
                        'share_with_crown' => true
                    ])
                    ->select(['gathering_id', 'member_id'])
                    ->toArray();

                foreach ($attendances as $attendance) {
                    if (!isset($attendanceMap[$attendance->gathering_id])) {
                        $attendanceMap[$attendance->gathering_id] = 0;
                    }
                    $attendanceMap[$attendance->gathering_id]++;
                }
            }

            // Build the response array
            $gatherings = [];
            foreach ($gatheringsData as $gathering) {
                $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

                // Add indicator if any members are attending and sharing with crown
                if (isset($attendanceMap[$gathering->id])) {
                    $count = $attendanceMap[$gathering->id];
                    $displayName .= ' *(' . $count . ')';
                }

                $gatherings[] = [
                    'id' => $gathering->id,
                    'name' => $gathering->name,
                    'display' => $displayName,
                    'attendance_count' => $attendanceMap[$gathering->id] ?? 0
                ];
            }

            $this->set([
                'gatherings' => $gatherings,
                '_serialize' => ['gatherings']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['gatherings']);
        } catch (\Exception $e) {
            Log::error('Error in gatheringsForBulkEdit: ' . $e->getMessage());
            $this->set([
                'error' => 'An error occurred while fetching gatherings',
                '_serialize' => ['error']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);
            $this->response = $this->response->withStatus(500);
        }
    }
}