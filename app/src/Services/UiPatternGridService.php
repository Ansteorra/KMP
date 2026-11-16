<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\GridColumns\UiPatternGatheringsGridColumns;
use App\KMP\GridViewConfig;
use App\Model\Entity\Member;
use Cake\Datasource\Exception\RecordNotFoundException;
use DateTimeImmutable;

/** Read-only sample rows with real, separately namespaced personal grid preferences. */
class UiPatternGridService
{
    public const GRID_KEY = 'UiPatterns.collection.main';

    public const BRANCH_GRID_KEY = 'UiPatterns.branch.gatherings';

    /** Reuse the member-owned view store, isolated by this showcase's grid key. */
    public function __construct(private ?GridViewService $views = null)
    {
        $this->views ??= new GridViewService();
    }

    /** Resolve the same view/query precedence used by Dataverse without querying gatherings. */
    public function build(array $query, Member $member): array
    {
        $columns = UiPatternGatheringsGridColumns::getColumns();
        $systemViews = UiPatternGatheringsGridColumns::getSystemViews();
        $isBranchContext = ($query['pattern'] ?? null) === 'record'
            && !in_array($query['record'] ?? 'branch', ['member', 'role'], true);
        $gridKey = $isBranchContext ? self::BRANCH_GRID_KEY : self::GRID_KEY;
        $availableViews = $this->views->getViewsForGrid($gridKey, $member);
        $preferredViewId = $this->views->getUserPreferenceViewId($gridKey, $member);
        $hasRequestedView = $this->scalar($query['view_id'] ?? '') !== '';
        $requested = $this->scalar($query['view_id'] ?? $preferredViewId ?? 'all');
        $currentView = null;
        $selectedSystemView = null;
        if ($hasRequestedView || empty($query['ignore_default'])) {
            if (ctype_digit($requested)) {
                try {
                    $candidate = $this->views->getView((int)$requested, $member);
                    $currentView = $candidate && $candidate->grid_key === $gridKey ? $candidate : null;
                } catch (RecordNotFoundException) {
                    // A removed or unrelated saved view must never change this sample grid's scope.
                }
            }
            if ($currentView === null) {
                $selectedSystemView = $systemViews[$requested] ?? $systemViews['all'];
            }
        } else {
            $selectedSystemView = $systemViews['all'];
        }
        $config = $currentView?->getConfigArray() ?? $selectedSystemView['config'] ?? [];
        $config = GridViewConfig::normalize($config, $columns + ['_search' => ['filterable' => true]]);
        $dirty = is_array($query['dirty'] ?? null) ? $query['dirty'] : [];
        $filters = [];
        $search = '';
        foreach ($config['filters'] as $filter) {
            $field = $filter['field'];
            if ($field === '_search') {
                $search = $this->scalar($filter['value'] ?? '');
            } elseif ($field === 'start_date' && $filter['operator'] === 'dateRange') {
                $range = is_array($filter['value']) ? $filter['value'] : [];
                foreach (['start', 'end'] as $index => $suffix) {
                    $date = $this->date($range[$index] ?? null);
                    if ($date !== null) {
                        $filters['start_date_' . $suffix] = $date;
                    }
                }
            } elseif (
                isset($columns[$field]['filterQueryField']) && in_array($filter['operator'], ['eq', 'in'], true)
            ) {
                $filters[$field] = $this->values($filter['value'] ?? []);
            }
        }
        if ($isBranchContext) {
            $filters['branch'] = ['oakmere'];
        }
        $savedFilters = $filters;
        $savedSearch = $search;
        if (isset($dirty['filters'])) {
            $filters = [];
        }
        $incoming = is_array($query['filter'] ?? null) ? $query['filter'] : [];
        foreach (UiPatternGatheringsGridColumns::getDropdownFilterColumns() as $field => $column) {
            if (array_key_exists($field, $incoming)) {
                $filters[$field] = $this->values($incoming[$field]);
            }
        }
        foreach (['start_date_start', 'start_date_end'] as $key) {
            if (array_key_exists($key, $query)) {
                unset($filters[$key]);
                $date = $this->date($query[$key]);
                if ($date !== null) {
                    $filters[$key] = $date;
                }
            }
        }
        if ($isBranchContext) {
            $filters['branch'] = ['oakmere'];
        }
        $filters = array_filter($filters, static fn($value) => $value !== [] && $value !== '');
        if (array_key_exists('search', $query) || isset($dirty['search'])) {
            $search = $this->scalar($query['search'] ?? '');
        }
        $search = mb_substr(trim($search), 0, 200);
        $savedSort = ['field' => $config['sort'][0]['field'] ?? 'start_date',
            'direction' => $config['sort'][0]['direction'] ?? 'asc'];
        $querySort = isset($dirty['sort']) ? ['field' => 'start_date', 'direction' => 'asc'] : $savedSort;
        $sortField = $this->scalar($query['sort'] ?? $querySort['field']);
        $sortField = isset($columns[$sortField]) ? $sortField : 'start_date';
        $direction = strtolower($this->scalar($query['direction'] ?? $querySort['direction']));
        $sort = ['field' => $sortField, 'direction' => $direction === 'desc' ? 'desc' : 'asc'];
        $savedColumns = $this->visibleColumns(GridViewConfig::extractVisibleColumns($config, $columns), $columns);
        $visible = $savedColumns;
        if (is_string($query['columns'] ?? null)) {
            $visible = explode(',', $query['columns']);
        }
        $visible = $this->visibleColumns($visible, $columns);
        $savedPageSize = $currentView ? $config['pageSize'] : 10;
        $pageSize = GridViewConfig::extractPageSize([
            'pageSize' => $query['limit'] ?? $savedPageSize,
        ]);
        $state = $this->scalar($query['state'] ?? 'normal');
        $state = in_array($state, ['normal', 'empty', 'error', 'restricted'], true) ? $state : 'normal';
        $rows = $this->rows();
        $filterOptions = $this->filterOptions($rows);
        $filtered = in_array($state, ['empty', 'error'], true) ? [] : array_values(array_filter(
            $rows,
            fn(array $row): bool => $this->matches($row, $filters, $search, $columns),
        ));
        usort($filtered, static function (array $a, array $b) use ($sort): int {
            $order = strnatcasecmp((string)$a[$sort['field']], (string)$b[$sort['field']]);

            return $order * ($sort['direction'] === 'asc' ? 1 : -1);
        });
        $total = count($filtered);
        $pageCount = max(1, (int)ceil($total / $pageSize));
        $page = max(1, min($pageCount, (int)$this->scalar($query['page'] ?? 1)));
        $offset = ($page - 1) * $pageSize;
        $data = array_slice($filtered, $offset, $pageSize);

        return [
            'viewHasChanges' => $this->filterFingerprint($filters) !== $this->filterFingerprint($savedFilters)
                || $search !== $savedSearch || $sort !== $savedSort || $visible !== $savedColumns
                || $pageSize !== $savedPageSize,
            'gridData' => $data,
            'columns' => $columns,
            'gridPagination' => [
                'page' => $page, 'pageSize' => $pageSize, 'total' => $total, 'pageCount' => $pageCount,
                'start' => $total ? $offset + 1 : 0, 'end' => $offset + count($data),
            ],
            'showcaseState' => $state,
            'gridError' => $state === 'error',
            'exportData' => $this->exportRows($filtered, $visible, $columns),
            'stateArguments' => [
                'currentView' => $currentView, 'selectedSystemView' => $selectedSystemView,
                'systemViews' => $systemViews, 'availableViews' => $availableViews, 'currentMember' => $member,
                'preferredViewId' => $preferredViewId, 'search' => $search, 'filters' => $filters,
                'filterOptions' => $filterOptions,
                'dropdownFilterColumns' => UiPatternGatheringsGridColumns::getDropdownFilterColumns(),
                'dateRangeFilterColumns' => ['start_date' => $columns['start_date']], 'sort' => $sort,
                'visibleColumns' => $visible, 'allColumns' => $columns, 'gridKey' => $gridKey,
                'pageSize' => $pageSize, 'showAllTab' => false, 'canAddViews' => true, 'canFilter' => true,
                'hasSearch' => true, 'hasDropdownFilters' => true, 'hasDateRangeFilters' => true,
                'skipFilterColumns' => [], 'canExportCsv' => $state !== 'error', 'showFilterPills' => true,
                'showViewTabs' => true, 'enableColumnPicker' => true, 'enableBulkSelection' => true,
                'lockedFilters' => $isBranchContext ? ['branch'] : [],
                'bulkSelection' => ['rowLabelTemplate' => 'Select {name}', 'selectAllLabel' => 'Select this page'],
                'bulkActions' => [],
            ],
        ];
    }

