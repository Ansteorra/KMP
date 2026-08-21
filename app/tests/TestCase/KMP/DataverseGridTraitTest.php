<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\Controller\DataverseGridTrait;
use Cake\TestSuite\TestCase;

class DataverseGridTraitTest extends TestCase
{
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
             * @return array<string, string|null>
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
            'expires_on_start' => null,
            'expires_on_end' => '2026-08-21',
        ], $defaults);
    }
}
