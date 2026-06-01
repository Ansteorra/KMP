<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add indexes for high-traffic recommendation grid filters, joins, and sorts.
 */
class AddRecommendationGridIndexes extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('awards_recommendations');
        $table
            ->addIndex(['award_id'], ['name' => 'idx_rec_award_id'])
            ->addIndex(['branch_id'], ['name' => 'idx_rec_branch_id'])
            ->addIndex(['requester_id', 'created'], ['name' => 'idx_rec_requester_created'])
            ->addIndex(['member_id', 'created'], ['name' => 'idx_rec_member_created'])
            ->addIndex(['recommendation_group_id', 'created'], ['name' => 'idx_rec_group_created'])
            ->addIndex(['status', 'state'], ['name' => 'idx_rec_status_state'])
            ->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('awards_recommendations');
        $table
            ->removeIndexByName('idx_rec_status_state')
            ->removeIndexByName('idx_rec_group_created')
            ->removeIndexByName('idx_rec_member_created')
            ->removeIndexByName('idx_rec_requester_created')
            ->removeIndexByName('idx_rec_branch_id')
            ->removeIndexByName('idx_rec_award_id')
            ->update();
    }
}