    /** Required and registered columns survive saved configurations and URL edits. */
    private function visibleColumns(array $visible, array $columns): array
    {
        $visible = array_values(array_unique(array_intersect($visible, array_keys($columns))));
        if ($visible === []) {
            $visible = array_keys(UiPatternGatheringsGridColumns::getDefaultVisibleColumns());
        }
        if (!in_array('name', $visible, true)) {
            array_unshift($visible, 'name');
        }

        return $visible;
    }

    /** Filter option ordering is irrelevant, unlike visible-column ordering. */
    private function filterFingerprint(array $filters): array
    {
        ksort($filters);
        foreach ($filters as &$value) {
            if (is_array($value)) {
                sort($value);
            }
        }
        unset($value);

        return $filters;
    }

    /** Return display values in the same column order as the filtered grid. */
    private function exportRows(array $rows, array $visible, array $columns): array
    {
        return array_map(static function (array $row) use ($visible, $columns): array {
            $export = [];
            foreach ($visible as $field) {
                $export[$columns[$field]['label']] = $row[$field];
            }

            return $export;
        }, $rows);
    }

    /** Exact option values are distinct from the row's human-readable labels. */
    private function filterOptions(array $rows): array
    {
        $options = [];
        foreach (UiPatternGatheringsGridColumns::getDropdownFilterColumns() as $field => $column) {
            $values = [];
            foreach ($rows as $row) {
                $value = $row[$column['filterQueryField']];
                $values[$value] = [
                    'value' => $value, 'label' => $row[$field] === '—' ? 'No Royal Progress' : $row[$field],
                ];
            }
            $options[$field] = array_values($values);
        }

        return $options;
    }

