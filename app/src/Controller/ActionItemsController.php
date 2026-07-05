<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\GridColumns\ActionItemsGridColumns;
use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemCompletionFormRegistry;
use App\Services\ActionItems\ActionItemService;
use Cake\Http\Response;
use Cake\Routing\Router;
use Throwable;

/**
 * ActionItems Controller - the reusable "My To-Dos" surface.
 *
 * Lists the open to-do items the current member is eligible to act on (resolved
 * the same way the approvals queue resolves "who can act") and provides gated
 * complete / reopen transitions. To-dos are a second item source alongside
 * workflow approvals, surfaced in the same Action Items navigation area.
 *
 * @property \App\Model\Table\ActionItemsTable $ActionItems
 */
class ActionItemsController extends AppController
{
    use DataverseGridTrait;

    protected ?string $defaultTable = 'ActionItems';

    /**
     * Initialize controller authorization.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'myTasks',
            'myTasksGridData',
            'mobileMyTasks',
            'mobileMyTasksData',
        );
    }

    /**
     * My To-Dos dashboard: a Dataverse grid of the member's to-dos.
     *
     * The grid lazy-loads {@see myTasksGridData()}; this action only renders the
     * page shell + completion modal (mirrors the My Approvals grid page).
     *
     * @return \Cake\Http\Response|null
     */
    public function myTasks(): ?Response
    {
        return null;
    }

