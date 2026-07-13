<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable Squiz.Classes.ClassFileName.NoMatch

class CreatePlatformSettingsAndBackupPolicy extends AbstractMigration
{
    /**
     * Create the platform_settings key/value table and seed the global
     * backup policy (cadence + retention) that drives fleet backups.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('platform_settings', ['id' => false, 'primary_key' => ['key']])
            ->addColumn('key', 'string', ['limit' => 120])
            ->addColumn('value', 'text', ['null' => true])
            ->addColumn('modified_at', 'datetime', ['null' => true])
            ->create();

        $now = date('Y-m-d H:i:s');
        $this->table('platform_settings')->insert([
            ['key' => 'backup.cadence', 'value' => 'daily', 'modified_at' => $now],
            ['key' => 'backup.retention_days', 'value' => '30', 'modified_at' => $now],
        ])->saveData();
    }

    /**
     * Drop the platform_settings table.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('platform_settings')->drop()->save();
    }
}
