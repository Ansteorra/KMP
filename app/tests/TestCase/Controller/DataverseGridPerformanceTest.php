<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Services\ViewCellRegistry;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Services\BestowalQueryService;
use Cake\Datasource\FactoryLocator;

class DataverseGridPerformanceTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testAppSettingsTableFrameUsesLeanStatePayload(): void
    {
        $this->configRequest([
            'headers' => [
                'Turbo-Frame' => 'app-settings-grid-table',
            ],
        ]);
        $this->get('/app-settings/gridData');
        $this->assertResponseOk();

        $body = (string)$this->_response->getBody();
        $state = $this->extractGridStateFromResponse($body, 'app-settings-grid-table-state');
        $this->assertIsArray($state['columns']['all'] ?? null);
        $this->assertSame([], $state['columns']['all'] ?? null);
        $this->assertSame([], $state['view']['available'] ?? null);
        $this->assertStringContainsString('<th', $body, 'Table frame must still render column headers');
    }

    public function testGridDataSizeTableFrameIsSmallerThanOuterFrame(): void
    {
        $this->get('/app-settings/gridData');
        $this->assertResponseOk();
        $outerBody = (string)$this->_response->getBody();
        $outerSize = strlen($outerBody);

        $this->configRequest([
            'headers' => [
                'Turbo-Frame' => 'app-settings-grid-table',
            ],
        ]);
        $this->get('/app-settings/gridData?page=2');
        $this->assertResponseOk();
        $tableBody = (string)$this->_response->getBody();
        $tableSize = strlen($tableBody);

        $this->assertGreaterThan(0, $outerSize);
        $this->assertGreaterThan(0, $tableSize);
        $this->assertLessThan($outerSize, $tableSize);

        fwrite(STDERR, sprintf(
            "METRIC AppSettings outer_bytes=%d table_bytes=%d\n",
            $outerSize,
            $tableSize,
        ));
    }

    public function testFullPageRequestsBuildPluginViewCells(): void
    {
        $calls = 0;
        ViewCellRegistry::register('PerformanceSentinel', [], function () use (&$calls): array {
            $calls++;

            return [];
        });

        try {
            $this->get('/members');
            $this->assertResponseOk();
            $this->assertSame(1, $calls);
        } finally {
            ViewCellRegistry::unregister('PerformanceSentinel');
        }
    }

    public function testGridDataRequestsSkipPluginViewCells(): void
    {
        $calls = 0;
        ViewCellRegistry::register('PerformanceSentinel', [], function () use (&$calls): array {
            $calls++;

            return [];
        });

        try {
            $this->get('/members/grid-data');
            $this->assertResponseOk();
            $this->assertSame(0, $calls);
        } finally {
            ViewCellRegistry::unregister('PerformanceSentinel');
        }
    }

    public function testAppSettingAssetRequestsSkipPluginViewCells(): void
    {
        $assetName = 'Performance.FastPathAsset';
        $assetBody = 'fast path asset';
        $assetPayload = json_encode([
            'storage' => 'database',
            'filename' => 'fast-path.txt',
            'mime' => 'text/plain',
            'sha256' => hash('sha256', $assetBody),
            'data' => base64_encode($assetBody),
        ], JSON_THROW_ON_ERROR);
        $appSettings = $this->getTableLocator()->get('AppSettings');
        $this->assertTrue($appSettings->setAppSetting($assetName, $assetPayload, 'file'));

        $calls = 0;
        ViewCellRegistry::register('PerformanceSentinel', [], function () use (&$calls): array {
            $calls++;

            return [];
        });

        try {
            $this->get('/app-settings/asset/' . rawurlencode($assetName));
            $this->assertResponseOk();
            $this->assertResponseEquals($assetBody);
            $this->assertSame(0, $calls);
        } finally {
            ViewCellRegistry::unregister('PerformanceSentinel');
        }
    }

    public function testBranchOfficersOuterFrameIncludesSystemViewTabs(): void
    {
        $this->get('/officers/officers/gridData?branch_id=' . self::KINGDOM_BRANCH_ID);
        $this->assertResponseOk();

        $body = (string)$this->_response->getBody();
        $state = $this->extractGridStateFromResponse($body, 'branch-officers-grid-table-state');
        $viewIds = array_column($state['view']['available'] ?? [], 'id');
        $this->assertContains('sys-officers-current', $viewIds);
        $this->assertContains('sys-officers-upcoming', $viewIds);
        $this->assertContains('sys-officers-previous', $viewIds);
    }

    public function testBranchOfficersTableFrameUsesLeanStatePayload(): void
    {
        $this->configRequest([
            'headers' => [
                'Turbo-Frame' => 'branch-officers-grid-table',
            ],
        ]);
        $this->get('/officers/officers/gridData?branch_id=' . self::KINGDOM_BRANCH_ID);
        $this->assertResponseOk();

        $body = (string)$this->_response->getBody();
        $state = $this->extractGridStateFromResponse($body, 'branch-officers-grid-table-state');
        $this->assertSame([], $state['columns']['all'] ?? null);
        $this->assertSame([], $state['view']['available'] ?? null);
        fwrite(STDERR, sprintf("METRIC Officers branch_table_bytes=%d\n", strlen($body)));
    }

    public function testRecommendationsTableFrameUsesLeanStatePayload(): void
    {
        $this->configRequest([
            'headers' => [
                'Turbo-Frame' => 'recommendations-grid-table',
            ],
        ]);
        $this->get('/awards/recommendations/gridData');
        $this->assertResponseOk();

        $body = (string)$this->_response->getBody();
        $state = $this->extractGridStateFromResponse($body, 'recommendations-grid-table-state');
        $this->assertSame([], $state['columns']['all'] ?? null);
        $this->assertSame([], $state['view']['available'] ?? null);
        fwrite(STDERR, sprintf("METRIC Recommendations table_bytes=%d\n", strlen($body)));
    }

    public function testBestowalQuerySkipsHiddenDisplayOnlyAssociations(): void
    {
        $bestowalsTable = FactoryLocator::get('Table')->get('Awards.Bestowals');
        $queryService = new BestowalQueryService();

        $built = $queryService->buildIndexQuery($bestowalsTable, false, ['member_sca_name', 'state']);
        $contain = $built['query']->getContain();

        $this->assertArrayHasKey('Members', $contain);
        $this->assertArrayNotHasKey('GatheringScheduledActivities', $contain);
        $this->assertArrayNotHasKey('Recommendations', $contain);
        $this->assertArrayNotHasKey('PrimaryRecommendation', $contain);
    }

    public function testComplexVisibleColumnAwareGridEndpointsRenderWithNarrowColumns(): void
    {
        $endpoints = [
            '/awards/bestowals/gridData?columns=member_sca_name,state',
            '/members/gridData?columns=sca_name,status',
            '/gatherings/gridData?columns=name,start_date,end_date',
            '/workflows/instances/grid-data?columns=id,status',
            '/workflows/approvals-grid-data?columns=status_label,created',
        ];

        foreach ($endpoints as $endpoint) {
            $this->get($endpoint);
            $this->assertResponseOk("Expected $endpoint to render");
        }
    }

    /**
     * @param string $responseBody
     * @param string $scriptId
     * @return array<string,mixed>
     */
    private function extractGridStateFromResponse(string $responseBody, string $scriptId): array
    {
        $pattern = sprintf(
            '/<script type="application\\/json" id="%s">\\s*(.*?)\\s*<\\/script>/s',
            preg_quote($scriptId, '/'),
        );
        preg_match($pattern, $responseBody, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Expected grid state script to be present');
        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded, 'Expected grid state JSON to decode');

        return $decoded;
    }
}
