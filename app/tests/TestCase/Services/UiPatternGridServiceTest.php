<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\GridViewService;
use App\Services\UiPatternGridService;
use App\Test\TestCase\BaseTestCase;

/** Saved views exercise the normal owner-scoped store while rows remain fictional. */
class UiPatternGridServiceTest extends BaseTestCase
{
    public function testCombinedFiltersSortAndExportUseTheSameRows(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $result = (new UiPatternGridService())->build([
            'filter' => ['branch' => ['oakmere'], 'activity' => ['archery', 'court']],
            'start_date_end' => '2026-12-05', 'sort' => 'name', 'direction' => 'desc',
            'columns' => 'start_date,name,royal_progress',
        ], $member);
        $this->assertSame(['winter-court', 'oakmere-archery'], array_column($result['gridData'], 'id'));
        $this->assertSame(2, $result['gridPagination']['total']);
        $this->assertSame(['Date', 'Gathering', 'Royal Progress'], array_keys($result['exportData'][0]));
        $this->assertSame('Oakmere Winter Court', $result['exportData'][0]['Gathering']);
        $this->assertSame('Crown · Ansteorra', $result['exportData'][0]['Royal Progress']);
    }

    public function testExportIncludesEveryFilteredPageAndRequiredColumns(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $result = (new UiPatternGridService())->build(['page' => 2, 'columns' => 'rsvps,unknown'], $member);
        $this->assertCount(4, $result['gridData']);
        $this->assertCount(14, $result['exportData']);
        $this->assertSame(['name', 'rsvps'], $result['stateArguments']['visibleColumns']);
        $this->assertSame(11, $result['gridPagination']['start']);
        $this->assertSame(14, $result['gridPagination']['end']);
    }

    public function testSavedConfigurationReopensAndDirtyQueryCanClearItsFilters(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $views = new GridViewService();
        $view = $views->createView([
            'grid_key' => UiPatternGridService::GRID_KEY, 'name' => 'Field visit example',
            'config' => json_encode([
                'filters' => [
                    ['field' => '_search', 'operator' => 'contains', 'value' => 'Oakmere'],
                    ['field' => 'status', 'operator' => 'in', 'value' => ['published']],
                    ['field' => 'start_date', 'operator' => 'dateRange', 'value' => ['2026-12-01', '2027-02-28']],
                ],
                'sort' => [['field' => 'name', 'direction' => 'desc']],
                'columns' => [['key' => 'branch', 'visible' => true, 'order' => 0],
                    ['key' => 'name', 'visible' => true, 'order' => 1]],
                'pageSize' => 25,
            ]),
        ], $member);
        $this->assertNotFalse($view);
        $service = new UiPatternGridService($views);
        $result = $service->build(['view_id' => $view->id], $member);
        $this->assertSame('Oakmere', $result['stateArguments']['search']);
        $this->assertSame(4, $result['gridPagination']['total']);
        $this->assertSame(['branch', 'name'], $result['stateArguments']['visibleColumns']);
        $this->assertSame(25, $result['gridPagination']['pageSize']);
        $this->assertFalse($result['viewHasChanges']);
        $explicitView = $service->build(['view_id' => $view->id, 'ignore_default' => 1], $member);
        $this->assertSame($view->id, $explicitView['stateArguments']['currentView']->id);
        $this->assertSame('Oakmere', $explicitView['stateArguments']['search']);
        $clearedSort = $service->build(['view_id' => $view->id, 'dirty' => ['sort' => 1]], $member);
        $this->assertSame(['field' => 'start_date', 'direction' => 'asc'], $clearedSort['stateArguments']['sort']);
        $this->assertTrue($clearedSort['viewHasChanges']);

        $cleared = $service->build(['view_id' => $view->id, 'dirty' => ['filters' => 1, 'search' => 1]], $member);
        $this->assertSame(14, $cleared['gridPagination']['total']);
        $this->assertTrue($cleared['viewHasChanges']);
        $restored = $service->build([
            'view_id' => $view->id, 'dirty' => ['filters' => 1, 'search' => 1], 'search' => 'Oakmere',
            'filter' => ['status' => ['published']],
            'start_date_start' => '2026-12-01', 'start_date_end' => '2027-02-28',
        ], $member);
        $this->assertFalse($restored['viewHasChanges'], 'Dirty flags alone do not mean the saved view differs.');
    }

