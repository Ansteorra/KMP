<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Services\RecommendationQueryService;

class RecommendationQueryServiceTest extends BaseTestCase
{
    private RecommendationQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecommendationQueryService();
    }

    /**
     * @return array<string, array{0: string, 1: array<int, mixed>}>
     */
    public static function buildQueryProvider(): array
    {
        return [
            'main grid' => ['buildMainGridQuery', [true]],
            'member submitted grid' => ['buildMemberSubmittedQuery', [self::ADMIN_MEMBER_ID]],
            'recs for member grid' => ['buildRecsForMemberQuery', [self::ADMIN_MEMBER_ID]],
            'gathering awards grid' => ['buildGatheringAwardsQuery', [1, true]],
        ];
    }

    /**
     * @dataProvider buildQueryProvider
     * @param string $method
     * @param array<int, mixed> $args
     * @return void
     */
    public function testBuildQueriesJoinAwardDomainsForDomainFiltering(string $method, array $args): void
    {
        $recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');

        $result = $this->service->{$method}($recommendationsTable, ...$args);
        $query = $result['query']->where(['Domains.id IN' => [1]]);
        $sql = strtolower($query->sql());

        $this->assertStringContainsString('join awards_domains domains', $sql);
        $this->assertStringContainsString('domains.id in', $sql);
    }
}
