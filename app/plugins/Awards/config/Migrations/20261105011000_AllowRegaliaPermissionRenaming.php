<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;
use Migrations\Migration\IrreversibleMigrationException;

/**
 * Allow tenant administrators to rename the regalia workflow permissions.
 */
class AllowRegaliaPermissionRenaming extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        TableRegistry::getTableLocator()->get('Permissions')->updateAll(
            ['is_system' => false],
            ['name IN' => [
                'Baronial Regalia Management',
                'Crown Regalia Management',
                'Principality Regalia Management',
            ]],
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        throw new IrreversibleMigrationException(
            'Regalia permissions may have been renamed and cannot safely be identified by their original names.',
        );
    }
}
