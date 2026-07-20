<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Store award recipient names on bestowals and allow non-account recipients.
 */
class AddRecipientNameToBestowals extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('awards_bestowals')) {
            return;
        }

        $table = $this->table('awards_bestowals');
        if (!$table->hasColumn('member_sca_name')) {
            $table
                ->addColumn('member_sca_name', 'string', [
                    'limit' => 255,
                    'null' => false,
                    'default' => '',
                    'after' => 'member_id',
                ])
                ->addIndex(['member_sca_name'], ['name' => 'idx_bestowals_member_sca_name'])
                ->update();
        }

        $this->execute(
            "UPDATE awards_bestowals
                SET member_sca_name = COALESCE(
                    (
                        SELECT COALESCE(NULLIF(r.member_sca_name, ''), m.sca_name)
                          FROM awards_recommendations r
                          LEFT JOIN members m ON m.id = r.member_id
                         WHERE r.id = awards_bestowals.primary_recommendation_id
                    ),
                    member_sca_name,
                    ''
                )
              WHERE member_sca_name IS NULL OR member_sca_name = ''",
        );
        $this->execute(
            "UPDATE awards_bestowals
                SET member_sca_name = COALESCE(
                    (
                        SELECT m.sca_name
                          FROM members m
                         WHERE m.id = awards_bestowals.member_id
                    ),
                    member_sca_name,
                    ''
                )
              WHERE member_sca_name IS NULL OR member_sca_name = ''",
        );

        $table = $this->table('awards_bestowals');
        $table->changeColumn('member_id', 'integer', [
            'limit' => 11,
            'null' => true,
        ])->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (!$this->hasTable('awards_bestowals')) {
            return;
        }

        $this->execute(
            'UPDATE awards_bestowals
                SET member_id = (
                    SELECT r.member_id
                      FROM awards_recommendations r
                     WHERE r.id = awards_bestowals.primary_recommendation_id
                )
              WHERE member_id IS NULL
                AND EXISTS (
                    SELECT 1
                      FROM awards_recommendations r
                     WHERE r.id = awards_bestowals.primary_recommendation_id
                       AND r.member_id IS NOT NULL
                )',
        );
        $this->execute('DELETE FROM awards_bestowals WHERE member_id IS NULL');
        $table = $this->table('awards_bestowals');
        if ($table->hasColumn('member_sca_name')) {
            $table->removeIndexByName('idx_bestowals_member_sca_name')->update();
            $table->removeColumn('member_sca_name')->update();
        }
        $this->table('awards_bestowals')->changeColumn('member_id', 'integer', [
            'limit' => 11,
            'null' => false,
        ])->update();
    }
}
