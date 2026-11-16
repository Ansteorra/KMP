<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\CsvExportService;
use App\Services\UiPatternGridService;
use Cake\Http\Response;

/** Isolated, authenticated design review using fictional workflow examples. */
class UiPatternsController extends AppController
{
    use DataverseGridTrait;

    /** Render a whitelisted example; this controller has no mutation endpoints. */
    public function index(): void
    {
        $this->renderShowcase();
    }

    /** A distinct record destination preserves the existing app navigation stack. */
    public function reader(): void
    {
        $this->renderShowcase('reader');
    }

    /** Compose the same review surface for index selectors and record navigation. */
    private function renderShowcase(?string $requestedPattern = null): void
    {
        $this->request->allowMethod(['get']);
        $this->authorizeCurrentUrl();

        $patterns = [
            'collection' => __('Collection · Gatherings'),
            'record' => __('Record · Branch, member, role'),
            'reader' => __('Reader · Gathering'),
            'task' => __('Focused task · Waiver submission'),
            'workspace' => __('Workspace · Court agenda'),
            'dashboard' => __('Dashboard · Waiver operations'),
        ];
        $states = [
            'normal' => __('Typical use'),
            'empty' => __('Empty state'),
            'error' => __('Needs attention'),
            'restricted' => __('Limited access'),
        ];
        $recordKinds = ['branch' => __('Branch'), 'member' => __('Member'), 'role' => __('Role')];
        $pattern = $requestedPattern ?? $this->request->getQuery('pattern', 'collection');
        $showcaseState = $this->request->getQuery('state', 'normal');
        $recordKind = $this->request->getQuery('record', 'branch');
        $pattern = is_string($pattern) && isset($patterns[$pattern]) ? $pattern : 'collection';
        $showcaseState = is_string($showcaseState) && isset($states[$showcaseState]) ? $showcaseState : 'normal';
        $recordKind = is_string($recordKind) && isset($recordKinds[$recordKind]) ? $recordKind : 'branch';
        $this->set(compact('patterns', 'states', 'recordKinds', 'pattern', 'showcaseState', 'recordKind'));
        if ($pattern === 'collection' || ($pattern === 'record' && $recordKind === 'branch')) {
            $this->set($this->showcaseGrid());
        }
        $this->viewBuilder()->setLayout('default')->setTemplate('index');
    }

    /** Refresh the synthetic table or export its current complete filtered result. */
    public function gridData(CsvExportService $csvExportService): ?Response
    {
        $this->request->allowMethod(['get']);
        $this->authorizeCurrentUrl();
        $result = $this->showcaseGrid();
        if ($this->request->getQuery('export') === 'csv') {
            $headers = array_map(
                fn($field) => $result['columns'][$field]['label'],
                $result['gridState']['columns']['visible'],
            );

            return $csvExportService->outputCsv($result['exportData'], 'showcase-gatherings.csv', $headers);
        }
        $this->set($result);
        $this->viewBuilder()->disableAutoLayout()->setTemplate('grid_data');

        return null;
    }

    /** Shared inline and Turbo responses use the authoritative Dataverse state builder. */
    private function showcaseGrid(): array
    {
        $result = (new UiPatternGridService())->build(
            $this->request->getQueryParams(),
            $this->request->getAttribute('identity'),
        );
        $result['gridState'] = $this->buildDataverseGridState(...$result['stateArguments']);
        $result['gridState']['view']['hasChanges'] = $result['viewHasChanges'];
        $result['gridState']['pagination'] = [
            'currentPage' => $result['gridPagination']['page'],
            'pageCount' => $result['gridPagination']['pageCount'],
            'count' => count($result['gridData']),
            'totalCount' => $result['gridPagination']['total'],
        ];
        unset($result['stateArguments'], $result['viewHasChanges']);

        return $result;
    }
}
