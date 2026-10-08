<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Element;

use App\Test\TestCase\BaseTestCase;
use Cake\Http\ServerRequest;
use Cake\View\View;

class DataverseGridContextTest extends BaseTestCase
{
    public function testMatchingFrameRestoresItsCompleteGridQuery(): void
    {
        $context = $this->gridQuery();
        $context['grid_context'] = 'branch-officers-grid';
        $src = $this->renderGridUrl('branch-officers-grid', $context, '/officers/officers/grid-data?branch_id='
            . self::KINGDOM_BRANCH_ID);
        parse_str((string)parse_url($src, PHP_URL_QUERY), $query);

        $this->assertSame('/officers/officers/grid-data', parse_url($src, PHP_URL_PATH));
        $this->assertSame((string)self::KINGDOM_BRANCH_ID, $query['branch_id']);
        $this->assertSame($this->gridQuery(), array_diff_key($query, ['branch_id' => true]));
        $this->assertArrayNotHasKey('grid_context', $query);
    }

    public function testSiblingFrameKeepsItsFixedDataUrlWithoutHostGridState(): void
    {
        $context = $this->gridQuery();
        $context['grid_context'] = 'branch-officers-grid';
        $dataUrl = '/branches/grid-data?parent_id=' . self::KINGDOM_BRANCH_ID . '&sort=lft&direction=asc';

        $this->assertSame($dataUrl, $this->renderGridUrl('branch-children-grid', $context, $dataUrl));
    }

    public function testRestoredQueryCannotOverrideFixedEndpointParameters(): void
    {
        $fixed = [
            'branch_id' => (string)self::KINGDOM_BRANCH_ID,
            'member_id' => (string)self::ADMIN_MEMBER_ID,
            'gathering_id' => 'fixed-gathering',
            'parent_id' => (string)self::KINGDOM_BRANCH_ID,
            'frame_id' => 'branch-officers-grid',
        ];
        $dataUrl = '/officers/officers/grid-data?' . http_build_query($fixed);
        foreach ([null, 'branch-officers-grid'] as $marker) {
            foreach ([false, true] as $arrayValues) {
                $context = $this->gridQuery();
                foreach ($fixed as $key => $value) {
                    $context[$key] = $arrayValues ? ['other-context'] : 'other-context';
                }
                if ($marker !== null) {
                    $context['grid_context'] = $marker;
                }
                $src = $this->renderGridUrl('branch-officers-grid', $context, $dataUrl);
                parse_str((string)parse_url($src, PHP_URL_QUERY), $query);

                $this->assertSame($fixed + $this->gridQuery(), $query);
                foreach (array_keys($fixed) as $key) {
                    $this->assertSame(1, preg_match_all('/(?:\?|&)' . $key . '=/', $src));
                }
            }
        }
    }

    public function testUnmarkedHostQueryKeepsLegacyRestoration(): void
    {
        $src = $this->renderGridUrl('branch-officers-grid', $this->gridQuery(), '/officers/officers/grid-data');
        parse_str((string)parse_url($src, PHP_URL_QUERY), $query);

        $this->assertSame($this->gridQuery(), $query);
    }

    public function testMarkedMutationResponseKeepsTheRestoredGridEmbedded(): void
    {
        $html = $this->renderGrid(
            'activity-awards-grid',
            ['grid_context' => 'activity-awards-grid'],
            '/awards/awards/grid-data',
            'addActivityToGatheringActivity',
        );

        $this->assertStringContainsString('data-grid-view-sync-url-value="false"', $html);
    }

    public function testExplicitSynchronizationOverridesTheMarkedDefault(): void
    {
        foreach ([false, true] as $syncUrl) {
            $html = $this->renderGrid(
                'activity-awards-grid',
                ['grid_context' => 'activity-awards-grid'],
                '/awards/awards/grid-data',
                'addActivityToGatheringActivity',
                $syncUrl,
            );

            $this->assertStringContainsString('data-grid-view-sync-url-value="' . ($syncUrl ? 'true' : 'false')
                . '"', $html);
        }
    }

    public function testUnmarkedMutationKeepsTheExistingSynchronizationDefault(): void
    {
        $html = $this->renderGrid(
            'activity-awards-grid',
            [],
            '/awards/awards/grid-data',
            'addActivityToGatheringActivity',
        );

        $this->assertStringContainsString('data-grid-view-sync-url-value="true"', $html);
    }

    private function gridQuery(): array
    {
        return [
            'tab' => 'officers',
            'page' => '3',
            'limit' => '50',
            'search' => 'Marshal',
            'sort' => 'member_sca_name',
            'direction' => 'desc',
            'view_id' => 'saved-officers-view',
            'system_view' => 'sys-officers-current',
            'filter' => ['status' => ['Current', 'Upcoming'], 'warrant_state' => ['Missing']],
            'dirty' => ['search' => '1', 'sort' => '1'],
        ];
    }

    private function renderGridUrl(string $frameId, array $query, string $dataUrl): string
    {
        $html = $this->renderGrid($frameId, $query, $dataUrl);
        $this->assertSame(1, preg_match('/<turbo-frame[^>]+src="([^"]+)"/', $html, $matches));

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
    }

    private function renderGrid(
        string $frameId,
        array $query,
        string $dataUrl,
        string $action = 'view',
        ?bool $syncUrl = null,
    ): string {
        $request = (new ServerRequest())->withParam('action', $action)->withQueryParams($query);
        $view = new View($request);

        return $view->element('dv_grid', compact('frameId', 'dataUrl', 'syncUrl'));
    }
}