    /**
     * Grid data endpoint for the My To-Dos Dataverse grid.
     *
     * Open view lists the to-dos the member may act on (resolved via the same
     * eligibility logic as the rich list); the Completed view lists to-dos the
     * member has completed.
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @return \Cake\Http\Response|null|void
     */
    public function myTasksGridData(ActionItemService $actionItemService)
    {
        $user = $this->request->getAttribute('identity');
        $memberId = (int)$user->getIdentifier();

        $actionItemsTable = $this->fetchTable('ActionItems');
        $systemViews = ActionItemsGridColumns::getSystemViews();
        $baseQuery = $actionItemsTable->find()->contain(['Branches']);

        $queryCallback = function ($query, $systemView) use ($memberId, $actionItemService) {
            if ($systemView === null) {
                return $query->where(['ActionItems.id' => -1]);
            }

            if ($systemView['id'] === 'sys-todos-completed') {
                return $query->where([
                    'ActionItems.status' => ActionItem::STATUS_COMPLETED,
                    'ActionItems.completed_by' => $memberId,
                ]);
            }

            return $actionItemService->applyOpenCandidateScopeForMember($query, $memberId);
        };

        $result = $this->processDataverseGrid([
            'gridKey' => 'Core.actionItems.myTasks',
            'gridColumnsClass' => ActionItemsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'ActionItems',
            'defaultSort' => ['ActionItems.modified' => 'desc', 'ActionItems.id' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-todos-open',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'lockedFilters' => ['status_label'],
            'showFilterPills' => true,
            'showSearchBox' => true,
        ]);

        $this->prepareTodosForGrid($result['data'], $result['visibleColumns'], $user);

        $this->set([
            'data' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => ActionItemsGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'rowActions' => ActionItemsGridColumns::getRowActions(),
            'customElement' => null,
            'customElementOptions' => [],
        ]);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        if ($turboFrame === 'action-items-grid-table') {
            $this->set('tableFrameId', 'action-items-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            $this->set('frameId', 'action-items-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Mobile My To-Dos dashboard: online-only actionable to-do cards.
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @return \Cake\Http\Response|null
     */
    public function mobileMyTasks(ActionItemService $actionItemService): ?Response
    {
        $user = $this->request->getAttribute('identity');
        $memberId = (int)$user->getIdentifier();
        $openCount = $actionItemService->countOpenItemsForMember($memberId);
        if ($openCount === 0) {
            $this->Flash->info(__('You have no open to-dos right now.'));

            return $this->redirect(['controller' => 'Members', 'action' => 'viewMobileCard']);
        }

        $this->set('mobileTitle', 'My To-Dos');
        $this->set('mobileSection', 'todos');
        $this->set('mobileIcon', 'bi-check2-all');
        $this->set('mobileQueuePerPage', self::MOBILE_QUEUE_DEFAULT_PER_PAGE);

        $this->viewBuilder()->setLayout('mobile_app');

        return null;
    }

    /**
     * JSON API: open to-do items the current member may act on.
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @return \Cake\Http\Response|null
     */
    public function mobileMyTasksData(ActionItemService $actionItemService): ?Response
    {
        $this->request->allowMethod(['get']);

        $user = $this->request->getAttribute('identity');
        $memberId = (int)$user->getIdentifier();
        $items = $actionItemService->getOpenItemsForMember($memberId);
        $total = count($items);
        $pagination = $this->mobileQueuePagination($total);
        $pageItems = array_slice($items, (int)$pagination['offset'], (int)$pagination['perPage']);
        $groups = $this->groupByOwner($pageItems);

        return $this->jsonResponse([
            'openCount' => $total,
            'groups' => $this->serializeMobileGroups($groups, $user),
            'pagination' => $this->mobileQueuePaginationPayload($pagination),
        ]);
    }

    /**
     * Mark a to-do complete (eligibility-gated by ActionItemPolicy).
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @param int|null $id Action item id
     * @return \Cake\Http\Response|null
     */
    public function complete(ActionItemService $actionItemService, ?int $id = null): ?Response
    {
        return $this->transitionItem($actionItemService, 'complete', $id);
    }

    /**
     * Reopen a completed to-do (eligibility-gated by ActionItemPolicy).
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @param int|null $id Action item id
     * @return \Cake\Http\Response|null
     */
    public function reopen(ActionItemService $actionItemService, ?int $id = null): ?Response
    {
        return $this->transitionItem($actionItemService, 'reopen', $id);
    }

    /**
     * Shared complete/reopen handler.
     *
     * @param \App\Services\ActionItems\ActionItemService $actionItemService To-do service
     * @param string $operation Either 'complete' or 'reopen'
     * @param int|null $id Action item id from the route
     * @return \Cake\Http\Response|null
     */
    private function transitionItem(
        ActionItemService $actionItemService,
        string $operation,
        ?int $id,
    ): ?Response {
        $this->request->allowMethod(['post']);

        $user = $this->request->getAttribute('identity');
        $actorId = (int)$user->getIdentifier();
        $itemId = (int)($this->request->getData('id') ?? $id);
        $note = $this->request->getData('note');

        $item = $this->ActionItems->find()->where(['ActionItems.id' => $itemId])->first();
        if ($item === null) {
            if ($this->wantsTurboStreamRequest()) {
                $this->Flash->error(__('To-do item not found.'));

                return $this->renderTurboCloseModal(
                    'action-items-grid-table',
                    ['controller' => 'ActionItems', 'action' => 'myTasksGridData'],
                    $this->getPageContextUrl(),
                );
            }
            if ($this->wantsJsonResponse()) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => __('To-do item not found.'),
                ], 404);
            }
            $this->Flash->error(__('To-do item not found.'));

            return $this->redirectBack();
        }

        $this->Authorization->authorize($item, $operation);

        $result = $operation === 'complete'
            ? $actionItemService->complete(
                $itemId,
                $actorId,
                $note,
                !$user->isSuperUser(),
                $this->request->getData(),
                $user,
            )
            : $actionItemService->reopen($itemId, $actorId, $note, !$user->isSuperUser());

        if ($this->wantsTurboStreamRequest()) {
            if ($result->success) {
                $this->Flash->success(
                    $operation === 'complete' ? __('Marked complete.') : __('Reopened.'),
                );
            } else {
                $this->Flash->error($result->reason ?? __('The to-do item could not be updated.'));
            }

            return $this->renderTurboCloseModal(
                'action-items-grid-table',
                ['controller' => 'ActionItems', 'action' => 'myTasksGridData'],
                $this->getPageContextUrl(),
            );
        }

        if ($this->wantsJsonResponse()) {
            return $this->jsonResponse([
                'success' => $result->success,
                'error' => $result->success ? null : ($result->reason ?? __('The to-do item could not be updated.')),
                'itemId' => $itemId,
                'status' => $operation === 'complete' && $result->success ? 'completed' : null,
            ], $result->success ? 200 : 422);
        }

        if ($result->success) {
            $this->Flash->success(
                $operation === 'complete' ? __('Marked complete.') : __('Reopened.'),
            );
        } else {
            $this->Flash->error($result->reason ?? __('The to-do item could not be updated.'));
        }

        return $this->redirectBack();
    }

    /**
     * Redirect to the caller's page when provided, else the My To-Dos queue.
     *
     * @return \Cake\Http\Response|null
     */
    private function redirectBack(): ?Response
    {
        $redirect = $this->request->getData('current_page');
        if ($redirect) {
            return $this->redirect($redirect);
        }

        return $this->redirect(['action' => 'myTasks']);
    }

    /**
     * Decorate grid rows with computed display fields (owner link, status, etc.).
     *
     * @param iterable<\App\Model\Entity\ActionItem> $items Grid rows.
     * @param array<string> $visibleColumns Visible column keys.
     * @param \App\KMP\KmpIdentityInterface $user Current user.
     * @return void
     */
    private function prepareTodosForGrid(iterable $items, array $visibleColumns, KmpIdentityInterface $user): void
    {
        $items = is_array($items) ? $items : iterator_to_array($items);
        $includeOwner = in_array('owner', $visibleColumns, true);
        $includeBranch = in_array('branch', $visibleColumns, true);
        $ownerDescriptors = $includeOwner ? $this->buildOwnerDescriptors($items) : [];

        foreach ($items as $item) {
            $item->status_label = ucfirst((string)$item->status);
            $item->requirement = $item->is_gating ? __('Required') : __('Optional');

            if ($includeBranch) {
                $item->branch = $item->branch->name ?? '—';
            }

            if ($includeOwner) {
                $key = $item->entity_type . ':' . $item->entity_id;
                $descriptor = $ownerDescriptors[$key]
                    ?? $this->genericOwnerDescriptor((string)$item->entity_type, (int)$item->entity_id);
                $item->owner = $this->buildOwnerHtml($descriptor);
            }

            $completionForm = ActionItemCompletionFormRegistry::formFor($item, $user);
            $item->completion_form_data = $completionForm?->toArray() ?? [];
        }
    }

    /**
     * Resolve owner label + url descriptors for grid rows, batched by owner type.
     *
     * @param array<\App\Model\Entity\ActionItem> $items Grid rows.
     * @return array<string, array<string, mixed>> Keyed by "type:id".
     */
    private function buildOwnerDescriptors(array $items): array
    {
        $idsByType = [];
        foreach ($items as $item) {
            $idsByType[(string)$item->entity_type][(int)$item->entity_id] = true;
        }

        $descriptors = [];
        foreach ($idsByType as $type => $idMap) {
            $ids = array_keys($idMap);
            $batch = $type === 'Awards.Bestowals' ? $this->loadBestowalDescriptors($ids) : [];
            foreach ($ids as $id) {
                $descriptors[$type . ':' . $id] = $batch[$id]
                    ?? $this->genericOwnerDescriptor($type, $id);
            }
        }

        return $descriptors;
    }

    /**
     * Batch-load Awards bestowal owner descriptors, guarded so a disabled or
     * schema-changed Awards plugin degrades gracefully.
     *
     * @param array<int> $ids Bestowal ids.
     * @return array<int, array<string, mixed>> Keyed by bestowal id.
     */
    private function loadBestowalDescriptors(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            $bestowals = $this->fetchTable('Awards.Bestowals')->find()
                ->where(['Bestowals.id IN' => $ids])
                ->contain(['Members', 'Awards'])
                ->all();

            $descriptors = [];
            foreach ($bestowals as $bestowal) {
                $recipient = $bestowal->get('member_sca_name')
                    ?: ($bestowal->member->sca_name ?? __('Recipient'));
                $award = $bestowal->award->name ?? __('Award');
                $descriptors[(int)$bestowal->id] = [
                    'label' => sprintf('%s — %s', $recipient, $award),
                    'url' => $this->ownerUrl('Awards.Bestowals', (int)$bestowal->id),
                ];
            }

            return $descriptors;
        } catch (Throwable $exception) {
            return [];
        }
    }

