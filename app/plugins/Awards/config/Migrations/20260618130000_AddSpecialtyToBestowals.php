<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add a persisted award specialty to bestowals for court and scribal planning.
 */
class AddSpecialtyToBestowals extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_bestowals')
            ->addColumn('specialty', 'string', [
                'after' => 'award_id',
                'limit' => 255,
                'null' => true,
            ])
            ->update();

        $this->execute(
            "UPDATE awards_bestowals
                SET specialty = (
                    SELECT specialty
                    FROM awards_recommendations
                    WHERE awards_recommendations.id = awards_bestowals.primary_recommendation_id
                )
                WHERE specialty IS NULL
                    AND primary_recommendation_id IS NOT NULL",
        );

        $this->execute(
            "UPDATE awards_bestowals
                SET specialty = (
                    SELECT r.specialty
                    FROM awards_bestowal_recommendations br
                    INNER JOIN awards_recommendations r ON r.id = br.recommendation_id
                    WHERE br.bestowal_id = awards_bestowals.id
                        AND r.specialty IS NOT NULL
                        AND r.specialty <> ''
                    ORDER BY br.id ASC
                    LIMIT 1
                )
                WHERE specialty IS NULL",
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_bestowals')
            ->removeColumn('specialty')
            ->update();
    }
}
