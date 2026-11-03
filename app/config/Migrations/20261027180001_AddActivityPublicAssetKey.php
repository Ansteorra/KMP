<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddActivityPublicAssetKey extends BaseMigration
{
    /** @return void */
    public function change(): void
    {
        $this->table('gathering_activities')
            ->addColumn('icon_asset_key', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->update();
    }
}
