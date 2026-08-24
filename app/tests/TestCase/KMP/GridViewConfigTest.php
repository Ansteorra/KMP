<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\GridViewConfig;
use App\Test\TestCase\BaseTestCase;

class GridViewConfigTest extends BaseTestCase
{
    public function testNormalizeDropsLegacyBaseSystemViewId(): void
    {
        $normalized = GridViewConfig::normalize([
            'baseSystemViewId' => '  sys-recs-needs-my-approval  ',
        ]);

        $this->assertArrayNotHasKey('baseSystemViewId', $normalized);
    }

    public function testNormalizePreservesLockedFilterMarker(): void
    {
        $normalized = GridViewConfig::normalize([
            'filters' => [[
                'field' => 'member_scope',
                'operator' => 'in',
                'value' => ['awaiting_response'],
                'locked' => true,
            ]],
        ]);

        $this->assertTrue($normalized['filters'][0]['locked']);
    }

    public function testPreserveLockedFiltersRestoresChangedAndMissingValues(): void
    {
        $existing = GridViewConfig::normalize([
            'filters' => [
                [
                    'field' => 'member_scope',
                    'operator' => 'in',
                    'value' => ['awaiting_response'],
                    'locked' => true,
                ],
                [
                    'field' => 'status_label',
                    'operator' => 'in',
                    'value' => ['Pending'],
                    'locked' => true,
                ],
                [
                    'field' => 'requester_member_id',
                    'operator' => 'in',
                    'value' => [1],
                ],
            ],
        ]);
        $incoming = GridViewConfig::normalize([
            'filters' => [
                [
                    'field' => 'member_scope',
                    'operator' => 'in',
                    'value' => ['responded'],
                ],
                [
                    'field' => 'requester_member_id',
                    'operator' => 'in',
                    'value' => [2],
                ],
            ],
        ]);

        $preserved = GridViewConfig::preserveLockedFilters($existing, $incoming);
        $filters = array_column($preserved['filters'], null, 'field');

        $this->assertSame(['awaiting_response'], $filters['member_scope']['value']);
        $this->assertTrue($filters['member_scope']['locked']);
        $this->assertSame(['Pending'], $filters['status_label']['value']);
        $this->assertTrue($filters['status_label']['locked']);
        $this->assertSame([2], $filters['requester_member_id']['value']);
        $this->assertArrayNotHasKey('locked', $filters['requester_member_id']);
    }

    public function testPreserveLockedFiltersRetainsMultiplePredicatesAndOpenDateBounds(): void
    {
        $existing = GridViewConfig::normalize([
            'filters' => [
                [
                    'field' => 'created',
                    'operator' => 'gte',
                    'value' => '2026-01-01',
                    'locked' => true,
                ],
                [
                    'field' => 'created',
                    'operator' => 'lte',
                    'value' => '2026-01-31',
                    'locked' => true,
                ],
                [
                    'field' => 'expires_on',
                    'operator' => 'dateRange',
                    'value' => [null, '2026-12-31'],
                    'locked' => true,
                ],
            ],
        ]);
        $incoming = GridViewConfig::normalize(['filters' => []]);

        $preserved = GridViewConfig::preserveLockedFilters($existing, $incoming);

        $this->assertCount(3, $preserved['filters']);
        $this->assertSame(['gte', 'lte'], array_column(array_slice($preserved['filters'], 0, 2), 'operator'));
        $this->assertSame([null, '2026-12-31'], $preserved['filters'][2]['value']);
        $this->assertTrue($preserved['filters'][2]['locked']);
    }
}
