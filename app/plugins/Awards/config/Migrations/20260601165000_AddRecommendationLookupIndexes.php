<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add indexes for recommendation grid enrichment and filter-option lookups.
 */
class AddRecommendationLookupIndexes extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $this->table('notes')
            ->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_notes_entity_lookup'])
            ->update();

        $this->table('gathering_attendances')
            ->addIndex(['member_id', 'share_with_crown'], ['name' => 'idx_att_member_crown'])
            ->addIndex(['member_id', 'share_with_kingdom'], ['name' => 'idx_att_member_kingdom'])
            ->update();

        $this->table('awards_recommendations_events')
            ->addIndex(['event_id', 'recommendation_id'], ['name' => 'idx_rec_events_event_rec'])
            ->addIndex(['recommendation_id', 'event_id'], ['name' => 'idx_rec_events_rec_event'])
            ->update();

        $this->table('awards_recommendations')
            ->addIndex(['member_sca_name'], ['name' => 'idx_rec_member_sca_name'])
            ->addIndex(['requester_sca_name'], ['name' => 'idx_rec_requester_sca_name'])
            ->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_recommendations')
            ->removeIndexByName('idx_rec_requester_sca_name')
            ->removeIndexByName('idx_rec_member_sca_name')
            ->update();

        $this->table('awards_recommendations_events')
            ->removeIndexByName('idx_rec_events_rec_event')
            ->removeIndexByName('idx_rec_events_event_rec')
            ->update();

        $this->table('gathering_attendances')
            ->removeIndexByName('idx_att_member_kingdom')
            ->removeIndexByName('idx_att_member_crown')
            ->update();

        $this->table('notes')
            ->removeIndexByName('idx_notes_entity_lookup')
            ->update();
    }
}
