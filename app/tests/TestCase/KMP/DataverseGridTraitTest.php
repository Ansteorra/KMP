<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\Controller\DataverseGridTrait;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\Query\SelectQuery;

class DataverseGridTraitTest extends BaseTestCase
{
    public function testSelectedSortReplacesOrderingFromTheBaseQuery(): void
    {
        $harness = new class {
            use DataverseGridTrait;

            public function sortByName(SelectQuery $query, string $direction): SelectQuery
            {
                return $this->applyDataverseGridSort($query, 'name', $direction, [], 'GatheringActivities');
            }
        };
        $table = $this->getTableLocator()->get('GatheringActivities');
        $fixtures = [
            ['name' => 'Selected sort A', 'display_order' => 30],
            ['name' => 'Selected sort B', 'display_order' => 10],
            ['name' => 'Selected sort C', 'display_order' => 20],
        ];
        foreach ($fixtures as $data) {
            $table->saveOrFail($table->newEntity($data));
        }
        foreach (['ASC', 'DESC'] as $direction) {
            $query = $table->find()->where(['name LIKE' => 'Selected sort %'])
                ->orderBy(['GatheringActivities.display_order' => 'ASC']);
            $expected = ['Selected sort A', 'Selected sort B', 'Selected sort C'];
            $this->assertSame(
                $direction === 'ASC' ? $expected : array_reverse($expected),
                $harness->sortByName($query, $direction)->all()->extract('name')->toList(),
            );
        }
    }

    public function testLockedDateRangeExtractionPreservesOpenBoundary(): void
    {
        $harness = new class {
            use DataverseGridTrait;

            /**
             * Expose locked date extraction for focused trait testing.
             *
             * @param array<string, mixed> $viewConfig
             * @param array<int, string> $lockedFilters
             * @param array<string, array<string, mixed>> $columnsMetadata
             * @return array<string, array{value: string|null, operator: string}>
             */
            public function lockedDateDefaults(
                array $viewConfig,
                array $lockedFilters,
                array $columnsMetadata,
            ): array {
                return $this->extractLockedDateRangeDefaults(
                    $viewConfig,
                    $lockedFilters,
                    $columnsMetadata,
                );
            }
        };

        $defaults = $harness->lockedDateDefaults(
            [
                'filters' => [[
                    'field' => 'expires_on',
                    'operator' => 'dateRange',
                    'value' => [null, '2026-08-21'],
                ]],
            ],
            ['expires_on'],
            [
                'expires_on' => ['filterType' => 'date-range'],
            ],
        );

        $this->assertSame([
            'expires_on_start' => ['value' => null, 'operator' => 'gte'],
            'expires_on_end' => ['value' => '2026-08-21', 'operator' => 'lte'],
        ], $defaults);
    }

    public function testLockedDateRangeExtractionPreservesStrictOperators(): void
    {
        $harness = new class {
            use DataverseGridTrait;

            /**
             * @param array<string, mixed> $viewConfig
             * @param array<int, string> $lockedFilters
             * @param array<string, array<string, mixed>> $columnsMetadata
             * @return array<string, array{value: string|null, operator: string}>
             */
            public function lockedDateDefaults(
                array $viewConfig,
                array $lockedFilters,
                array $columnsMetadata,
            ): array {
                return $this->extractLockedDateRangeDefaults(
                    $viewConfig,
                    $lockedFilters,
                    $columnsMetadata,
                );
            }

            /**
             * @param array<string, mixed> $viewConfig
             * @return array<string, mixed>
             */
            public function systemViewDefaults(array $viewConfig): array
            {
                return $this->extractSystemViewDefaults($viewConfig);
            }
        };

        $defaults = $harness->lockedDateDefaults(
            [
                'filters' => [
                    ['field' => 'expires_on', 'operator' => 'gt', 'value' => '2026-08-21 00:00:00'],
                    ['field' => 'expires_on', 'operator' => 'lt', 'value' => '2026-08-22 00:00:00'],
                ],
            ],
            ['expires_on'],
            [
                'expires_on' => ['filterType' => 'date-range'],
            ],
        );

        $this->assertSame([
            'expires_on_start' => ['value' => '2026-08-21 00:00:00', 'operator' => 'gt'],
            'expires_on_end' => ['value' => '2026-08-22 00:00:00', 'operator' => 'lt'],
        ], $defaults);

        $systemDefaults = $harness->systemViewDefaults([
            'filters' => [
                ['field' => 'expires_on', 'operator' => 'gt', 'value' => '2026-08-21 00:00:00'],
                ['field' => 'expires_on', 'operator' => 'lt', 'value' => '2026-08-22 00:00:00'],
            ],
        ]);
        $this->assertSame([
            'expires_on_start' => '2026-08-21 00:00:00',
            'expires_on_end' => '2026-08-22 00:00:00',
        ], $systemDefaults['dateRange']);
        $this->assertSame([
            'expires_on_start' => 'gt',
            'expires_on_end' => 'lt',
        ], $systemDefaults['dateRangeOperators']);
    }
}
