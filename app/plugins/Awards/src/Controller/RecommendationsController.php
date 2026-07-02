<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;
use Awards\Model\Entity\Recommendation;
use Awards\KMP\GridColumns\RecommendationsGridColumns;
use Awards\Services\RecommendationGroupingService;
use Cake\I18n\DateTime;
use Cake\Routing\Router;
use App\KMP\StaticHelpers;
use App\KMP\GridViewConfig;
use App\Model\Entity\Member;
use App\Model\Table\GridViewsTable;
use App\Controller\DataverseGridTrait;
use App\Services\ServiceResult;
use App\Services\GridViewService;
use Authorization\Exception\ForbiddenException;
use Cake\Log\Log;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Exception;
use PhpParser\Node\Stmt\TryCatch;
use App\KMP\GridRowDomId;
use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Services\CsvExportService;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Awards\Services\BestowalGatheringLookupService;
use Awards\Services\RecommendationFormService;
use Awards\Services\RecommendationFeedbackService;
use Awards\Services\RecommendationUiModeService;
use Awards\Services\RecommendationSubmissionService;
use Awards\Services\RecommendationQueryService;
use Awards\Services\RecommendationUpdateService;
use Awards\Services\RecommendationWorkflowUiService;
use Cake\Error\Debugger;


/**
 * Recommendations Controller
 * 
 * Manages the complete award recommendation lifecycle from submission through final
 * disposition. Implements state machine-based workflow with table views.
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
    use \App\Controller\WorkflowDispatchTrait;

    private const BESTOWAL_GATHERING_REQUIRED_KEY = 'requires_bestowal_gathering';
    private const BESTOWAL_GATHERING_WORKFLOW_SLUGS = [
        'awards-recommendation-submitted',
        'awards-existing-recommendation-approval',
    ];

    /**
     * Configure authentication for public recommendation submission helpers.
     * 
     * @param \Cake\Event\EventInterface $event The beforeFilter event instance
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): ?\Cake\Http\Response
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            'submitRecommendation',
            'gatheringsForAward',
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

        // Prepare bulk edit modal data if user can edit
        if ($user->checkCan('edit', $emptyRecommendation)) {
            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }


            // Get explicit UI mode rules for form field visibility
            $rules = (new RecommendationUiModeService())->buildStateRules();

            // Empty gathering list initially - will be populated via AJAX
            $gatheringList = [];

            $this->set(compact('rules', 'statusList', 'gatheringList'));
        }

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
    public function gridData(CsvExportService $csvExportService, RecommendationQueryService $queryService)
    {
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'index');

        // Check if user can view hidden states
        $user = $this->request->getAttribute('identity');
        $canViewHidden = $user->checkCan('ViewHidden', $emptyRecommendation);

        // Build via service
        $systemViews = RecommendationsGridColumns::getSystemViews([]);
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Awards.Recommendations.index.main',
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-in-approval',
            'defaultSort' => ['Recommendations.created' => 'desc'],
        ]);
        $built = $queryService->buildMainGridQuery(
            $this->Recommendations,
            $user->checkCan('edit', $emptyRecommendation),
            $queryContext->loadsColumn('notes'),
            $queryContext->queryVisibleColumns(),
            (int)$user->id,
        );
        $baseQuery = $built['query'];
        $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
        $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
        $built['gridOptions']['baseQuery'] = $baseQuery;

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid($built['gridOptions']);
        $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);
        $result = $this->filterRecommendationGridActionsForResult($result);

        // Handle CSV export using trait's unified method with data mode
        if (!empty($result['isCsvExport'])) {
            // Fetch all data from query (not paginated) and process computed fields
            $exportData = $this->prepareRecommendationsForExport($result['query'], ['includeAttendance' => true, 'includeGroupedChildren' => true]);
            return $this->handleCsvExport($result, $csvExportService, 'recommendations', 'Awards.Recommendations', $exportData);
        }

        // Post-process data to add computed fields for display
        $recommendations = $result['data'];
        $this->enrichRecommendationsForGrid($recommendations, (array)$result['visibleColumns']);

        $rowActions = $this->filterRecommendationRowActionsForGridResult(
            RecommendationsGridColumns::getRowActions(),
            $result,
        );

        $this->renderDataverseGridResponse(
            result: $result,
            frameId: 'recommendations-grid',
            collectionVar: 'recommendations',
            extraViewVars: [
                'data' => $recommendations,
                'rowActions' => $rowActions,
                'searchableColumns' => RecommendationsGridColumns::getSearchableColumns(),
                'canViewHidden' => $canViewHidden,
            ],
        );
    }

    /**
     * Inject dynamic state filter options into a Dataverse grid result payload.
     *
     * @param array $result Dataverse grid processing result.
     * @param bool $canViewHidden Whether hidden states should be included.
     * @return array Updated grid result with state filter options in metadata and gridState.
     */
    protected function applyStateFilterOptionsToGridResult(array $result, bool $canViewHidden): array
    {
        $stateFilterOptions = RecommendationsGridColumns::getStateFilterOptions($canViewHidden);
        $result['filterOptions']['state'] = $stateFilterOptions;

        if (isset($result['columnsMetadata']['state'])) {
            $result['columnsMetadata']['state']['filterOptions'] = $stateFilterOptions;
            $result['gridState']['filters']['available']['state'] = [
                'label' => $result['columnsMetadata']['state']['label'] ?? 'State',
                'options' => $stateFilterOptions,
            ];
        }

        return $result;
    }

    /**
     * Apply hidden-state visibility constraints to a recommendations query.
     *
     * @param \Cake\ORM\Query\SelectQuery $query Recommendations query
     * @param bool $canViewHidden Whether hidden rows may be included
     * @return \Cake\ORM\Query\SelectQuery
     * @deprecated Delegate to RecommendationQueryService::applyHiddenStateVisibility() instead.
     */
    protected function applyHiddenStateVisibility(SelectQuery $query, bool $canViewHidden): SelectQuery
    {
        return (new RecommendationQueryService())->applyHiddenStateVisibility($query, $canViewHidden);
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
     * Fetch gatherings where members are attending with share_with_crown or share_with_kingdom enabled.
     *
     * Retrieves attendance records for all unique member_ids in the recommendations set
     * and returns a map of member_id => array of gathering entities.
     *
     * @param iterable $recommendations Collection of recommendation entities
     * @return array<int, array<\App\Model\Entity\Gathering>> Map of member_id to gatherings array
     */
    protected function getMemberAttendanceGatherings(iterable $recommendations): array
    {
        // Collect unique member IDs
        $memberIds = [];
        foreach ($recommendations as $rec) {
            if ($rec->member_id) {
                $memberIds[$rec->member_id] = true;
            }
        }

        if (empty($memberIds)) {
            return [];
        }

        $memberIds = array_keys($memberIds);

        // Fetch attendance records where the member has shared their attendance
        // share_with_crown: explicitly shared with crown/royalty
        // share_with_kingdom: shared at kingdom level (which includes crown)
        $attendanceTable = $this->fetchTable('GatheringAttendances');
        $attendances = $attendanceTable->find()
            ->contain([
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                }
            ])
            ->where([
                'GatheringAttendances.member_id IN' => $memberIds,
                'OR' => [
                    'GatheringAttendances.share_with_crown' => true,
                    'GatheringAttendances.share_with_kingdom' => true,
                ],
            ])
            ->all();

        // Build the map: member_id => [gatherings]
        $result = [];
        foreach ($attendances as $attendance) {
            if (!$attendance->gathering) {
                continue;
            }
            $memberId = $attendance->member_id;
            if (!isset($result[$memberId])) {
                $result[$memberId] = [];
            }
            // Avoid duplicates by keying by gathering id
            $result[$memberId][$attendance->gathering->id] = $attendance->gathering;
        }

        // Convert inner arrays from id-keyed to simple indexed arrays
        foreach ($result as $memberId => $gatherings) {
            $result[$memberId] = array_values($gatherings);
        }

        return $result;
    }

    /**
     * Build gatherings HTML showing combined recommendation events and member attendance.
     *
     * Displays up to 3 gatherings with a "more" link to expand if there are more.
     * Attendance gatherings (from GatheringAttendances with share_with_crown or share_with_kingdom)
     * are shown with a person-check icon to distinguish from recommendation-linked events.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity
     * @param array $attendanceGatherings Optional array of gatherings from member attendance
     * @return string HTML string with gathering information
     */
    protected function buildGatheringsHtml($recommendation, array $attendanceGatherings = []): string
    {
        // Combine recommendation-linked gatherings with attendance gatherings
        $recGatherings = $recommendation->gatherings ?? [];
        $recGatheringIds = [];

        // Build a lookup of attendance gathering IDs for quick reference
        $attendanceGatheringIds = [];
        foreach ($attendanceGatherings as $gathering) {
            $attendanceGatheringIds[$gathering->id] = true;
        }

        // Build list from recommendation-linked gatherings (from awards_recommendations_events)
        // If the member is also attending this gathering, show the attendance icon (takes precedence)
        $items = [];
        foreach ($recGatherings as $gathering) {
            $recGatheringIds[$gathering->id] = true;
            $items[] = [
                'name' => $gathering->name,
                'id' => $gathering->id,
                'isAttendance' => isset($attendanceGatheringIds[$gathering->id]),
            ];
        }

        // Add attendance gatherings that aren't already in rec gatherings
        foreach ($attendanceGatherings as $gathering) {
            if (!isset($recGatheringIds[$gathering->id])) {
                $items[] = [
                    'name' => $gathering->name,
                    'id' => $gathering->id,
                    'isAttendance' => true,
                ];
            }
        }

        if (empty($items)) {
            return '';
        }

        $total = count($items);
        $maxVisible = 3;

        // Build display names with attendance indicator
        $names = [];
        foreach ($items as $item) {
            $displayName = h($item['name']);
            if ($item['isAttendance']) {
                // Add crown icon for attendance-based gatherings
                $displayName = '<span title="' . __('Member shared attendance') . '">'
                    . '<i class="bi bi-person-check text-success me-1"></i>' . $displayName . '</span>';
            }
            $names[] = $displayName;
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
        $hiddenId = $uniqueId . '-hidden';
        $moreLabel = '+' . $hiddenCount . ' more';
        $html .= '<span id="' . $hiddenId . '" hidden style="display:none;">, ' . implode(', ', $hiddenNames) . '</span>';
        $html .= ' <a href="#" class="text-primary small" data-controller="show-more"';
        $html .= ' data-action="show-more#toggle"';
        $html .= ' data-show-more-target-selector-value="#' . $hiddenId . '"';
        $html .= ' data-show-more-more-label-value="' . h($moreLabel) . '"';
        $html .= ' data-show-more-less-label-value="' . __('less') . '">';
        $html .= h($moreLabel) . '</a>';
        $html .= '</span>';

        return $html;
    }

    /**
     * Build gatherings export text showing combined recommendation events and member attendance.
     *
     * Attendance gatherings are marked with a text suffix to replace the UI icon.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity
     * @param array $attendanceGatherings Optional array of gatherings from member attendance
     * @return string Text string with gathering information for exports
     */
    protected function buildGatheringsExportValue($recommendation, array $attendanceGatherings = []): string
    {
        // Combine recommendation-linked gatherings with attendance gatherings
        $recGatherings = $recommendation->gatherings ?? [];
        $recGatheringIds = [];

        // Build a lookup of attendance gathering IDs for quick reference
        $attendanceGatheringIds = [];
        foreach ($attendanceGatherings as $gathering) {
            $attendanceGatheringIds[$gathering->id] = true;
        }

        // Build list from recommendation-linked gatherings (from awards_recommendations_events)
        // If the member is also attending this gathering, show attendance suffix (takes precedence)
        $items = [];
        foreach ($recGatherings as $gathering) {
            $recGatheringIds[$gathering->id] = true;
            $items[] = [
                'name' => $gathering->name,
                'id' => $gathering->id,
                'isAttendance' => isset($attendanceGatheringIds[$gathering->id]),
            ];
        }

        // Add attendance gatherings that aren't already in rec gatherings
        foreach ($attendanceGatherings as $gathering) {
            if (!isset($recGatheringIds[$gathering->id])) {
                $items[] = [
                    'name' => $gathering->name,
                    'id' => $gathering->id,
                    'isAttendance' => true,
                ];
            }
        }

        if (empty($items)) {
            return '';
        }

        $names = [];
        $attendanceSuffix = ' ' . __('(shared attendance)');
        foreach ($items as $item) {
            $displayName = $item['name'];
            if ($item['isAttendance']) {
                $displayName .= $attendanceSuffix;
            }
            $names[] = $displayName;
        }

        return implode(', ', $names);
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
     * @param array $options Export options (includeAttendance => bool)
     * @return iterable Recommendations with computed fields populated
     */
    protected function prepareRecommendationsForExport($query, array $options = []): iterable
    {
        // Execute query to get all matching records (not paginated)
        $recommendations = $query->all()->toList();
        $includeAttendance = $options['includeAttendance'] ?? false;
        $includeGroupedChildren = $options['includeGroupedChildren'] ?? false;

        $memberAttendanceGatherings = [];
        if ($includeAttendance) {
            $memberAttendanceGatherings = $this->getMemberAttendanceGatherings($recommendations);
        }

        // Process each recommendation to populate computed fields for export
        foreach ($recommendations as $recommendation) {
            $attendanceGatherings = [];
            if ($includeAttendance && $recommendation->member_id) {
                $attendanceGatherings = $memberAttendanceGatherings[$recommendation->member_id] ?? [];
            }

            $recommendation->gatherings = $this->buildGatheringsExportValue($recommendation, $attendanceGatherings);
        }

        if (!$includeGroupedChildren) {
            return $recommendations;
        }

        // Interleave grouped children after their group head
        $headIds = [];
        foreach ($recommendations as $rec) {
            $headIds[] = $rec->id;
        }

        $children = [];
        if (!empty($headIds)) {
            $children = $this->Recommendations->find()
                ->where(['Recommendations.recommendation_group_id IN' => $headIds])
                ->contain([
                    'Requesters' => function ($q) {
                        return $q->select(['id', 'sca_name']);
                    },
                    'Members' => function ($q) {
                        return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation']);
                    },
                    'Branches' => function ($q) {
                        return $q->select(['id', 'name', 'type']);
                    },
                    'Awards' => function ($q) {
                        return $q->select(['id', 'abbreviation', 'branch_id']);
                    },
                    'Awards.Domains' => function ($q) {
                        return $q->select(['id', 'name']);
                    },
                    'AssignedGathering' => function ($q) {
                        return $q->select(['id', 'name', 'cancelled_at']);
                    },
                ])
                ->orderBy(['Recommendations.recommendation_group_id' => 'asc', 'Recommendations.created' => 'asc'])
                ->all()
                ->toList();
        }

        if (empty($children)) {
            return $recommendations;
        }

        // Group children by their head ID
        $childrenByHead = [];
        foreach ($children as $child) {
            $childrenByHead[$child->recommendation_group_id][] = $child;
        }

        // Build final list with children interleaved after their head
        $result = [];
        foreach ($recommendations as $rec) {
            $result[] = $rec;
            if (isset($childrenByHead[$rec->id])) {
                foreach ($childrenByHead[$rec->id] as $child) {
                    // Prefix member name to visually indicate linked child in export
                    $child->member_sca_name = '↳ ' . ($child->member_sca_name ?? '');
                    $child->gatherings = '';
                    $result[] = $child;
                }
            }
        }

        return $result;
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
    public function memberSubmittedRecsGridData(CsvExportService $csvExportService, RecommendationQueryService $queryService, ?int $memberId = null)
    {
        // Resolve member ID
        if ($memberId === null || $memberId === -1) {
            $memberId = $this->request->getAttribute('identity')->id;
        }

        $user = $this->request->getAttribute('identity');
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $emptyRecommendation->requester_id = $memberId;

        $this->Authorization->authorize($emptyRecommendation, 'ViewSubmittedByMember');
        // Members viewing their own submissions should always see all states
        $isOwnSubmissions = ($user->id === $memberId);
        $canViewHidden = $isOwnSubmissions || $user->checkCan('ViewHidden', $emptyRecommendation);

        // Build via service
        $systemViews = RecommendationsGridColumns::getSystemViews(['context' => 'memberSubmitted']);
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Awards.Recommendations.memberSubmitted.' . $memberId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-submitted-by',
            'defaultSort' => ['Recommendations.created' => 'desc'],
        ]);
        $built = $queryService->buildMemberSubmittedQuery(
            $this->Recommendations,
            $memberId,
            $queryContext->queryVisibleColumns(),
        );
        $baseQuery = $built['query'];
        $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
        $built['gridOptions']['baseQuery'] = $baseQuery;

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid($built['gridOptions']);
        $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);

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
    public function recsForMemberGridData(CsvExportService $csvExportService, RecommendationQueryService $queryService, ?int $memberId = null)
    {
        // Resolve member ID
        if ($memberId === null || $memberId === -1) {
            $memberId = $this->request->getAttribute('identity')->id;
        }

        $user = $this->request->getAttribute('identity');
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();



        $this->Authorization->authorize($emptyRecommendation, 'ViewSubmittedForMember');
        // If the user can see this tab at all, they should see all states
        $canViewHidden = true;

        // Build via service
        $systemViews = RecommendationsGridColumns::getSystemViews(['context' => 'recsForMember']);
        $queryContext = $this->resolveDataverseGridQueryContext([
            'gridKey' => 'Awards.Recommendations.forMember.' . $memberId,
            'gridColumnsClass' => RecommendationsGridColumns::class,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-recs-for-member',
            'defaultSort' => ['Recommendations.created' => 'desc'],
        ]);
        $built = $queryService->buildRecsForMemberQuery(
            $this->Recommendations,
            $memberId,
            $queryContext->queryVisibleColumns(),
        );
        $baseQuery = $built['query'];

        // Apply authorization scope
        $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
        $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
        $built['gridOptions']['baseQuery'] = $baseQuery;

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid($built['gridOptions']);
        $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);

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
     * Request feedback on selected recommendations or recommendation groups.
     */
    public function requestFeedback(RecommendationFeedbackService $feedbackService): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $recommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($recommendation, 'requestFeedback');

        $ids = $this->parseIdList((string)$this->request->getData('ids'));
        $recipientIds = $this->parseMemberIdList((string)$this->request->getData('recipient_ids'));
        $message = $this->request->getData('message');
        $deadline = $this->request->getData('deadline');
        $user = $this->request->getAttribute('identity');

        $result = $feedbackService->createRequests(
            $ids,
            $recipientIds,
            (int)$user->id,
            is_string($message) ? $message : null,
            is_string($deadline) && $deadline !== '' ? $deadline : null,
        );

        if ($result->isSuccess()) {
            $this->Flash->success(__('Feedback request sent.'));
        } else {
            $this->Flash->error($result->getError() ?? __('Feedback request could not be sent.'));
        }

        $pageContext = $this->getPageContextUrl();
        if ($result->isSuccess() && $this->wantsTurboStreamRequest() && $pageContext !== null) {
            return $this->renderTurboCloseModal(
                'recommendations-grid-table',
                ['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'gridData'],
                $pageContext,
            );
        }

        return $this->redirect($pageContext ?: ['action' => 'index']);
    }

    /**
     * Retract a pending recommendation feedback request.
     */
    public function retractFeedback(RecommendationFeedbackService $feedbackService): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $recommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($recommendation, 'retractFeedback');

        $user = $this->request->getAttribute('identity');
        $requestId = (int)$this->request->getData('feedback_request_id');
        $adminOverride = $user->checkCan('administerFeedback', $recommendation);
        $result = $feedbackService->retractRequest($requestId, (int)$user->id, $adminOverride);

        if ($result->isSuccess()) {
            $this->Flash->success(__('Feedback request retracted.'));
        } else {
            $this->Flash->error($result->getError() ?? __('Feedback request could not be retracted.'));
        }

        return $this->redirect($this->referer(['action' => 'index']));
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
            $workflowUiService = new RecommendationWorkflowUiService();
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Gatherings',
                'AssignedGathering',
                'Bestowals',
                'GroupHead' => ['Awards'],
                'GroupChildren' => ['Awards', 'Requesters'],
                'FeedbackRequestItems' => [
                    'FeedbackRequests' => [
                        'Requesters',
                        'Recipients' => [
                            'RecipientMembers',
                            'WorkflowApprovals',
                            'WorkflowApprovalResponses',
                        ],
                    ],
                ],
            ]);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;
            $workflowContext = $workflowUiService->buildContext(
                $recommendation,
                $this->request->getAttribute('identity'),
            );
            if (!empty($workflowContext['pendingApproval']) && $workflowContext['pendingApproval'] instanceof WorkflowApproval) {
                $workflowContext['pendingApproval']->approver_config = $this->augmentApproverConfigForResponse(
                    $this->normalizeApproverConfig($workflowContext['pendingApproval']->approver_config),
                    $workflowContext['pendingApproval'],
                    $recommendation,
                );
            }

            // Fetch member's self-selected attendance gatherings (where they've shared with crown/kingdom)
            $memberAttendanceGatherings = [];
            if ($recommendation->member_id) {
                $attendanceTable = $this->fetchTable('GatheringAttendances');
                $attendances = $attendanceTable->find()
                    ->contain([
                        'Gatherings' => function ($q) {
                            return $q->select(['id', 'name', 'start_date', 'end_date', 'public_id']);
                        }
                    ])
                    ->where([
                        'GatheringAttendances.member_id' => $recommendation->member_id,
                        'OR' => [
                            'GatheringAttendances.share_with_crown' => true,
                            'GatheringAttendances.share_with_kingdom' => true,
                        ],
                    ])
                    ->orderBy(['Gatherings.start_date' => 'ASC'])
                    ->all();

                foreach ($attendances as $attendance) {
                    if ($attendance->gathering) {
                        $memberAttendanceGatherings[] = $attendance->gathering;
                    }
                }
            }

            $this->set(compact('recommendation', 'memberAttendanceGatherings', 'workflowContext'));

            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Record the current user's approval decision from a recommendation screen.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Trigger dispatcher.
     * @param string|null $id Recommendation ID.
     * @return \Cake\Http\Response|null
     */
    public function workflowDecision(
        TriggerDispatcher $triggerDispatcher,
        ?string $id = null,
    ): ?\Cake\Http\Response {
        $this->request->allowMethod(['post']);
        if ($id === null) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }

        $recommendation = $this->Recommendations->get($id, contain: ['Awards']);
        $this->Authorization->authorize($recommendation, 'decideApproval');

        $identity = $this->request->getAttribute('identity');
        $workflowUiService = new RecommendationWorkflowUiService();
        $approval = $workflowUiService->pendingApprovalForRecommendation($recommendation, $identity);
        if (!$approval instanceof WorkflowApproval) {
            $this->Flash->error(__('There is no pending approval assigned to you for this recommendation.'));

            return $this->redirect(['action' => 'view', $recommendation->id]);
        }

        $decision = (string)$this->request->getData('decision');
        $comment = trim((string)$this->request->getData('comment'));
        $bestowalGatheringId = $this->getPostedBestowalGatheringId();
        $approverConfig = $approval->approver_config ?? [];
        $requiresComment = $decision === WorkflowApprovalResponse::DECISION_REJECT
            || !empty($approverConfig['requires_comment']);
        if ($requiresComment && $comment === '') {
            $this->Flash->error(__('A comment is required for this approval decision.'));

            return $this->redirect(['action' => 'view', $recommendation->id]);
        }

        if (!in_array($decision, WorkflowApprovalDecisionOptions::allowedValues($approverConfig), true)) {
            $this->Flash->error(__('Invalid approval decision.'));

            return $this->redirect(['action' => 'view', $recommendation->id]);
        }

        $gatheringError = $this->validateBestowalGatheringSelection(
            $approval,
            $decision,
            $bestowalGatheringId,
            $recommendation,
        );
        if ($gatheringError !== null) {
            $this->Flash->error($gatheringError);

            return $this->redirect(['action' => 'view', $recommendation->id]);
        }

        $result = $this->recordWorkflowApprovalDecision(
            $approval,
            (int)$identity->getAsMember()->id,
            $decision,
            $comment !== '' ? $comment : null,
            $triggerDispatcher,
            $bestowalGatheringId,
        );

        if ($result->isSuccess()) {
            $this->Flash->success(__('Approval response recorded.'));
        } else {
            $this->Flash->error($result->getError() ?? __('Approval response could not be recorded.'));
        }

        return $this->redirect(['action' => 'view', $recommendation->id]);
    }

    /**
     * Record a grid approval decision for the current user's pending recommendation approval.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Trigger dispatcher.
     * @return \Cake\Http\Response|null
     */
    public function workflowDecisionFromGrid(TriggerDispatcher $triggerDispatcher): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->authorize($this->Recommendations->newEmptyEntity(), 'index');

        $pageContext = $this->getPageContextUrl();
        $identity = $this->request->getAttribute('identity');
        $approvalId = (int)$this->request->getData('approvalId');
        $recommendationId = $this->getPostedRecommendationId();
        $decision = (string)$this->request->getData('decision');
        $comment = trim((string)$this->request->getData('comment'));
        $bestowalGatheringId = $this->getPostedBestowalGatheringId();
        $memberId = (int)$identity->getAsMember()->id;

        $approval = $this->getPendingApprovalForMember($approvalId, $memberId);
        if (!$approval instanceof WorkflowApproval) {
            $this->Flash->error(__('There is no pending approval assigned to you for this recommendation.'));

            return $this->recommendationsGridRefreshResponse($pageContext)
                ?? $this->redirect($pageContext ?: ['action' => 'index']);
        }

        $validationError = $this->validateWorkflowDecision($approval, $decision, $comment);
        if ($validationError !== null) {
            $this->Flash->error($validationError);

            return $this->recommendationsGridRefreshResponse($pageContext)
                ?? $this->redirect($pageContext ?: ['action' => 'index']);
        }

        $recommendation = $this->getRecommendationForApproval($approval, $recommendationId);
        $gatheringError = $this->validateBestowalGatheringSelection(
            $approval,
            $decision,
            $bestowalGatheringId,
            $recommendation,
        );
        if ($gatheringError !== null) {
            $this->Flash->error($gatheringError);

            return $this->recommendationsGridRefreshResponse($pageContext)
                ?? $this->redirect($pageContext ?: ['action' => 'index']);
        }

        $result = $this->recordWorkflowApprovalDecision(
            $approval,
            $memberId,
            $decision,
            $comment !== '' ? $comment : null,
            $triggerDispatcher,
            $bestowalGatheringId,
        );

        if ($result->isSuccess()) {
            $this->Flash->success(__('Approval response recorded.'));
        } else {
            $this->Flash->error($result->getError() ?? __('Approval response could not be recorded.'));
        }

        return $this->recommendationsGridRefreshResponse($pageContext)
            ?? $this->redirect($pageContext ?: ['action' => 'index']);
    }

    /**
     * Record one decision against multiple selected recommendation workflow approvals.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Trigger dispatcher.
     * @return \Cake\Http\Response|null
     */
    public function bulkWorkflowDecision(TriggerDispatcher $triggerDispatcher): ?\Cake\Http\Response
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->authorize($this->Recommendations->newEmptyEntity(), 'index');

        $pageContext = $this->getPageContextUrl();
        $identity = $this->request->getAttribute('identity');
        $memberId = (int)$identity->getAsMember()->id;
        $decision = (string)$this->request->getData('decision');
        $comment = trim((string)$this->request->getData('comment'));
        $bestowalGatheringId = $this->getPostedBestowalGatheringId();
        $approvalIds = array_values(array_unique(array_map(
            'intval',
            (array)$this->request->getData('approval_ids'),
        )));
        $approvalIds = array_filter($approvalIds);

        if ($approvalIds === []) {
            $this->Flash->error(__('Select at least one recommendation that is pending your approval.'));

            return $this->recommendationsGridRefreshResponse($pageContext)
                ?? $this->redirect($pageContext ?: ['action' => 'index']);
        }

        $eligibleApprovals = $this->getPendingApprovalsForMember($memberId, $approvalIds);
        if ($eligibleApprovals === []) {
            $this->Flash->error(__('None of the selected recommendations are pending your approval.'));

            return $this->recommendationsGridRefreshResponse($pageContext)
                ?? $this->redirect($pageContext ?: ['action' => 'index']);
        }

        $recorded = 0;
        $failed = 0;
        foreach ($eligibleApprovals as $approval) {
            $validationError = $this->validateWorkflowDecision($approval, $decision, $comment);
            if ($validationError !== null) {
                $failed++;
                continue;
            }
            $gatheringError = $this->validateBestowalGatheringSelection(
                $approval,
                $decision,
                $bestowalGatheringId,
            );
            if ($gatheringError !== null) {
                $failed++;
                continue;
            }

            $result = $this->recordWorkflowApprovalDecision(
                $approval,
                $memberId,
                $decision,
                $comment !== '' ? $comment : null,
                $triggerDispatcher,
                $bestowalGatheringId,
            );
            if ($result->isSuccess()) {
                $recorded++;
            } else {
                $failed++;
            }
        }

        $skipped = count($approvalIds) - count($eligibleApprovals);
        if ($recorded > 0 && ($failed > 0 || $skipped > 0)) {
            $this->Flash->warning(__(
                '{0} approval response(s) recorded. {1} selected item(s) could not be processed.',
                $recorded,
                $failed + $skipped,
            ));
        } elseif ($recorded > 0) {
            $this->Flash->success(__('{0} approval response(s) recorded.', $recorded));
        } else {
            $this->Flash->error(__('No approval responses could be recorded for the selected recommendations.'));
        }

        return $this->recommendationsGridRefreshResponse($pageContext)
            ?? $this->redirect($pageContext ?: ['action' => 'index']);
    }

    /**
     * @param \App\Model\Entity\WorkflowApproval $approval Approval entity.
     * @param string $decision Decision value.
     * @param string $comment Comment text.
     * @return string|null Validation error, if any.
     */
    private function validateWorkflowDecision(WorkflowApproval $approval, string $decision, string $comment): ?string
    {
        $approverConfig = is_array($approval->approver_config) ? $approval->approver_config : [];
        $requiresComment = $decision === WorkflowApprovalResponse::DECISION_REJECT
            || !empty($approverConfig['requires_comment']);

        if ($requiresComment && $comment === '') {
            return __('A comment is required for this approval decision.');
        }

        if (!in_array($decision, WorkflowApprovalDecisionOptions::allowedValues($approverConfig), true)) {
            return __('Invalid approval decision.');
        }

        return null;
    }

    /**
     * @param mixed $approverConfig Approval config.
     * @return array<string, mixed>
     */
    private function normalizeApproverConfig(mixed $approverConfig): array
    {
        return is_array($approverConfig) ? $approverConfig : [];
    }

    /**
     * @param array<string, mixed> $approverConfig Approval config.
     * @return array<string, mixed>
     */
    private function augmentApproverConfigForResponse(
        array $approverConfig,
        ?WorkflowApproval $approval = null,
        ?Recommendation $recommendation = null,
    ): array {
        if (!$this->approvalRequiresBestowalGatheringSelection($approval, $approverConfig)) {
            return $approverConfig;
        }

        $approverConfig[self::BESTOWAL_GATHERING_REQUIRED_KEY] = true;
        if ($recommendation !== null) {
            $lookupUrl = $this->buildBestowalGatheringLookupUrl($recommendation);
            $approverConfig['bestowal_gathering_url'] = $lookupUrl;
            $approverConfig['bestowalGatheringUrl'] = $lookupUrl;
        }

        return $approverConfig;
    }

    /**
     * @param array<string, mixed> $approverConfig Approval config.
     * @return bool
     */
    private function requiresBestowalGatheringSelection(array $approverConfig): bool
    {
        return !empty($approverConfig[self::BESTOWAL_GATHERING_REQUIRED_KEY])
            || !empty($approverConfig['requiresBestowalGathering']);
    }

    /**
     * Determine whether this recommendation approval must schedule the created bestowal.
     *
     * Older pending approvals may predate requires_bestowal_gathering in node config,
     * so the award workflow slug remains the compatibility fallback.
     *
     * @param \App\Model\Entity\WorkflowApproval|null $approval Approval.
     * @param array<string, mixed> $approverConfig Approval config.
     * @return bool
     */
    private function approvalRequiresBestowalGatheringSelection(
        ?WorkflowApproval $approval,
        array $approverConfig,
    ): bool {
        if ($this->requiresBestowalGatheringSelection($approverConfig)) {
            return true;
        }

        $slug = (string)($approval?->workflow_instance?->workflow_definition?->slug ?? '');

        return in_array($slug, self::BESTOWAL_GATHERING_WORKFLOW_SLUGS, true);
    }

    /**
     * @return int|null
     */
    private function getPostedBestowalGatheringId(): ?int
    {
        $rawId = $this->request->getData('bestowal_gathering_id') ?? $this->request->getData('gathering_id');
        $gatheringId = (int)$rawId;

        return $gatheringId > 0 ? $gatheringId : null;
    }

    /**
     * @return int|null
     */
    private function getPostedRecommendationId(): ?int
    {
        $recommendationId = (int)$this->request->getData('recommendation_id');

        return $recommendationId > 0 ? $recommendationId : null;
    }

    /**
     * @param \App\Model\Entity\WorkflowApproval|null $approval Approval.
     * @param string $decision Submitted decision.
     * @param int|null $gatheringId Selected gathering ID.
     * @param \Awards\Model\Entity\Recommendation|null $recommendation Recommendation context.
     * @return string|null
     */
    private function validateBestowalGatheringSelection(
        ?WorkflowApproval $approval,
        string $decision,
        ?int $gatheringId,
        ?Recommendation $recommendation = null,
    ): ?string {
        if ($approval === null || $decision !== WorkflowApprovalResponse::DECISION_APPROVE) {
            return null;
        }

        $approverConfig = $this->normalizeApproverConfig($approval->approver_config);
        if (!$this->approvalRequiresBestowalGatheringSelection($approval, $approverConfig)) {
            return null;
        }

        if ($gatheringId === null) {
            return null;
        }

        $isSelectable = $recommendation !== null
            ? $this->isSelectableBestowalGatheringForRecommendation($recommendation, $gatheringId)
            : $this->isSelectableBestowalGathering($gatheringId);
        if (!$isSelectable) {
            return (string)__('Select a valid, future gathering for the bestowal.');
        }

        return null;
    }

    /**
     * @param int $gatheringId Gathering ID.
     * @return bool
     */
    private function isSelectableBestowalGathering(int $gatheringId): bool
    {
        return $this->fetchTable('Gatherings')->exists([
            'Gatherings.id' => $gatheringId,
            'Gatherings.deleted IS' => null,
            'Gatherings.cancelled_at IS' => null,
            'Gatherings.start_date >' => DateTime::now(),
        ]);
    }

    /**
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation context.
     * @param int $gatheringId Gathering ID.
     * @return bool
     */
    private function isSelectableBestowalGatheringForRecommendation(Recommendation $recommendation, int $gatheringId): bool
    {
        $bestowalsTable = TableRegistry::getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowalsTable->newEmptyEntity();
        $bestowal->award_id = $recommendation->award_id !== null ? (int)$recommendation->award_id : null;
        $bestowal->member_id = $recommendation->member_id !== null ? (int)$recommendation->member_id : null;
        $bestowal->set('recommendations', [$recommendation]);

        $gatheringData = (new BestowalGatheringLookupService())->getFilteredGatheringsForBestowal(
            $bestowal,
            true,
            null,
            $bestowal->award_id !== null ? (int)$bestowal->award_id : null,
        );

        return isset($gatheringData['gatherings'][$gatheringId]);
    }

    /**
     * @param \App\Model\Entity\WorkflowApproval $approval Approval entity.
     * @param int|null $recommendationId Submitted recommendation ID.
     * @return \Awards\Model\Entity\Recommendation|null
     */
    private function getRecommendationForApproval(WorkflowApproval $approval, ?int $recommendationId): ?Recommendation
    {
        $query = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->contain(['Recommendations'])
            ->where(['RecommendationApprovalRuns.workflow_instance_id' => (int)$approval->workflow_instance_id]);
        if ($recommendationId !== null) {
            $query->where(['RecommendationApprovalRuns.recommendation_id' => $recommendationId]);
        }

        $run = $query->first();
        $recommendation = $run?->recommendation ?? null;

        return $recommendation instanceof Recommendation ? $recommendation : null;
    }

    /**
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation context.
     * @return string
     */
    private function buildBestowalGatheringLookupUrl(Recommendation $recommendation): string
    {
        return Router::url([
            'plugin' => 'Awards',
            'controller' => 'Bestowals',
            'action' => 'gatheringsForBestowalAutoComplete',
            '?' => [
                'recommendation_id' => (int)$recommendation->id,
            ],
        ]);
    }

    /**
     * @param int $approvalId Approval ID.
     * @param int $memberId Member ID.
     * @return \App\Model\Entity\WorkflowApproval|null
     */
    private function getPendingApprovalForMember(int $approvalId, int $memberId): ?WorkflowApproval
    {
        $approvals = $this->getPendingApprovalsForMember($memberId, [$approvalId]);

        return $approvals[$approvalId] ?? null;
    }

    /**
     * @param int $memberId Member ID.
     * @param array<int> $approvalIds Approval IDs.
     * @return array<int, \App\Model\Entity\WorkflowApproval>
     */
    private function getPendingApprovalsForMember(int $memberId, array $approvalIds): array
    {
        if ($approvalIds === []) {
            return [];
        }

        $approvalIdSet = array_flip(array_map('intval', $approvalIds));
        $approvals = [];
        $workflowApprovalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        foreach ($workflowApprovalsTable::getPendingApprovalsForMember(
            $memberId,
            ['WorkflowInstances' => ['WorkflowDefinitions']],
        ) as $approval) {
            $approvalId = (int)$approval->id;
            if (isset($approvalIdSet[$approvalId])) {
                $approvals[$approvalId] = $approval;
            }
        }

        return $approvals;
    }

    /**
     * @param \App\Model\Entity\WorkflowApproval $approval Approval entity.
     * @param int $memberId Approver member ID.
     * @param string $decision Decision value.
     * @param string|null $comment Optional comment.
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Trigger dispatcher.
     * @return \App\Services\ServiceResult
     */
    private function recordWorkflowApprovalDecision(
        WorkflowApproval $approval,
        int $memberId,
        string $decision,
        ?string $comment,
        TriggerDispatcher $triggerDispatcher,
        ?int $bestowalGatheringId = null,
    ): ServiceResult {
        $approvalManager = new DefaultWorkflowApprovalManager();
        $result = $approvalManager->recordResponse(
            (int)$approval->id,
            $memberId,
            $decision,
            $comment,
        );

        if (!$result->isSuccess() || !$result->getData()) {
            return $result;
        }

        $data = $result->getData();
        $engine = $triggerDispatcher->getEngine();
        if (
            in_array($data['approvalStatus'] ?? '', [
                WorkflowApproval::STATUS_APPROVED,
                WorkflowApproval::STATUS_REJECTED,
            ], true)
        ) {
            $outputPort = $data['approvalStatus'] === WorkflowApproval::STATUS_APPROVED ? 'approved' : 'rejected';
            $resumeData = [
                'approval' => $data,
                'approverId' => $memberId,
                'decision' => $decision,
                'comment' => $comment,
            ];
            if ($bestowalGatheringId !== null) {
                $resumeData['bestowalGatheringId'] = $bestowalGatheringId;
            }

            $resume = $engine->resumeWorkflow(
                (int)$data['instanceId'],
                (string)$data['nodeId'],
                $outputPort,
                $resumeData,
            );
            if (!$resume->isSuccess()) {
                return new ServiceResult(
                    false,
                    $resume->getError() ?? __('The workflow could not be advanced.'),
                );
            }
        } elseif (!empty($data['needsMore'])) {
            $intermediateData = [
                'approverId' => $memberId,
                'decision' => $decision,
                'comment' => $comment,
                'nextApproverId' => $data['nextApproverId'] ?? null,
            ];
            if ($bestowalGatheringId !== null) {
                $intermediateData['bestowalGatheringId'] = $bestowalGatheringId;
            }

            $engine->fireIntermediateApprovalActions(
                (int)$data['instanceId'],
                (string)$data['nodeId'],
                $intermediateData,
            );
        }

        return $result;
    }

    /**
     * Start a fresh approval workflow from a recommendation screen.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Trigger dispatcher.
     * @param string|null $id Recommendation ID.
     * @return \Cake\Http\Response|null
     */
    public function startApprovalWorkflow(
        TriggerDispatcher $triggerDispatcher,
        ?string $id = null,
    ): ?\Cake\Http\Response {
        $this->request->allowMethod(['post']);
        if ($id === null) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }

        $recommendation = $this->Recommendations->get($id, contain: ['Awards']);
        $this->Authorization->authorize($recommendation, 'startApprovalWorkflow');

        try {
            $this->dispatchWorkflowOrFail(
                $triggerDispatcher,
                'awards-existing-recommendation-approval',
                'Awards.ExistingRecommendationApprovalRequested',
                [
                    'recommendationId' => (int)$recommendation->id,
                    'actorId' => (int)$this->request->getAttribute('identity')->getAsMember()->id,
                    'restartReason' => (string)$this->request->getData('restart_reason', 'manual_restart'),
                ],
            );
            $this->Flash->success(__('Approval workflow started.'));
        } catch (Exception $e) {
            Log::error('Recommendation approval workflow start failed: ' . $e->getMessage());
            $this->Flash->error(__('The approval workflow could not be started.'));
        }

        return $this->redirect(['action' => 'view', $recommendation->id]);
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
    public function add(
        RecommendationSubmissionService $submissionService,
        TriggerDispatcher $triggerDispatcher,
    ): ?\Cake\Http\Response {
        try {
            $user = $this->request->getAttribute('identity');
            $recommendation = $this->Recommendations->newEmptyEntity();
            $this->Authorization->authorize($recommendation);

            if ($this->request->is('post')) {
                $result = $this->dispatchRecommendationMutation(
                    $triggerDispatcher,
                    'awards-recommendation-submitted',
                    'Awards.RecommendationCreateRequested',
                    [
                        'data' => $this->request->getData(),
                        'requesterContext' => [
                            'id' => (int)$user->id,
                            'sca_name' => (string)$user->sca_name,
                            'email_address' => (string)$user->email_address,
                            'phone_number' => $user->phone_number !== null ? (string)$user->phone_number : null,
                        ],
                        'submissionMode' => 'authenticated',
                        'actorId' => (int)$user->id,
                    ],
                );

                if ($result['success']) {
                    $this->Flash->success(__('The recommendation has been saved.'));

                    $recommendationId = $this->extractRecommendationIdFromResult($result);
                    if ($recommendationId !== null) {
                        $savedRecommendation = $this->Recommendations->get($recommendationId);
                        if ($user->checkCan('view', $savedRecommendation)) {
                            return $this->redirect(['action' => 'view', $recommendationId]);
                        }
                    }

                    return $this->redirect([
                        'controller' => 'members',
                        'plugin' => null,
                        'action' => 'view',
                        $user->id,
                    ]);
                }

                if ($result['recommendation'] instanceof Recommendation) {
                    $recommendation = $result['recommendation'];
                }

                $this->Flash->error($result['error'] ?? __('The recommendation could not be saved. Please, try again.'));
                if ($result['errorCode'] === 'member_public_id_not_found') {
                    $this->response = $this->response->withStatus(400);

                    return $this->response;
                }
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

            $awards = $this->Recommendations->Awards
                ->find('active')
                ->find('list', limit: 200)
                ->all();
            $gatherings = [];

            $this->set(compact('recommendation', 'branches', 'awards', 'gatherings', 'awardsDomains', 'awardsLevels'));
            return null;
        } catch (\Exception $e) {
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
    public function submitRecommendation(
        RecommendationSubmissionService $submissionService,
        TriggerDispatcher $triggerDispatcher,
    ): ?\Cake\Http\Response {
        $this->Authorization->skipAuthorization();
        $user = $this->request->getAttribute('identity');

        if ($user !== null) {
            return $this->redirect(['action' => 'add']);
        }

        $recommendation = $this->Recommendations->newEmptyEntity();

        if ($this->request->is(['post', 'put'])) {
            try {
                $result = $this->dispatchRecommendationMutation(
                    $triggerDispatcher,
                    'awards-recommendation-submitted',
                    'Awards.RecommendationCreateRequested',
                    [
                        'data' => $this->request->getData(),
                        'submissionMode' => 'public',
                    ],
                );

                if ($result['success']) {
                    $this->Flash->success(__('The recommendation has been submitted.'));
                } else {
                    if ($result['recommendation'] instanceof Recommendation) {
                        $recommendation = $result['recommendation'];
                    }

                    $this->Flash->error($result['error'] ?? __('The recommendation could not be submitted. Please, try again.'));
                    if ($result['errorCode'] === 'member_public_id_not_found') {
                        $this->response = $this->response->withStatus(400);

                        return $this->response;
                    }
                }
            } catch (\Exception $e) {
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

        $awards = $this->Recommendations->Awards
            ->find('active')
            ->find('list', limit: 200)
            ->all();
        $gatherings = [];

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
    public function edit(
        RecommendationUpdateService $updateService,
        TriggerDispatcher $triggerDispatcher,
        RecommendationQueryService $queryService,
        ?string $id = null,
    ): ?\Cake\Http\Response {
        $id = $id ?? $this->request->getData('id');
        if (!$id || is_array($id)) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }

        try {
            $recommendation = $this->Recommendations->get($id, contain: ['Gatherings']);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'edit');

            $pageContext = $this->getPageContextUrl();

            if ($this->request->is(['patch', 'post', 'put'])) {
                $identity = $this->request->getAttribute('identity');
                $result = $this->dispatchRecommendationMutation(
                    $triggerDispatcher,
                    'awards-recommendation-updated',
                    'Awards.RecommendationUpdateRequested',
                    [
                        'recommendationId' => (int)$recommendation->id,
                        'data' => $this->request->getData(),
                        'actorId' => (int)$identity->id,
                    ],
                );

                if ($result['success']) {
                    $this->dispatchRecommendationFollowUpWorkflow(
                        $triggerDispatcher,
                        $result,
                        (int)$identity->id,
                    );
                    $stream = $this->tryRecommendationsGridTurboResponse(
                        $pageContext,
                        true,
                        null,
                        (int)$recommendation->id,
                        $queryService,
                    );
                    if ($stream !== null) {
                        return $stream;
                    }
                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->success(__('The recommendation has been saved.'));
                    }
                } else {
                    if ($result['recommendation'] instanceof Recommendation) {
                        $recommendation = $result['recommendation'];
                    }

                    $this->Flash->error($result['error'] ?? __('The recommendation could not be saved. Please, try again.'));

                    if ($result['errorCode'] === 'member_public_id_not_found') {
                        $this->response = $this->response->withStatus(400);

                        return $this->response;
                    }

                    $stream = $this->tryRecommendationsGridTurboResponse($pageContext, false, (int)$id);
                    if ($stream !== null) {
                        return $stream;
                    }
                }
            }

            if ($this->request->getData('current_page')) {
                return $this->redirect($this->request->getData('current_page'));
            }
            if ($pageContext !== null) {
                return $this->redirect($pageContext);
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
     * Delete a recommendation with transaction safety.
     *
     * Performs soft deletion of the recommendation after authorization validation.
     *
     * @param string|null $id Recommendation ID to delete
     * @return \Cake\Http\Response|null Redirects to index page after deletion
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     */
    public function delete(TriggerDispatcher $triggerDispatcher, ?string $id = null): ?\Cake\Http\Response
    {
        try {
            $this->request->allowMethod(['post', 'delete']);

            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation);
            $identity = $this->request->getAttribute('identity');
            $result = $this->dispatchRecommendationMutation(
                $triggerDispatcher,
                'awards-recommendation-deleted',
                'Awards.RecommendationDeleteRequested',
                [
                    'recommendationId' => (int)$recommendation->id,
                    'actorId' => (int)$identity->id,
                ],
            );

            if ($result['success']) {
                $this->Flash->success(__('The recommendation has been deleted.'));
            } else {
                $this->Flash->error($result['error'] ?? __('The recommendation could not be deleted. Please, try again.'));
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
     * Loads the recommendation and related lookup data so the Turbo Frame can display an in-place edit form.
     *
     * @param string|null $id Recommendation ID to load for the form
     * @return \Cake\Http\Response|null A Response when the action issues an explicit response, or null after setting view variables for rendering
     * @throws \Cake\Http\Exception\NotFoundException If the recommendation cannot be found
     * @see edit() For form submission handling
     */
    public function turboEditForm(RecommendationFormService $formService, ?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Awards.Domains',
                'CurrentApprovalRun',
            ]);

            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $viewVars = $formService->prepareEditFormData(
                $this->Recommendations,
                $recommendation,
            );
            $this->set($viewVars);
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
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
     * @param array<int> $includeGatheringIds Additional gathering IDs to include (for recommendation-selected gatherings).
     * @return array Associative array mapping gathering ID => formatted display string ("Name in Branch on YYYY-MM-DD - YYYY-MM-DD"); entries with an asterisk indicate the member is attending and sharing with crown.
     */
    public function getFilteredGatheringsForAward(
        int $awardId,
        ?int $memberId = null,
        bool $futureOnly = true,
        ?int $includeGatheringId = null,
        array $includeGatheringIds = []
    ): array {
        $includeGatheringIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            $includeGatheringIds,
            $includeGatheringId ? [$includeGatheringId] : []
        )))));

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
        $gatheringsData = [];
        if (!empty($activityIds)) {
            $query = $this->fetchTable('Gatherings')->find()
                ->contain([
                    'Branches' => function ($q) {
                        return $q->select(['id', 'name']);
                    }
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id', 'Gatherings.cancelled_at'])
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
        }

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

        // Build the response array from award-linked activities
        $gatherings = [];
        $cancelledGatheringIds = [];
        foreach ($gatheringsData as $gathering) {
            $isCancelled = $gathering->cancelled_at !== null;
            $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

            $hasAttendance = isset($attendanceMap[$gathering->id]);
            $shareWithCrown = $hasAttendance && $attendanceMap[$gathering->id];
            if ($shareWithCrown) {
                $displayName .= ' *';
            }

            if ($isCancelled) {
                $displayName = '[CANCELLED] ' . $displayName;
                $cancelledGatheringIds[] = $gathering->id;
            }

            $gatherings[$gathering->id] = $displayName;
        }

        // Always include currently scheduled gathering and recommendation-selected gatherings.
        if (!empty($includeGatheringIds)) {
            $missingIncludeIds = array_values(array_diff($includeGatheringIds, array_keys($gatherings)));
            $includedGatherings = [];

            if (!empty($missingIncludeIds)) {
                $includeGatherings = $this->fetchTable('Gatherings')->find()
                    ->contain(['Branches' => function ($q) {
                        return $q->select(['id', 'name']);
                    }])
                    ->where(['Gatherings.id IN' => $missingIncludeIds])
                    ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id', 'Gatherings.cancelled_at'])
                    ->all()
                    ->indexBy('id')
                    ->toArray();

                foreach ($includeGatheringIds as $includedId) {
                    if (!isset($includeGatherings[$includedId])) {
                        continue;
                    }
                    $includedGathering = $includeGatherings[$includedId];
                    $displayName = $includedGathering->name . ' in ' . $includedGathering->branch->name . ' on '
                        . $includedGathering->start_date->toDateString() . ' - ' . $includedGathering->end_date->toDateString();
                    $hasAttendance = isset($attendanceMap[$includedGathering->id]);
                    $shareWithCrown = $hasAttendance && $attendanceMap[$includedGathering->id];
                    if ($shareWithCrown) {
                        $displayName .= ' *';
                    }
                    if ($includedGathering->cancelled_at !== null) {
                        $displayName = '[CANCELLED] ' . $displayName;
                    }
                    $includedGatherings[$includedGathering->id] = $displayName;
                }
            }

            $gatherings = $includedGatherings + $gatherings;
            $cancelledGatheringIds = array_values(array_diff($cancelledGatheringIds, $includeGatheringIds));
        }

        return ['gatherings' => $gatherings, 'cancelledGatheringIds' => $cancelledGatheringIds];
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
            $identity = $this->Authentication->getIdentity();

            // Get member_id from query params if provided
            $memberId = $this->request->getQuery('member_id');
            $includeAttendance = $identity !== null && is_string($memberId) && trim($memberId) !== '';

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
                ->select(['Gatherings.id', 'Gatherings.name', 'Gatherings.start_date', 'Gatherings.end_date', 'Gatherings.branch_id', 'Gatherings.cancelled_at']);

            // Only filter by date if futureOnly is true
            if ($futureOnly) {
                $query = $query->where(['Gatherings.start_date >' => DateTime::now()])
                    ->orderBy(['Gatherings.start_date' => 'ASC']);
            } else {
                $query = $query->orderBy(['Gatherings.start_date' => 'DESC']);
            }

            // If there are linked activities, filter by them using an inner join
            if (!empty($activityIds)) {
                $query = $query->innerJoinWith('GatheringActivities', function ($q) use ($activityIds) {
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
            if ($includeAttendance) {
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
                $isCancelled = $gathering->cancelled_at !== null;
                $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

                $hasAttendance = isset($attendanceMap[$gathering->id]);
                $shareWithCrown = $hasAttendance && $attendanceMap[$gathering->id];

                // Add indicator if member is attending and sharing with crown
                if ($shareWithCrown) {
                    $displayName .= ' *';
                }

                // Add cancelled indicator
                if ($isCancelled) {
                    $displayName = '[CANCELLED] ' . $displayName;
                }

                $gatherings[] = [
                    'id' => $gathering->id,
                    'name' => $gathering->name,
                    'display' => $displayName,
                    'has_attendance' => $hasAttendance,
                    'share_with_crown' => $shareWithCrown,
                    'cancelled' => $isCancelled
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
     * Return gathering autocomplete options for recommendation edit/quick-edit forms.
     *
     * Returns Ajax HTML list items consumed by the shared auto-complete controller.
     *
     * @param string|null $awardId Award ID for gathering activity filtering.
     * @return void
     */
    public function gatheringsAutoComplete(?string $awardId = null): void
    {
        $this->request->allowMethod(['get']);
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'index');
        $this->viewBuilder()->setClassName('Ajax');
        $this->viewBuilder()->setTemplate('gatherings_auto_complete');

        $q = trim((string)$this->request->getQuery('q', ''));
        $status = (string)$this->request->getQuery('status', '');
        $futureOnly = ($status !== 'Given');
        $selectedId = $this->request->getQuery('selected_id');
        $selectedId = is_numeric((string)$selectedId) ? (int)$selectedId : null;
        $recommendationId = $this->request->getQuery('recommendation_id');
        $recommendationId = is_numeric((string)$recommendationId) ? (int)$recommendationId : null;

        $gatherings = [];
        $cancelledGatheringIds = [];
        $includeGatheringIds = [];

        try {
            $memberId = null;
            $memberPublicId = (string)$this->request->getQuery('member_id', '');
            if ($memberPublicId !== '') {
                $member = $this->fetchTable('Members')->find('byPublicId', publicId: $memberPublicId)->first();
                if ($member) {
                    $memberId = $member->id;
                }
            }

            if ($recommendationId) {
                $recommendationQuery = $this->Recommendations->find()
                    ->select(['Recommendations.id', 'Recommendations.gathering_id'])
                    ->contain(['Gatherings' => function ($q) {
                        return $q->select(['Gatherings.id']);
                    }]);
                $recommendation = $this->Authorization->applyScope($recommendationQuery, 'index')
                    ->where(['Recommendations.id' => $recommendationId])
                    ->first();
                if ($recommendation) {
                    if (!empty($recommendation->gathering_id)) {
                        $includeGatheringIds[] = (int)$recommendation->gathering_id;
                    }
                    foreach (($recommendation->gatherings ?? []) as $selectedGathering) {
                        $includeGatheringIds[] = (int)$selectedGathering->id;
                    }
                }
            }

            if ($awardId !== null && ctype_digit((string)$awardId)) {
                $gatheringData = $this->getFilteredGatheringsForAward(
                    (int)$awardId,
                    $memberId,
                    $futureOnly,
                    $selectedId,
                    $includeGatheringIds
                );
                $gatherings = $gatheringData['gatherings'] ?? [];
                $cancelledGatheringIds = $gatheringData['cancelledGatheringIds'] ?? [];
            }

            $stickyGatheringIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
                $includeGatheringIds,
                $selectedId ? [$selectedId] : []
            )))));
            $stickyLookup = array_fill_keys($stickyGatheringIds, true);

            if ($q === '') {
                if (!empty($stickyGatheringIds)) {
                    $gatherings = array_intersect_key($gatherings, array_fill_keys($stickyGatheringIds, true));
                }
            } else {
                $gatherings = array_filter($gatherings, function ($display, $id) use ($q, $stickyLookup) {
                    return isset($stickyLookup[(int)$id]) || mb_stripos((string)$display, $q) !== false;
                }, ARRAY_FILTER_USE_BOTH);
            }
        } catch (\Exception $e) {
            Log::error('Error in gatheringsAutoComplete: ' . $e->getMessage());
            $gatherings = [];
            $cancelledGatheringIds = [];
        }

        $this->set(compact('gatherings', 'q', 'cancelledGatheringIds', 'selectedId'));
    }

    /**
     * Group selected recommendations together.
     *
     * Validates that all selected recommendations share the same member_id
     * or have null member_id. If one selected rec is already a group head,
     * others join it. Otherwise, the lowest-ID rec becomes the head.
     * Children transition to "Linked" state.
     *
     * @return \Cake\Http\Response|null
     */
    public function groupRecommendations(
        RecommendationGroupingService $groupingService,
        TriggerDispatcher $triggerDispatcher,
    ): ?\Cake\Http\Response {
        $this->request->allowMethod(['post']);
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'group');

        $pageContext = $this->getPageContextUrl();
        $ids = $this->request->getData('recommendation_ids');
        if (!is_array($ids) || count($ids) < 2) {
            $this->Flash->error(__('At least 2 recommendations are required to group.'));

            return $this->recommendationsGridRefreshResponse($pageContext)
                ?? $this->redirect($pageContext ?: ['action' => 'index']);
        }

        $ids = array_map('intval', $ids);
        $identity = $this->request->getAttribute('identity');
        $result = $this->dispatchRecommendationMutation(
            $triggerDispatcher,
            'awards-recommendations-group',
            'Awards.RecommendationsGroupRequested',
            [
                'recommendationIds' => $ids,
                'actorId' => (int)$identity->id,
            ],
        );

        if ($result['success']) {
            $this->Flash->success(__('Recommendations have been grouped.'));
        } else {
            $this->Flash->error($result['error'] ?? __('An error occurred while grouping recommendations.'));
        }

        return $this->recommendationsGridRefreshResponse($pageContext)
            ?? $this->redirect($pageContext ?: ['action' => 'index']);
    }

    /**
     * Ungroup all children from a group head.
     *
     * Restores each child to its pre-linked state using the state change log.
     *
     * @return \Cake\Http\Response|null
     */
    public function ungroupRecommendations(
        RecommendationGroupingService $groupingService,
        TriggerDispatcher $triggerDispatcher,
    ): ?\Cake\Http\Response {
        $this->request->allowMethod(['post']);
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'group');

        $headId = (int)$this->request->getData('recommendation_id');
        $identity = $this->request->getAttribute('identity');
        $result = $this->dispatchRecommendationMutation(
            $triggerDispatcher,
            'awards-recommendations-ungroup',
            'Awards.RecommendationsUngroupRequested',
            [
                'recommendationId' => $headId,
                'actorId' => (int)$identity->id,
            ],
        );

        if ($result['success']) {
            $this->Flash->success(__('Recommendations have been ungrouped.'));
        } else {
            $this->Flash->error($result['error'] ?? __('An error occurred while ungrouping recommendations.'));
        }

        return $this->redirect(['action' => 'view', $headId]);
    }

    /**
     * Remove a single child from its group.
     *
     * If only one child remains after removal, auto-ungroups entirely.
     *
     * @return \Cake\Http\Response|null
     */
    public function removeFromGroup(
        RecommendationGroupingService $groupingService,
        TriggerDispatcher $triggerDispatcher,
    ): ?\Cake\Http\Response {
        $this->request->allowMethod(['post']);
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'group');

        $childId = (int)$this->request->getData('recommendation_id');
        $identity = $this->request->getAttribute('identity');
        $result = $this->dispatchRecommendationMutation(
            $triggerDispatcher,
            'awards-recommendation-remove-from-group',
            'Awards.RecommendationRemoveFromGroupRequested',
            [
                'recommendationId' => $childId,
                'actorId' => (int)$identity->id,
            ],
        );

        if ($result['success']) {
            $this->Flash->success(__('Recommendation removed from group.'));
        } else {
            $this->Flash->error($result['error'] ?? __('An error occurred while removing the recommendation from the group.'));
        }

        return $this->redirect([
            'action' => 'view',
            $result['data']['formerHeadId'] ?? $childId,
        ]);
    }

    /**
     * AJAX endpoint: return grouped children HTML for a recommendation sub-row.
     *
     * @param int $headId The group head recommendation ID
     * @return void
     */
    public function groupChildren(int $headId): void
    {
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'index');

        $children = $this->Recommendations->find()
            ->where(['Recommendations.recommendation_group_id' => $headId])
            ->contain([
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation']);
                },
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ])
            ->orderBy(['Recommendations.created' => 'asc'])
            ->all()
            ->toArray();

        $user = $this->request->getAttribute('identity');
        $canEdit = $user->checkCan('edit', $emptyRecommendation);

        $this->set(compact('children', 'headId', 'canEdit'));
        $this->viewBuilder()->disableAutoLayout();
        $this->viewBuilder()->setTemplate('group_children_subrow');
    }

    /**
     * Dispatch a workflow-backed recommendation mutation and normalize its result.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow dispatcher.
     * @param string $slug Workflow definition slug.
     * @param string $triggerEvent Workflow trigger event name.
     * @param array<string, mixed> $context Workflow context payload.
     * @return array{success: bool, data: array<string, mixed>, error: ?string, errorCode: ?string, errors: array, recommendation: ?\Awards\Model\Entity\Recommendation}
     */
    private function dispatchRecommendationMutation(
        TriggerDispatcher $triggerDispatcher,
        string $slug,
        string $triggerEvent,
        array $context,
    ): array {
        try {
            return $this->normalizeRecommendationMutationResult(
                $this->dispatchWorkflowOrFail($triggerDispatcher, $slug, $triggerEvent, $context),
            );
        } catch (\Throwable $e) {
            Log::error("Recommendation workflow dispatch failed for {$slug}: " . $e->getMessage());

            return [
                'success' => false,
                'data' => [],
                'error' => 'The recommendation workflow is not currently available.',
                'errorCode' => 'workflow_unavailable',
                'errors' => [],
                'recommendation' => null,
            ];
        }
    }

    /**
     * Parse comma or whitespace separated numeric IDs.
     *
     * @return array<int>
     */
    private function parseIdList(string $value): array
    {
        $ids = [];
        foreach (preg_split('/[,\s]+/', $value) ?: [] as $id) {
            if ($id !== '' && ctype_digit($id)) {
                $ids[(int)$id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Parse member IDs from normal member lookup values.
     *
     * The member autocomplete returns public_id values; numeric IDs are still
     * accepted for callers that do not use the lookup control.
     *
     * @return array<int>
     */
    private function parseMemberIdList(string $value): array
    {
        $ids = [];
        $publicIds = [];
        foreach (preg_split('/[,\s]+/', $value) ?: [] as $id) {
            if ($id === '') {
                continue;
            }
            if (ctype_digit($id)) {
                $ids[(int)$id] = true;
            } else {
                $publicIds[$id] = true;
            }
        }

        if ($publicIds !== []) {
            $members = $this->fetchTable('Members')->find()
                ->select(['id'])
                ->where(['public_id IN' => array_keys($publicIds)])
                ->disableHydration()
                ->all();
            foreach ($members as $member) {
                $ids[(int)$member['id']] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Normalize legacy service results and workflow-dispatch results to one controller shape.
     *
     * @param mixed $result Shared service result or workflow dispatch output.
     * @return array{success: bool, data: array<string, mixed>, error: ?string, errorCode: ?string, errors: array, recommendation: ?\Awards\Model\Entity\Recommendation}
     */
    private function normalizeRecommendationMutationResult(mixed $result): array
    {
        if (is_array($result) && $this->isWorkflowDispatchResult($result)) {
            return $this->normalizeWorkflowDispatchResult($result);
        }

        if (is_array($result) && array_key_exists('success', $result)) {
            $data = [];
            if (isset($result['output']) && is_array($result['output'])) {
                $data = $result['output'];
            } elseif (isset($result['data']) && is_array($result['data'])) {
                $data = $result['data'];
            }

            $recommendation = $result['recommendation'] ?? null;
            if (!$recommendation instanceof Recommendation) {
                $recommendation = null;
            }

            $errors = $result['errors'] ?? ($data['errors'] ?? []);

            return [
                'success' => (bool)$result['success'],
                'data' => $data,
                'error' => $result['error'] ?? $result['message'] ?? null,
                'errorCode' => $result['errorCode'] ?? ($data['errorCode'] ?? null),
                'errors' => is_array($errors) ? $errors : [],
                'recommendation' => $recommendation,
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'error' => 'Workflow did not return a result.',
            'errorCode' => null,
            'errors' => [],
            'recommendation' => null,
        ];
    }

    /**
     * Determine whether the result is a list of workflow engine service results.
     *
     * @param array<int, mixed> $result Result payload to inspect.
     * @return bool
     */
    private function isWorkflowDispatchResult(array $result): bool
    {
        if ($result === []) {
            return false;
        }

        foreach ($result as $item) {
            if (!$item instanceof ServiceResult) {
                return false;
            }
        }

        return true;
    }

    /**
     * Collapse workflow dispatch results down to the workflow end-node result payload.
     *
     * @param array<int, \App\Services\ServiceResult> $results Workflow dispatch results.
     * @return array{success: bool, data: array<string, mixed>, error: ?string, errorCode: ?string, errors: array, recommendation: ?\Awards\Model\Entity\Recommendation}
     */
    private function normalizeWorkflowDispatchResult(array $results): array
    {
        foreach ($results as $dispatchResult) {
            if (!$dispatchResult->isSuccess()) {
                return [
                    'success' => false,
                    'data' => [],
                    'error' => $dispatchResult->getError(),
                    'errorCode' => null,
                    'errors' => [],
                    'recommendation' => null,
                ];
            }

            $data = $dispatchResult->getData();
            $workflowResult = is_array($data) ? ($data['workflowResult'] ?? null) : null;
            if (is_array($workflowResult)) {
                return $this->normalizeRecommendationMutationResult($workflowResult);
            }
        }

        return [
            'success' => false,
            'data' => [],
            'error' => 'Workflow did not return a result.',
            'errorCode' => null,
            'errors' => [],
            'recommendation' => null,
        ];
    }

    /**
     * Extract the saved recommendation ID from a normalized mutation result.
     *
     * @param array{data?: array<string, mixed>, recommendation?: mixed} $result Normalized mutation result.
     * @return int|null
     */
    private function extractRecommendationIdFromResult(array $result): ?int
    {
        $recommendationId = $result['data']['recommendationId'] ?? null;
        if (is_numeric($recommendationId)) {
            return (int)$recommendationId;
        }

        $recommendation = $result['recommendation'] ?? null;
        if ($recommendation instanceof Recommendation && $recommendation->id !== null) {
            return (int)$recommendation->id;
        }

        return null;
    }

    /**
     * Dispatch a post-commit workflow event returned by the recommendation mutation.
     *
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow trigger dispatcher.
     * @param array<string, mixed> $result Normalized recommendation mutation result.
     * @param int $actorId Actor ID.
     * @return void
     */
    private function dispatchRecommendationFollowUpWorkflow(
        TriggerDispatcher $triggerDispatcher,
        array $result,
        int $actorId,
    ): void {
        $eventName = $result['data']['eventName'] ?? null;
        if (!is_string($eventName) || $eventName === '') {
            return;
        }

        $eventPayload = $result['data']['eventPayload'] ?? $result['data'];
        if (!is_array($eventPayload)) {
            $eventPayload = [];
        }
        $eventPayload['actorId'] = $actorId;

        $this->dispatchWorkflowEvent($triggerDispatcher, $eventName, $eventPayload);
    }

    /**
     * Add computed display fields used by the recommendations Dataverse grid.
     *
     * @param iterable<\Awards\Model\Entity\Recommendation> $recommendations
     */
    private function enrichRecommendationsForGrid(iterable $recommendations, array $visibleColumns = []): void
    {
        $includeGroupCount = $this->isRecommendationColumnVisible('group_children_count', $visibleColumns);
        $includeOpLinks = $this->isRecommendationColumnVisible('op_links', $visibleColumns);
        $includeGatherings = $this->isRecommendationColumnVisible('gatherings', $visibleColumns);
        $includeNotes = $this->isRecommendationColumnVisible('notes', $visibleColumns);
        $includeReason = $this->isRecommendationColumnVisible('reason', $visibleColumns);

        $recIds = [];
        foreach ($recommendations as $recommendation) {
            $recIds[] = $recommendation->id;
        }

        $groupCounts = [];
        if ($includeGroupCount && $recIds !== []) {
            $countQuery = $this->Recommendations->find()
                ->select([
                    'recommendation_group_id',
                    'child_count' => $this->Recommendations->find()->func()->count('*'),
                ])
                ->where(['recommendation_group_id IN' => $recIds])
                ->groupBy(['recommendation_group_id'])
                ->disableHydration()
                ->all();
            foreach ($countQuery as $row) {
                $groupCounts[(int)$row['recommendation_group_id']] = (int)$row['child_count'];
            }
        }

        $memberAttendanceGatherings = $includeGatherings
            ? $this->getMemberAttendanceGatherings($recommendations)
            : [];
        $this->decoratePendingWorkflowApprovals($recommendations);
        $identity = $this->request->getAttribute('identity');

        foreach ($recommendations as $recommendation) {
            $recommendation->bestowal_linked = !empty($recommendation->bestowal_id);
            $recommendation->bestowal_viewable = $this->canViewLinkedBestowal($recommendation, $identity);

            if ($includeGroupCount) {
                $recommendation->group_children_count = isset($groupCounts[$recommendation->id])
                    ? $groupCounts[$recommendation->id] + 1
                    : 0;
            }
            if ($includeOpLinks) {
                $recommendation->op_links = $this->buildOpLinksHtml($recommendation);
            }
            if ($includeGatherings) {
                $attendanceGatherings = $memberAttendanceGatherings[$recommendation->member_id] ?? [];
                $recommendation->gatherings = $this->buildGatheringsHtml($recommendation, $attendanceGatherings);
            }
            if ($includeNotes) {
                $recommendation->notes = $this->buildNotesHtml($recommendation);
            }
            if ($includeReason) {
                $recommendation->reason = $this->buildReasonHtml($recommendation);
            }
        }
    }

    /**
     * Check whether the current identity can view the linked bestowal for a recommendation row.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation row.
     * @param mixed $identity Current identity.
     * @return bool
     */
    private function canViewLinkedBestowal(Recommendation $recommendation, mixed $identity): bool
    {
        if (empty($recommendation->bestowal_id) || empty($recommendation->bestowal)) {
            return false;
        }

        if ($identity === null || !method_exists($identity, 'checkCan')) {
            return false;
        }

        return $identity->checkCan('view', $recommendation->bestowal);
    }

    /**
     * Add current-user pending approval metadata for recommendation grid actions.
     *
     * @param iterable<\Awards\Model\Entity\Recommendation> $recommendations Recommendations to decorate.
     * @return void
     */
    private function decoratePendingWorkflowApprovals(iterable $recommendations): void
    {
        $identity = $this->request->getAttribute('identity');
        $member = $identity?->getAsMember();
        if (!$member instanceof Member) {
            return;
        }

        $workflowInstanceIds = [];
        foreach ($recommendations as $recommendation) {
            $recommendation->pending_approval_id = null;
            $recommendation->pending_approval_approver_config = [];
            $recommendation->pending_approval_required_count = 0;
            $recommendation->pending_approval_approved_count = 0;
            $recommendation->can_workflow_decide = false;

            $run = $recommendation->current_approval_run ?? null;
            if ($run && !empty($run->workflow_instance_id)) {
                $workflowInstanceIds[] = (int)$run->workflow_instance_id;
            }
        }

        $workflowInstanceIds = array_values(array_unique($workflowInstanceIds));
        if ($workflowInstanceIds === []) {
            return;
        }

        $approvalsByInstance = [];
        $workflowApprovalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $approvals = $workflowApprovalsTable::getPendingApprovalsForMember(
            (int)$member->id,
            ['WorkflowInstances' => ['WorkflowDefinitions']],
            $workflowInstanceIds,
        );
        foreach ($approvals as $approval) {
            $approvalsByInstance[(int)$approval->workflow_instance_id] = $approval;
        }

        foreach ($recommendations as $recommendation) {
            $run = $recommendation->current_approval_run ?? null;
            $workflowInstanceId = $run && !empty($run->workflow_instance_id)
                ? (int)$run->workflow_instance_id
                : null;
            if ($workflowInstanceId === null || !isset($approvalsByInstance[$workflowInstanceId])) {
                continue;
            }

            $approval = $approvalsByInstance[$workflowInstanceId];
            $recommendation->pending_approval_id = (int)$approval->id;
            $recommendation->pending_approval_approver_config = $this->augmentApproverConfigForResponse(
                $this->normalizeApproverConfig($approval->approver_config),
                $approval,
                $recommendation,
            );
            $recommendation->pending_approval_required_count = (int)($approval->required_count ?? 1);
            $recommendation->pending_approval_approved_count = (int)($approval->approved_count ?? 0);
            $recommendation->can_workflow_decide = true;
        }
    }

    /**
     * Determine whether a recommendation column should be treated as visible.
     *
     * @param string $columnKey
     * @param array<int,string> $visibleColumns
     * @return bool
     */
    private function isRecommendationColumnVisible(string $columnKey, array $visibleColumns = []): bool
    {
        if ($visibleColumns !== []) {
            return in_array($columnKey, $visibleColumns, true);
        }

        $column = RecommendationsGridColumns::getColumns()[$columnKey] ?? null;

        return (bool)($column['defaultVisible'] ?? false);
    }

    /**
     * @param array<string, array<string, mixed>> $rowActions
     * @param array<string, mixed> $gridResult
     * @return array<string, array<string, mixed>>
     */
    private function filterRecommendationRowActionsForGridResult(array $rowActions, array $gridResult): array
    {
        $currentViewId = $gridResult['gridState']['view']['currentId'] ?? null;
        if ($currentViewId !== 'sys-recs-archived') {
            return $rowActions;
        }

        return array_intersect_key($rowActions, array_flip(['bestowal', 'view']));
    }

    /**
     * @param array<string, mixed> $gridResult
     * @return array<string, mixed>
     */
    private function filterRecommendationGridActionsForResult(array $gridResult): array
    {
        $currentViewId = $gridResult['gridState']['view']['currentId'] ?? null;
        if ($currentViewId !== 'sys-recs-archived') {
            return $gridResult;
        }

        $gridResult['gridState']['config']['bulkActions'] = [];

        return $gridResult;
    }

    /**
     * Determine whether a column is requested for the current grid request.
     *
     * @param string $columnKey
     * @return bool
     */
    private function shouldIncludeRecommendationColumn(string $columnKey, ?array $visibleColumns = null): bool
    {
        if ($visibleColumns !== null) {
            return $this->isRecommendationColumnVisible($columnKey, $visibleColumns);
        }

        $columnsParam = (string)$this->request->getQuery('columns', '');
        if ($columnsParam !== '') {
            $requested = array_filter(explode(',', $columnsParam));

            return in_array($columnKey, $requested, true);
        }

        return $this->isRecommendationColumnVisible($columnKey);
    }

    /**
     * Resolve visible columns early so recommendation queries can skip hidden display-only associations.
     *
     * @param string $gridKey Grid identifier.
     * @param array<string,array<string,mixed>> $systemViews System view definitions.
     * @param string $defaultSystemView Default system view key.
     * @return array<int,string>|null Null means all display data is required.
     */
    private function resolveRecommendationVisibleColumns(
        string $gridKey,
        array $systemViews,
        string $defaultSystemView,
    ): ?array {
        if ($this->isCsvExportRequest()) {
            return null;
        }

        $columnsParam = (string)$this->request->getQuery('columns', '');
        if ($columnsParam !== '') {
            return $this->appendActiveRecommendationColumns(array_values(array_filter(explode(',', $columnsParam))));
        }

        if ($this->request->getQuery('ignore_default')) {
            return $this->appendActiveRecommendationColumns($this->defaultRecommendationVisibleColumns());
        }

        $currentMember = $this->request->getAttribute('identity');
        $viewId = $this->request->getQuery('view_id');
        $requestedViewId = is_string($viewId) && $viewId !== '' ? $viewId : null;
        if ($requestedViewId === null && $currentMember instanceof Member) {
            $gridViewsTable = $this->fetchTable('GridViews');
            $requestedViewId = (new GridViewService(
                $gridViewsTable instanceof GridViewsTable ? $gridViewsTable : null,
            ))
                ->getUserPreferenceViewId($gridKey, $currentMember);
        }
        $requestedViewId ??= $defaultSystemView;

        if (is_string($requestedViewId) && isset($systemViews[$requestedViewId])) {
            return $this->appendActiveRecommendationColumns(
                $this->normalizeRecommendationColumns($systemViews[$requestedViewId]['config']['columns'] ?? []),
            );
        }

        if (is_numeric($requestedViewId)) {
            $customColumns = $this->loadRecommendationGridViewColumns(
                $gridKey,
                (int)$requestedViewId,
                $currentMember instanceof Member ? (int)$currentMember->id : null,
            );
            if ($customColumns !== null) {
                return $this->appendActiveRecommendationColumns($customColumns);
            }
        }

        if (isset($systemViews[$defaultSystemView])) {
            return $this->appendActiveRecommendationColumns(
                $this->normalizeRecommendationColumns($systemViews[$defaultSystemView]['config']['columns'] ?? []),
            );
        }

        return $this->appendActiveRecommendationColumns($this->defaultRecommendationVisibleColumns());
    }

    /**
     * @param string $gridKey Grid identifier.
     * @param int $viewId Grid view id.
     * @param int|null $memberId Current member id.
     * @return array<int,string>|null
     */
    private function loadRecommendationGridViewColumns(string $gridKey, int $viewId, ?int $memberId): ?array
    {
        $query = $this->fetchTable('GridViews')
            ->find()
            ->where([
                'id' => $viewId,
                'grid_key' => $gridKey,
            ]);

        if ($memberId !== null) {
            $query->where([
                'OR' => [
                    'member_id' => $memberId,
                    [
                        'is_system_default' => true,
                        'member_id IS' => null,
                    ],
                ],
            ]);
        } else {
            $query->where([
                'is_system_default' => true,
                'member_id IS' => null,
            ]);
        }

        $view = $query->first();
        if ($view === null || !method_exists($view, 'getConfigArray')) {
            return null;
        }

        $config = $view->getConfigArray();
        if (empty($config['columns']) || !is_array($config['columns'])) {
            return null;
        }

        return $this->normalizeRecommendationColumns($config['columns']);
    }

    /**
     * @param array<int,mixed> $columns
     * @return array<int,string>
     */
    private function normalizeRecommendationColumns(array $columns): array
    {
        $firstColumn = reset($columns);
        if (is_string($firstColumn)) {
            return array_values(array_filter($columns, 'is_string'));
        }

        return GridViewConfig::extractVisibleColumns(
            ['columns' => $columns],
            RecommendationsGridColumns::getColumns(),
        );
    }

    /**
     * @return array<int,string>
     */
    private function defaultRecommendationVisibleColumns(): array
    {
        $columns = [];
        foreach (RecommendationsGridColumns::getColumns() as $key => $column) {
            if (!empty($column['defaultVisible'])) {
                $columns[] = $key;
            }
        }

        return $columns;
    }

    /**
     * @param array<int,string> $columns
     * @return array<int,string>
     */
    private function appendActiveRecommendationColumns(array $columns): array
    {
        $activeColumns = $columns;
        $sortColumn = $this->request->getQuery('sort');
        if (is_string($sortColumn) && $sortColumn !== '') {
            $activeColumns[] = $sortColumn;
        }

        $filters = $this->request->getQuery('filter', []);
        if (is_array($filters)) {
            foreach (array_keys($filters) as $filterColumn) {
                if (is_string($filterColumn) && $filterColumn !== '') {
                    $activeColumns[] = $filterColumn;
                }
            }
        }

        return array_values(array_unique($activeColumns));
    }

    /**
     * Tab query param from page context URL (detail pages).
     */
    private function pageContextQueryTab(?string $pageContextUrl): ?string
    {
        if ($pageContextUrl === null) {
            return null;
        }

        $parsed = parse_url($pageContextUrl);
        if (empty($parsed['query'])) {
            return null;
        }

        $params = [];
        parse_str($parsed['query'], $params);

        $tab = $params['tab'] ?? null;

        return is_string($tab) && $tab !== '' ? $tab : null;
    }

    /**
     * Match page context to a recommendation grid row-sync context.
     *
     * @return array{contextKey: string, tableFrameId: string, memberId?: int, gatheringId?: int}|null
     */
    private function resolveRecommendationGridSyncContext(?string $pageContextUrl): ?array
    {
        if ($pageContextUrl === null) {
            return null;
        }

        $path = parse_url($pageContextUrl, PHP_URL_PATH) ?? $pageContextUrl;
        $tab = $this->pageContextQueryTab($pageContextUrl);

        if ($this->matchesGridIndexPath($pageContextUrl, '#/awards/recommendations/?$#')) {
            return [
                'contextKey' => 'main',
                'tableFrameId' => 'recommendations-grid-table',
            ];
        }

        if (preg_match('#/members/profile/?$#', $path)) {
            $memberId = (int)$this->request->getAttribute('identity')->id;
            if ($tab === null || $tab === 'member-submitted-recs') {
                return [
                    'contextKey' => 'memberSubmitted',
                    'tableFrameId' => 'member-submitted-recs-grid-' . $memberId . '-table',
                    'memberId' => $memberId,
                ];
            }

            return null;
        }

        if (preg_match('#/members/view/(\d+)/?$#', $path, $matches)) {
            $memberId = (int)$matches[1];
            if ($tab === 'member-submitted-recs') {
                return [
                    'contextKey' => 'memberSubmitted',
                    'tableFrameId' => 'member-submitted-recs-grid-' . $memberId . '-table',
                    'memberId' => $memberId,
                ];
            }
            if ($tab === 'recs-for-member') {
                return [
                    'contextKey' => 'recsForMember',
                    'tableFrameId' => 'recs-for-member-grid-' . $memberId . '-table',
                    'memberId' => $memberId,
                ];
            }

            return null;
        }

        return null;
    }

    /**
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations
     */
    private function enrichRecommendationsForGridContext(array $recommendations, string $contextKey): void
    {
        if ($contextKey === 'main') {
            $this->enrichRecommendationsForGrid($recommendations);

            return;
        }

        foreach ($recommendations as $recommendation) {
            $recommendation->gatherings = $this->buildGatheringsHtml($recommendation);
            $recommendation->reason = $this->buildReasonHtml($recommendation);
        }
    }

    /**
     * Resolve targeted row sync after a single recommendation save.
     *
     * @return array{action: string, rowDomId: string, rowHtml?: string}|null Null → full table refresh
     */
    private function resolveRecommendationGridRowSync(
        int $recommendationId,
        ?string $pageContextUrl,
        RecommendationQueryService $queryService,
    ): ?array {
        $syncContext = $this->resolveRecommendationGridSyncContext($pageContextUrl);
        if ($syncContext === null) {
            return null;
        }

        $tableFrameId = $syncContext['tableFrameId'];
        $rowDomId = GridRowDomId::fromTableFrameId($tableFrameId, $recommendationId);

        return $this->withPageContextQuery($pageContextUrl, function () use (
            $recommendationId,
            $rowDomId,
            $queryService,
            $tableFrameId,
            $syncContext,
        ): ?array {
            $emptyRecommendation = $this->Recommendations->newEmptyEntity();
            $user = $this->request->getAttribute('identity');
            $contextKey = $syncContext['contextKey'];

            if ($contextKey === 'main') {
                $canViewHidden = $user->checkCan('ViewHidden', $emptyRecommendation);
                $canEdit = $user->checkCan('edit', $emptyRecommendation);
                $built = $queryService->buildMainGridQuery($this->Recommendations, $canEdit);
                $baseQuery = $built['query'];
                $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
                $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
            } elseif ($contextKey === 'memberSubmitted') {
                $memberId = $syncContext['memberId'];
                $emptyRecommendation->requester_id = $memberId;
                $this->Authorization->authorize($emptyRecommendation, 'ViewSubmittedByMember');
                $isOwnSubmissions = ($user->id === $memberId);
                $canViewHidden = $isOwnSubmissions || $user->checkCan('ViewHidden', $emptyRecommendation);
                $built = $queryService->buildMemberSubmittedQuery($this->Recommendations, $memberId);
                $baseQuery = $built['query'];
                $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
            } elseif ($contextKey === 'recsForMember') {
                $memberId = $syncContext['memberId'];
                $this->Authorization->authorize($emptyRecommendation, 'ViewSubmittedForMember');
                $canViewHidden = true;
                $built = $queryService->buildRecsForMemberQuery($this->Recommendations, $memberId);
                $baseQuery = $built['query'];
                $baseQuery = $this->Authorization->applyScope($baseQuery, 'index');
                $baseQuery = $queryService->applyHiddenStateVisibility($baseQuery, $canViewHidden);
            }

            $baseQuery = $baseQuery->where(['Recommendations.id' => $recommendationId]);
            $built['gridOptions']['baseQuery'] = $baseQuery;

            $result = $this->processDataverseGrid($built['gridOptions']);
            if ($contextKey === 'main') {
                $canViewHidden = $canViewHidden ?? $user->checkCan('ViewHidden', $emptyRecommendation);
                $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);
            } elseif ($contextKey === 'memberSubmitted') {
                $result = $this->applyStateFilterOptionsToGridResult($result, $canViewHidden);
            } else {
                $result = $this->applyStateFilterOptionsToGridResult($result, true);
            }

            $gridData = $result['data'];
            if (is_array($gridData)) {
                $recommendations = $gridData;
            } elseif ($gridData instanceof \Traversable) {
                $recommendations = iterator_to_array($gridData, false);
            } else {
                $recommendations = [];
            }
            if ($recommendations === []) {
                return [
                    'action' => 'remove',
                    'rowDomId' => $rowDomId,
                ];
            }

            $this->enrichRecommendationsForGridContext($recommendations, $contextKey);
            $recommendation = $recommendations[0];
            $rowActions = $contextKey === 'main'
                ? $this->filterRecommendationRowActionsForGridResult(
                    RecommendationsGridColumns::getRowActions(),
                    $result,
                )
                : [];
            $gridState = $result['gridState'];
            $enableColumnPicker = $gridState['config']['enableColumnPicker'] ?? true;
            $visibleColumns = $gridState['columns']['visible'];
            if (!is_array($visibleColumns)) {
                $visibleColumns = array_values($visibleColumns);
            }

            $rowHtml = $this->renderDataverseTableRowElement([
                'row' => $recommendation,
                'columns' => $gridState['columns']['all'],
                'visibleColumns' => $visibleColumns,
                'controllerName' => 'grid-view',
                'primaryKey' => $gridState['config']['primaryKey'],
                'gridKey' => $gridState['config']['gridKey'],
                'rowActions' => $rowActions,
                'user' => $user,
                'enableBulkSelection' => $gridState['config']['enableBulkSelection'] ?? false,
                'bulkSelectionDataFields' => $gridState['config']['bulkSelectionDataFields'] ?? [],
                'bulkSelectionDisabledField' => $gridState['config']['bulkSelectionDisabledField'] ?? null,
                'rowDomIdPrefix' => preg_replace('/-table$/', '', $tableFrameId),
                'showActionsColumn' => $enableColumnPicker || $rowActions !== [],
            ]);

            return [
                'action' => 'replace',
                'rowDomId' => $rowDomId,
                'rowHtml' => $rowHtml,
            ];
        });
    }

    /**
     * Turbo-stream response for grid-origin recommendation saves.
     */
    private function tryRecommendationsGridTurboResponse(
        ?string $pageContext,
        bool $success,
        ?int $reloadQuickEditId = null,
        ?int $updatedRecommendationId = null,
        ?RecommendationQueryService $queryService = null,
    ): ?\Cake\Http\Response {
        if (!$this->wantsTurboStreamRequest() || $pageContext === null) {
            return null;
        }

        $syncContext = $this->resolveRecommendationGridSyncContext($pageContext);
        if (!$this->isGridOriginRequest($pageContext) && $syncContext === null) {
            return null;
        }

        $gridRoute = ['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'gridData'];

        if ($success) {
            $this->Flash->success(__('The recommendation has been saved.'));

            if ($updatedRecommendationId !== null) {
                $queryService ??= new RecommendationQueryService();
                $sync = $this->resolveRecommendationGridRowSync(
                    $updatedRecommendationId,
                    $pageContext,
                    $queryService,
                );
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

            return $this->renderTurboCloseModal('recommendations-grid-table', $gridRoute, $pageContext);
        }

        if ($reloadQuickEditId !== null) {
            $frameSrc = Router::url([
                'plugin' => 'Awards',
                'controller' => 'Recommendations',
                'action' => 'turboEditForm',
                $reloadQuickEditId,
            ]);

            return $this->renderTurboReloadFrame('editRecommendation', $frameSrc)->withStatus(422);
        }

        return null;
    }

    /**
     * Turbo-stream table refresh for recommendation grid actions.
     */
    private function recommendationsGridRefreshResponse(?string $pageContext): ?\Cake\Http\Response
    {
        if (!$this->wantsTurboStreamRequest() || $pageContext === null) {
            return null;
        }

        return $this->renderTurboCloseModal(
            'recommendations-grid-table',
            ['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'gridData'],
            $pageContext,
        );
    }
}
