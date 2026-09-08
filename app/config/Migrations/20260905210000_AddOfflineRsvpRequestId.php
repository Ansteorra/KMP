<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Deduplicate retries even if an offline RSVP response is lost in transit. */
class AddOfflineRsvpRequestId extends BaseMigration
{
    /** @inheritDoc */
    public function change(): void
    {
        $this->table('gathering_attendances')
            ->addColumn('offline_request_id', 'string', ['limit' => 36, 'null' => true, 'default' => null])
            ->addIndex(['offline_request_id'], ['unique' => true, 'name' => 'idx_attendances_offline_request'])
            ->update();
    }
}
