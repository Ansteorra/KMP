<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class DropUnusedFeatureFlags extends AbstractMigration
{
    /**
     * Remove the unused per-tenant feature_flags column and the unused
     * feature_flags registry table. Neither has ever been read by the
     * application; feature flagging will be redesigned holistically later.
     */
    public function up(): void
    {
        $this->table('tenants')
            ->removeColumn('feature_flags')
            ->update();

        $this->table('feature_flags')->drop()->save();
    }

    /**
     * Restore the dropped storage (empty; the app never wrote real data).
     */
    public function down(): void
    {
        $this->table('tenants')
            ->addColumn('feature_flags', 'json', ['null' => true])
            ->update();

        $this->table('feature_flags', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid')
            ->addColumn('flag_key', 'string', ['limit' => 120])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('default_enabled', 'boolean', ['default' => false])
            ->addColumn('rules', 'json', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->addIndex(['flag_key'], ['unique' => true])
            ->create();
    }
}