    /**
     * Build a generic owner descriptor from the polymorphic owner type/id.
     *
     * @param string $entityType Owner type, e.g. Awards.Bestowals.
     * @param int $entityId Owner primary key.
     * @return array<string, mixed>
     */
    private function genericOwnerDescriptor(string $entityType, int $entityId): array
    {
        return [
            'label' => str_replace('.', ' ', $entityType) . ' #' . $entityId,
            'url' => $this->ownerUrl($entityType, $entityId),
        ];
    }

    /**
     * Render an owner descriptor as an escaped HTML link for the grid cell.
     *
     * @param array<string, mixed> $descriptor Owner descriptor (label + url).
     * @return string
     */
    private function buildOwnerHtml(array $descriptor): string
    {
        $label = (string)($descriptor['label'] ?? '');
        $url = !empty($descriptor['url']) ? Router::url($descriptor['url']) : null;

        if ($url === null) {
            return h($label);
        }

        return sprintf(
            '<a href="%s" data-turbo-frame="_top">%s</a>',
            h($url),
            h($label),
        );
    }

    /**
     * Group open items by their owning entity for display.
     *
     * @param array<\App\Model\Entity\ActionItem> $items Open items
     * @return array<int, array<string, mixed>>
     */
    private function groupByOwner(array $items): array
    {
        $groups = [];
        $ownerDescriptors = $this->buildOwnerDescriptors($items);
        foreach ($items as $item) {
            $key = $item->entity_type . ':' . $item->entity_id;
            if (!isset($groups[$key])) {
                $groups[$key] = $ownerDescriptors[$key]
                    ?? $this->genericOwnerDescriptor((string)$item->entity_type, (int)$item->entity_id);
                $groups[$key]['entityType'] = (string)$item->entity_type;
                $groups[$key]['entityId'] = (int)$item->entity_id;
                $groups[$key]['items'] = [];
            }
            $groups[$key]['items'][] = $item;
        }

        return array_values($groups);
    }

