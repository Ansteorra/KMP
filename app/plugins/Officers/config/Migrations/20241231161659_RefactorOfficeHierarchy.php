<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Migrations\Migration\ManagerFactory;

require_once __DIR__ . '/../Seeds/InitOfficersRefactorSeed.php';

class RefactorOfficeHierarchy extends BaseMigration
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
    public function change(): void
    {
        // add new columns to the offices table
        $table = $this->table('officers_offices');
        $table->addColumn('applicable_branch_types', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => true,
        ]);
        $table->addColumn('reports_to_id', 'integer', [
            'default' => null,
            'limit' => 11,
            'null' => true,
        ]);
        $table->removeColumn('kingdom_only');
        $table->update();

        $this->table("officers_offices")
            ->addForeignKey(
                "reports_to_id",
                "officers_offices",
                "id",
                [
                    "update" => "NO_ACTION",
                    "delete" => "NO_ACTION",
                ],
            )
            ->update();

        $table = $this->table('officers_officers');
        $table->addColumn("deputy_to_branch_id", "integer", [
            "default" => null,
            "limit" => 11,
            "null" => true,
        ]);
        $table->addColumn("deputy_to_office_id", "integer", [
            "default" => null,
            "limit" => 11,
            "null" => true,
        ]);

        $table->addForeignKey(
            "deputy_to_branch_id",
            "branches",
            "id",
            [
                "update" => "NO_ACTION",
                "delete" => "NO_ACTION",
            ],
        );
        $table->addForeignKey(
            "deputy_to_office_id",
            "officers_offices",
            "id",
            [
                "update" => "NO_ACTION",
                "delete" => "NO_ACTION",
            ],
        );
        $table->update();

        [$pluginName, $seeder] = pluginSplit("Officers.InitOfficersRefactorSeed");
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
}