    public function testSavedViewsAndDefaultsAreIsolatedByOwnerAndGrid(): void
    {
        $members = $this->getTableLocator()->get('Members');
        $member = $members->get(self::TEST_MEMBER_AGATHA_ID);
        $otherMember = $members->get(self::TEST_MEMBER_BRYCE_ID);
        $views = new GridViewService();
        $otherGridView = $views->createView([
            'grid_key' => 'Gatherings.index.main', 'name' => 'Unrelated grid example',
            'config' => json_encode(['filters' => [['field' => '_search', 'operator' => 'contains', 'value' => 'secret']]]),
        ], $member);
        $otherOwnerView = $views->createView([
            'grid_key' => UiPatternGridService::GRID_KEY, 'name' => 'Other member example',
            'config' => json_encode(['filters' => [['field' => '_search', 'operator' => 'contains', 'value' => 'private']]]),
        ], $otherMember);
        $this->assertNotFalse($otherGridView);
        $this->assertNotFalse($otherOwnerView);
        $service = new UiPatternGridService($views);
        foreach ([$otherGridView->id, $otherOwnerView->id] as $id) {
            $result = $service->build(['view_id' => $id], $member);
            $this->assertNull($result['stateArguments']['currentView']);
            $this->assertSame('', $result['stateArguments']['search']);
            $available = array_map(static fn($view) => $view->id, $result['stateArguments']['availableViews']);
            $this->assertNotContains($id, $available);
        }
        $this->assertTrue($views->setUserDefault($otherGridView->id, $member->id, 'Gatherings.index.main'));
        $this->assertTrue($views->setUserDefault('royal-progress', $member->id, UiPatternGridService::GRID_KEY));
        $result = $service->build([], $member);
        $this->assertSame('royal-progress', $result['stateArguments']['selectedSystemView']['id']);
        $this->assertSame(6, $result['gridPagination']['total']);
        $this->assertSame($otherGridView->id, $views->getUserPreferenceViewId('Gatherings.index.main', $member));
        $all = $service->build(['ignore_default' => 1], $member);
        $this->assertSame('all', $all['stateArguments']['selectedSystemView']['id']);
        $this->assertSame(14, $all['gridPagination']['total']);
    }

    public function testReviewStatesAndMalformedQueryValuesRemainSafe(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $service = new UiPatternGridService();
        $result = $service->build([
            'view_id' => ['invalid'], 'sort' => ['invalid'], 'direction' => ['invalid'],
            'start_date_start' => '2026-02-31', 'start_date_end' => ['invalid'], 'page' => ['invalid'],
        ], $member);
        $this->assertSame(14, $result['gridPagination']['total']);
        $this->assertSame([], $result['stateArguments']['filters']);
        $this->assertFalse($service->build(['state' => 'restricted'], $member)['gridError']);
        $this->assertCount(0, $service->build(['state' => 'empty'], $member)['gridData']);
        $this->assertTrue($service->build(['state' => 'error'], $member)['gridError']);
    }

    public function testBranchGridHasIndependentPreferencesAndAnImmutableBranchFilter(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_AGATHA_ID);
        $views = new GridViewService();
        $collectionView = $views->createView([
            'grid_key' => UiPatternGridService::GRID_KEY, 'name' => 'Collection-only view',
            'config' => json_encode(['filters' => [
                ['field' => '_search', 'operator' => 'contains', 'value' => 'Willowford'],
            ]]),
        ], $member);
        $this->assertNotFalse($collectionView);
        $this->assertTrue($views->setUserDefault($collectionView->id, $member->id, UiPatternGridService::GRID_KEY));
        $service = new UiPatternGridService($views);
        $queries = [[], ['view_id' => $collectionView->id], [
            'filter' => ['branch' => ['willowford']], 'dirty' => ['filters' => 1],
        ], ['dirty' => ['filters' => 1], 'ignore_default' => 1]];
        foreach ($queries as $query) {
            $result = $service->build(['pattern' => 'record', 'record' => 'branch'] + $query, $member);
            $this->assertSame(UiPatternGridService::BRANCH_GRID_KEY, $result['stateArguments']['gridKey']);
            $this->assertSame(['branch'], $result['stateArguments']['lockedFilters']);
            $this->assertSame(['oakmere'], $result['stateArguments']['filters']['branch']);
            $this->assertSame(6, $result['gridPagination']['total']);
            $this->assertSame(['oakmere'], array_values(array_unique(array_column($result['gridData'], 'branch_key'))));
            $this->assertNull($result['stateArguments']['currentView']);
            $this->assertSame('', $result['stateArguments']['search']);
        }
        $collection = $service->build([], $member);
        $this->assertSame($collectionView->id, $collection['stateArguments']['currentView']->id);
        $this->assertSame([], $collection['stateArguments']['lockedFilters']);
    }
}