    /**
     * Derive a CakePHP view route array from a polymorphic owner type.
     *
     * @param string $entityType Owner type, e.g. Awards.Bestowals or Members
     * @param int $entityId Owner primary key
     * @return array<string, mixed>
     */
    private function ownerUrl(string $entityType, int $entityId): array
    {
        if (str_contains($entityType, '.')) {
            [$plugin, $controller] = explode('.', $entityType, 2);

            return ['plugin' => $plugin, 'controller' => $controller, 'action' => 'view', $entityId];
        }

        return ['plugin' => null, 'controller' => $entityType, 'action' => 'view', $entityId];
    }

    /**
     * Convert owner-grouped action items into the compact mobile JSON contract.
     *
     * @param array<int, array<string, mixed>> $groups Owner-grouped open items
     * @param \App\KMP\KmpIdentityInterface $user Current user.
     * @return array<int, array<string, mixed>>
     */
    private function serializeMobileGroups(array $groups, KmpIdentityInterface $user): array
    {
        $payload = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                $completionForm = ActionItemCompletionFormRegistry::formFor($item, $user);
                $items[] = [
                    'id' => (int)$item->id,
                    'title' => (string)$item->title,
                    'description' => (string)($item->description ?? ''),
                    'isGating' => (bool)$item->is_gating,
                    'branchName' => (string)($item->branch->name ?? ''),
                    'modified' => $item->modified?->toIso8601String(),
                    'completionForm' => $completionForm?->toArray(),
                ];
            }

            $payload[] = [
                'label' => (string)$group['label'],
                'url' => !empty($group['url']) ? Router::url($group['url']) : null,
                'entityType' => (string)$group['entityType'],
                'entityId' => (int)$group['entityId'],
                'openCount' => count($items),
                'items' => $items,
            ];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload JSON payload
     * @param int $status HTTP status
     * @return \Cake\Http\Response
     */
    private function jsonResponse(array $payload, int $status = 200): Response
    {
        return $this->response
            ->withType('application/json')
            ->withStatus($status)
            ->withStringBody(json_encode($payload));
    }

    /**
     * @return bool Whether this request expects a JSON response
     */
    private function wantsJsonResponse(): bool
    {
        return $this->request->is('ajax')
            || str_contains((string)$this->request->getHeaderLine('Accept'), 'application/json');
    }
}
