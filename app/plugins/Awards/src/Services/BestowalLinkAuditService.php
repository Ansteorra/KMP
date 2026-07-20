<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;

/**
 * Read-only consistency checks for recommendation-to-bestowal link ownership.
 */
class BestowalLinkAuditService
{
    use LocatorAwareTrait;

    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private Table $bestowalRecommendationsTable;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Cake\ORM\Table|null $bestowalRecommendationsTable Optional injected join table.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?Table $bestowalRecommendationsTable = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->bestowalRecommendationsTable = $bestowalRecommendationsTable
            ?? $this->fetchTable('Awards.BestowalRecommendations');
    }

    /**
     * @param int $sampleLimit Number of sample rows to return per issue type.
     * @return array<string, array{count:int,samples:array<int, array<string, mixed>>}>
     */
    public function audit(int $sampleLimit = 25): array
    {
        return [
            'joinRowsWithoutRecommendationShortcut' => $this->issueResult(
                $this->joinRowsWithoutRecommendationShortcutQuery(),
                ['BestowalRecommendations.bestowal_id' => 'ASC', 'BestowalRecommendations.recommendation_id' => 'ASC'],
                $sampleLimit,
            ),
            'recommendationShortcutsWithoutJoinRow' => $this->issueResult(
                $this->recommendationShortcutsWithoutJoinRowQuery(),
                ['Recommendations.bestowal_id' => 'ASC', 'Recommendations.id' => 'ASC'],
                $sampleLimit,
            ),
            'cancelledBestowalsWithActiveJoinRows' => $this->issueResult(
                $this->cancelledBestowalsWithActiveJoinRowsQuery(),
                ['Bestowals.id' => 'ASC', 'BestowalRecommendations.recommendation_id' => 'ASC'],
                $sampleLimit,
            ),
            'recommendationBestowalsMissingAward' => $this->issueResult(
                $this->recommendationBestowalsMissingAwardQuery(),
                ['Bestowals.id' => 'ASC'],
                $sampleLimit,
            ),
            'activeRecommendationBestowalsWithoutJoinRows' => $this->issueResult(
                $this->activeRecommendationBestowalsWithoutJoinRowsQuery(),
                ['Bestowals.id' => 'ASC'],
                $sampleLimit,
            ),
        ];
    }

    /**
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function joinRowsWithoutRecommendationShortcutQuery(): SelectQuery
    {
        return $this->bestowalRecommendationsTable->find()
            ->select([
                'bestowal_id' => 'BestowalRecommendations.bestowal_id',
                'recommendation_id' => 'BestowalRecommendations.recommendation_id',
                'shortcut_bestowal_id' => 'Recommendations.bestowal_id',
            ])
            ->innerJoin(
                ['Recommendations' => 'awards_recommendations'],
                ['Recommendations.id = BestowalRecommendations.recommendation_id'],
            )
            ->where([
                'OR' => [
                    'Recommendations.bestowal_id IS' => null,
                    'Recommendations.bestowal_id != BestowalRecommendations.bestowal_id',
                ],
            ]);
    }

    /**
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function recommendationShortcutsWithoutJoinRowQuery(): SelectQuery
    {
        return $this->recommendationsTable->find()
            ->select([
                'recommendation_id' => 'Recommendations.id',
                'bestowal_id' => 'Recommendations.bestowal_id',
            ])
            ->leftJoin(
                ['BestowalRecommendations' => 'awards_bestowal_recommendations'],
                [
                    'BestowalRecommendations.recommendation_id = Recommendations.id',
                    'BestowalRecommendations.bestowal_id = Recommendations.bestowal_id',
                ],
            )
            ->where([
                'Recommendations.bestowal_id IS NOT' => null,
                'BestowalRecommendations.id IS' => null,
            ]);
    }

    /**
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function cancelledBestowalsWithActiveJoinRowsQuery(): SelectQuery
    {
        return $this->bestowalsTable->find()
            ->select([
                'bestowal_id' => 'Bestowals.id',
                'recommendation_id' => 'BestowalRecommendations.recommendation_id',
                'lifecycle_status' => 'Bestowals.lifecycle_status',
            ])
            ->innerJoin(
                ['BestowalRecommendations' => 'awards_bestowal_recommendations'],
                ['BestowalRecommendations.bestowal_id = Bestowals.id'],
            )
            ->where(['Bestowals.lifecycle_status' => Bestowal::LIFECYCLE_CANCELLED]);
    }

    /**
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function recommendationBestowalsMissingAwardQuery(): SelectQuery
    {
        return $this->bestowalsTable->find()
            ->select([
                'bestowal_id' => 'Bestowals.id',
                'award_id' => 'Bestowals.award_id',
                'primary_recommendation_id' => 'Bestowals.primary_recommendation_id',
            ])
            ->where([
                'Bestowals.source' => Bestowal::SOURCE_RECOMMENDATION,
                'OR' => [
                    'Bestowals.award_id IS' => null,
                    'Bestowals.award_id <=' => 0,
                ],
            ]);
    }

    /**
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function activeRecommendationBestowalsWithoutJoinRowsQuery(): SelectQuery
    {
        return $this->bestowalsTable->find()
            ->select([
                'bestowal_id' => 'Bestowals.id',
                'lifecycle_status' => 'Bestowals.lifecycle_status',
                'primary_recommendation_id' => 'Bestowals.primary_recommendation_id',
            ])
            ->leftJoin(
                ['BestowalRecommendations' => 'awards_bestowal_recommendations'],
                ['BestowalRecommendations.bestowal_id = Bestowals.id'],
            )
            ->where([
                'Bestowals.source' => Bestowal::SOURCE_RECOMMENDATION,
                'Bestowals.lifecycle_status !=' => Bestowal::LIFECYCLE_CANCELLED,
                'BestowalRecommendations.id IS' => null,
            ]);
    }

    /**
     * @param \Cake\ORM\Query\SelectQuery $query Issue query.
     * @param array<string, string> $order Sort order for stable samples.
     * @param int $sampleLimit Number of sample rows to include.
     * @return array{count:int,samples:array<int, array<string, mixed>>}
     */
    private function issueResult(SelectQuery $query, array $order, int $sampleLimit): array
    {
        $countQuery = clone $query;
        $sampleQuery = clone $query;
        $limit = max(1, $sampleLimit);

        return [
            'count' => $countQuery->count(),
            'samples' => $sampleQuery
                ->orderBy($order)
                ->limit($limit)
                ->enableHydration(false)
                ->all()
                ->toList(),
        ];
    }
}
