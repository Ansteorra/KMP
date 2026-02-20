<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;
use Migrations\Migration\ManagerFactory;


require_once __DIR__ . '/../Seeds/MigrAddViewMembersPermission.php';

class AddViewMembersPermission extends BaseMigration
{
    /** Disable transaction wrapping so embedded seed failures don't roll back DDL on Postgres. */
    public function useTransactions(): bool
    {
        return false;
    }

    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function up(): void
    {
        [$pluginName, $seeder] = pluginSplit("MigrAddViewMembersPermission");
        $adapter = $this->getAdapter();
        $connection = $adapter->getConnection()->configName();

        $factory = new ManagerFactory([
            'plugin' => $options['plugin'] ?? $pluginName ?? null,
            'source' => 'Seeds',
            'connection' => $options['connection'] ?? $connection,
        ]);
        $io = $this->getIo();
        assert($io !== null, 'Missing ConsoleIo instance');
        $manager = $factory->createManager($io);
        $manager->seed($seeder);
    }

    public function down(): void
    {
        $tbl = TableRegistry::getTableLocator()->get('Permissions');
        $perm = $tbl->find()->where(['name' => 'Can View Members'])->first();

        if ($perm) {
            $tbl->delete($perm);
        }
    }
}