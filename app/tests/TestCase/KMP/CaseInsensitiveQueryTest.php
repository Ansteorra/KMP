<?php
declare(strict_types=1);

namespace App\Test\TestCase\KMP;

use App\KMP\CaseInsensitiveQuery;
use App\KMP\GridViewConfig;
use Cake\Database\Driver\Postgres;
use Cake\Database\Expression\FunctionExpression;
use Cake\Datasource\ConnectionManager;
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
        $fieldExpression = $this->scaNameFieldExpression('Members.sca_name');
        $this->assertEquals(
            [$fieldExpression . ' LIKE' => $this->scaNameValueExpression('%Mixed Name%')],
            CaseInsensitiveQuery::contains('Members.sca_name', ' Mixed Name '),
        );
        $this->assertEquals(
            [$fieldExpression . ' LIKE' => $this->scaNameValueExpression('Mixed%')],
            CaseInsensitiveQuery::startsWith('Members.sca_name', ' Mixed '),
        );
        $this->assertEquals(
            [$fieldExpression . ' LIKE' => $this->scaNameValueExpression('%Mixed')],
            CaseInsensitiveQuery::endsWith('Members.sca_name', ' Mixed '),
        );
    }

    public function testPostgresScaNameValueUsesTheSameDatabaseFunctionsAsTheField(): void
    {
        $condition = CaseInsensitiveQuery::equals('Members.sca_name', ' Ǣthelred ');
        $value = array_values($condition)[0];

        if (ConnectionManager::get('default')->getDriver() instanceof Postgres) {
            $this->assertInstanceOf(FunctionExpression::class, $value);

            return;
        }

        $this->assertSame('ǣthelred', $value);
    }

    public function testRecognizesQualifiedAndDenormalizedScaNameFields(): void
    {
        $this->assertTrue(CaseInsensitiveQuery::isScaNameField('Members.sca_name'));
        $this->assertTrue(CaseInsensitiveQuery::isScaNameField('Recommendations.member_sca_name'));
        $this->assertFalse(CaseInsensitiveQuery::isScaNameField('Members.first_name'));
    }

    public function testLegacyGridFiltersUsePortableCaseInsensitivePatterns(): void
    {
        $this->assertEquals(
            [
                $this->scaNameFieldExpression('Members.sca_name') . ' LIKE' => $this->scaNameValueExpression('%Mixed%'),
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

    private function scaNameFieldExpression(string $field): string
    {
        $lowerExpression = 'LOWER(' . $field . ')';

        return ConnectionManager::get('default')->getDriver() instanceof Postgres
            ? 'UNACCENT(' . $lowerExpression . ')'
            : $lowerExpression;
    }

    private function scaNameValueExpression(string $value): string|FunctionExpression
    {
        if (!ConnectionManager::get('default')->getDriver() instanceof Postgres) {
            return mb_strtolower($value);
        }

        return new FunctionExpression('UNACCENT', [
            new FunctionExpression('LOWER', [$value], ['string']),
        ]);
    }
}
