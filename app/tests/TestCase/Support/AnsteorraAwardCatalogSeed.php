<?php
declare(strict_types=1);

namespace App\Test\TestCase\Support;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Migrations\Migration\Environment;
use RuntimeException;
use SeedBestowalTodoTemplates;
use SeedDefaultAwardApprovalProcesses;

/**
 * Reconciles current-schema award fields after either test seed load order.
 */
final class AnsteorraAwardCatalogSeed
{
    private const APPROVAL_PROCESS_NAMES = [
        1 => 'Single Approver - Crown',
        2 => 'Single Approver - Local',
        3 => 'Single Approver - Principality Coronet',
        4 => 'Dual Approver - Local then Crown',
    ];

    private const BESTOWAL_TEMPLATE_NAMES = [
        1 => 'Kingdom Award',
        2 => 'Principality Award',
        3 => 'Baronial Award',
    ];

    public static function synchronize(string $connectionName, bool $replayDataMigrations): void
    {
        if ($replayDataMigrations) {
            self::replayDataMigrations($connectionName);
        }

        $catalog = self::loadCatalog();
        $connection = ConnectionManager::get($connectionName);
        $approvalProcessIds = self::resolveIdsByName(
            $connection,
            'awards_approval_processes',
            self::APPROVAL_PROCESS_NAMES,
        );
        $bestowalTemplateIds = self::resolveIdsByName(
            $connection,
            'awards_bestowal_todo_templates',
            self::BESTOWAL_TEMPLATE_NAMES,
        );

        $connection->transactional(
            static function (Connection $connection) use (
                $catalog,
                $approvalProcessIds,
                $bestowalTemplateIds,
            ): void {
                foreach ($catalog['awards'] as $award) {
                    $statement = $connection->update(
                        'awards_awards',
                        [
                            'is_active' => $award['is_active'] ? 1 : 0,
                            'approval_process_id' => $approvalProcessIds[(int)$award['approval_process_id']],
                            'bestowal_todo_template_id' =>
                                $bestowalTemplateIds[(int)$award['bestowal_todo_template_id']],
                        ],
                        ['id' => (int)$award['target_id']],
                    );
                    if ($statement->rowCount() !== 1) {
                        throw new RuntimeException(sprintf(
                            'Unable to reconcile test seed award ID %d.',
                            $award['target_id'],
                        ));
                    }
                }
            },
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadCatalog(): array
    {
        $path = dirname(__DIR__, 3) . '/scripts/seed/data/ansteorra-award-catalog.json';
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read managed award catalog at {$path}.");
        }
        $catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($catalog)) {
            throw new RuntimeException("Managed award catalog at {$path} is not an object.");
        }

        return $catalog;
    }

    private static function replayDataMigrations(string $connectionName): void
    {
        $appRoot = dirname(__DIR__, 3);
        require_once $appRoot
            . '/plugins/Awards/config/Migrations/20260604184500_SeedDefaultAwardApprovalProcesses.php';
        require_once $appRoot
            . '/plugins/Awards/config/Migrations/20260626120000_SeedBestowalTodoTemplates.php';

        $environment = new Environment('test-seed-replay', ['connection' => $connectionName]);
        (new SeedDefaultAwardApprovalProcesses(20260604184500))
            ->setAdapter($environment->getAdapter())
            ->up();
        (new SeedBestowalTodoTemplates(20260626120000))
            ->setAdapter($environment->getAdapter())
            ->up();
    }

    /**
     * @param array<int, string> $names
     * @return array<int, int>
     */
    private static function resolveIdsByName(Connection $connection, string $table, array $names): array
    {
        $ids = [];
        foreach ($names as $catalogId => $name) {
            $row = $connection->execute(
                "SELECT id FROM {$table} WHERE name = ? AND deleted IS NULL",
                [$name],
            )->fetch('assoc');
            if (!is_array($row)) {
                throw new RuntimeException("Missing test seed dependency {$table}.{$name}.");
            }
            $ids[$catalogId] = (int)$row['id'];
        }

        return $ids;
    }
}
