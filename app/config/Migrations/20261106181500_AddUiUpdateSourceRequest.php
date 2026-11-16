<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/** Distinguish the initiating browser from other viewers without identifying a person. */
class AddUiUpdateSourceRequest extends BaseMigration
{
    /** @inheritDoc */
    public function change(): void
    {
        $this->table('ui_update_outbox')
            ->addColumn('source_request', 'string', ['limit' => 36, 'null' => true])
            ->update();
    }
}
