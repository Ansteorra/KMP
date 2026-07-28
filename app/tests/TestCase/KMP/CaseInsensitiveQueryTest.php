<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\CaseInsensitiveQuery;
use App\KMP\GridViewConfig;
use Cake\TestSuite\TestCase;
use ReflectionMethod;

class CaseInsensitiveQueryTest extends TestCase
{
    public function testNormalizesWhitespaceAndCaseWithoutChangingStoredValues(): void
    {
        $this->assertSame(
            ['LOWER(Members.email_address)' => 'mixed@example.com'],
            CaseInsensitiveQuery::equals('Members.email_address', '  Mixed@Example.COM '),
        );
    }

    public function testBuildsPortablePatternConditions(): void
    {
        $this->assertSame(
            ['LOWER(Members.sca_name) LIKE' => '%mixed name%'],
            CaseInsensitiveQuery::contains('Members.sca_name', ' Mixed Name '),
        );
        $this->assertSame(
            ['LOWER(Members.sca_name) LIKE' => 'mixed%'],
            CaseInsensitiveQuery::startsWith('Members.sca_name', ' Mixed '),
        );
        $this->assertSame(
            ['LOWER(Members.sca_name) LIKE' => '%mixed'],
            CaseInsensitiveQuery::endsWith('Members.sca_name', ' Mixed '),
        );
    }

    public function testLegacyGridFiltersUsePortableCaseInsensitivePatterns(): void
    {
        $this->assertSame(
            [
                'LOWER(Members.sca_name) LIKE' => '%mixed%',
                'LOWER(Members.email_address) LIKE' => 'person%',
            ],
            GridViewConfig::extractFilters([
                'filters' => [
                    ['field' => 'Members.sca_name', 'operator' => 'contains', 'value' => ' Mixed '],
                    ['field' => 'Members.email_address', 'operator' => 'startsWith', 'value' => ' Person '],
                ],
            ]),
        );
    }

    public function testExpressionEqualityDoesNotLowerDropdownRelationIds(): void
    {
        $this->assertSame(
            ['Levels.id' => '1'],
            $this->buildLeafCondition(
                ['field' => 'level_name', 'operator' => 'eq', 'value' => '1'],
                'Awards',
                [
                    'level_name' => [
                        'type' => 'string',
                        'filterType' => 'dropdown',
                        'queryField' => 'Levels.id',
                    ],
                ],
            ),
        );
    }

    public function testExpressionEqualityLowersFreeTextQueryFields(): void
    {
        $this->assertSame(
            ['LOWER(Awards.name)' => 'mixed'],
            $this->buildLeafCondition(
                ['field' => 'name', 'operator' => 'eq', 'value' => ' Mixed '],
                'Awards',
                [
                    'name' => [
                        'type' => 'string',
                        'filterType' => 'text',
                        'queryField' => 'Awards.name',
                    ],
                ],
            ),
        );
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, array<string, mixed>> $columnsMetadata
     * @return array<string, mixed>
     */
    private function buildLeafCondition(
        array $condition,
        string $tableName,
        array $columnsMetadata,
    ): array {
        $method = new ReflectionMethod(GridViewConfig::class, 'buildLeafCondition');

        /** @var array<string, mixed> */
        return $method->invoke(null, $condition, $tableName, [], $columnsMetadata);
    }
}