    /** Apply multi-value OR within each filter and AND between filter fields. */
    private function matches(array $row, array $filters, string $search, array $columns): bool
    {
        if ($search !== '') {
            $searchable = array_flip(UiPatternGatheringsGridColumns::getSearchableColumns());
            $text = implode(' ', array_intersect_key($row, $searchable));
            if (!str_contains(mb_strtolower($text), mb_strtolower($search))) {
                return false;
            }
        }
        foreach ($filters as $field => $value) {
            if ($field === 'start_date_start' && $row['start_date'] < $value) {
                return false;
            }
            if ($field === 'start_date_end' && $row['start_date'] > $value) {
                return false;
            }
            if (
                isset($columns[$field]['filterQueryField'])
                && !in_array($row[$columns[$field]['filterQueryField']], (array)$value, true)
            ) {
                return false;
            }
        }

        return true;
    }

    /** Reject nested request values rather than casting arrays into text. */
    private function scalar(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string)$value : '';
    }

    /** Bound and normalize exact filter option identifiers. */
    private function values(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn($item) => mb_substr($this->scalar($item), 0, 100),
            array_slice(is_array($value) ? $value : [$value], 0, 50),
        ), static fn($item) => $item !== '')));
    }

    /** Accept calendar dates only; the sample rows have date-only values. */
    private function date(mixed $value): ?string
    {
        $value = $this->scalar($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** Stable fictional records; never joins or reads real gathering/member data. */
    private function rows(): array
    {
        $branches = ['oakmere' => 'Barony of Oakmere', 'willowford' => 'Shire of Willowford',
            'silvermere' => 'Principality of Silvermere'];
        $activities = ['archery' => 'Archery', 'scribal' => 'Scribal arts', 'court' => 'Kingdom court',
            'armored' => 'Armored combat', 'rapier' => 'Rapier', 'bardic' => 'Bardic circle',
            'equestrian' => 'Equestrian', 'needlework' => 'Needlework', 'heraldry' => 'Heraldry'];
        $royals = ['none' => '—', 'crown-ansteorra' => 'Crown · Ansteorra',
            'coronet-oakmere' => 'Coronet · Oakmere', 'coronet-silvermere' => 'Principality Coronet · Silvermere'];
        $samples = [
            ['oakmere-archery', 'Oakmere Archery Practice',
                '2026-11-14', 'oakmere', 'archery', 'none', 'published', 18],
            ['scribal-workshop', 'Scribal Arts Workshop',
                '2026-11-21', 'willowford', 'scribal', 'none', 'published', 12],
            ['winter-court', 'Oakmere Winter Court',
                '2026-12-05', 'oakmere', 'court', 'crown-ansteorra', 'published', 87],
            ['fighter-practice', 'Winter Fighter Practice', '2026-12-12', 'oakmere', 'armored', 'none', 'draft', 0],
            ['rapier-practice', 'Willowford Rapier Practice',
                '2026-12-13', 'willowford', 'rapier', 'none', 'published', 16],
            ['bardic-evening', 'Oakmere Bardic Evening',
                '2026-12-19', 'oakmere', 'bardic', 'coronet-oakmere', 'published', 24],
            ['scribal-day', 'Silvermere Scribal Day',
                '2027-01-02', 'silvermere', 'scribal', 'coronet-silvermere', 'published', 33],
            ['new-year-archery', 'New Year Archery', '2027-01-09', 'willowford', 'archery', 'none', 'draft', 0],
            ['equestrian-day', 'Oakmere Equestrian Day',
                '2027-01-16', 'oakmere', 'equestrian', 'none', 'published', 29],
            ['needlework-circle', 'Willowford Needlework Circle',
                '2027-01-23', 'willowford', 'needlework', 'none', 'published', 11],
            ['heraldry-workshop', 'Silvermere Heraldry Workshop',
                '2027-01-30', 'silvermere', 'heraldry', 'none', 'published', 14],
            ['royal-visit', 'Royal Visit to Willowford',
                '2027-02-06', 'willowford', 'court', 'crown-ansteorra', 'published', 52],
            ['winter-bardic', 'Silvermere Winter Bardic',
                '2027-02-13', 'silvermere', 'bardic', 'coronet-silvermere', 'draft', 0],
            ['oakmere-rapier', 'Oakmere Rapier Tournament',
                '2027-02-20', 'oakmere', 'rapier', 'coronet-oakmere', 'published', 41],
        ];

        return array_map(static function (array $sample) use ($branches, $activities, $royals): array {
            [$id, $name, $date, $branch, $activity, $royal, $status, $rsvps] = $sample;

            return ['id' => $id, 'name' => $name, 'start_date' => $date, 'branch' => $branches[$branch],
                'branch_key' => $branch, 'activity' => $activities[$activity], 'activity_key' => $activity,
                'royal_progress' => $royals[$royal], 'royal_progress_key' => $royal,
                'status' => ucfirst($status), 'status_key' => $status, 'rsvps' => $rsvps,
                'location' => ucfirst($branch) . ' Community Hall'];
        }, $samples);
    }
}
