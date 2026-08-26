<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

/**
 * Advance date-sensitive development snapshot data to a current reference date.
 */
class AdvanceDevSeedDatesCommand extends Command
{
    /**
     * The date represented as "today" when the shared development snapshot was captured.
     */
    public const BASELINE_REFERENCE_DATE = '2026-02-16';

    /**
     * Business-effective dates that move with the development snapshot calendar.
     *
     * Audit timestamps are intentionally excluded so records do not appear to have
     * been created or modified in the future.
     *
     * @var array<string, list<string>>
     */
    private const SHIFTABLE_COLUMNS = [
        'activities_authorizations' => ['start_on', 'expires_on'],
        'awards_events' => ['start_date', 'end_date'],
        'awards_recommendation_feedback_requests' => ['deadline'],
        'gatherings' => ['start_date', 'end_date', 'published_on', 'preregister_closes_on'],
        'gathering_scheduled_activities' => ['start_datetime', 'end_datetime'],
        'member_roles' => ['start_on', 'expires_on'],
        'members' => ['membership_expires_on', 'background_check_expires_on'],
        'officers_officers' => ['start_on', 'expires_on'],
        'waivers_gathering_waiver_closures' => ['ready_to_close_at'],
        'waivers_gathering_waivers' => ['retention_date'],
        'warrant_periods' => ['start_date', 'end_date'],
        'warrants' => ['start_on', 'expires_on'],
        'workflow_approvals' => ['deadline'],
        'workflow_tasks' => ['due_date'],
    ];

    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription('Advance date-sensitive development seed data while preserving relative chronology.')
            ->addOption('as-of', [
                'help' => 'Date that should represent today after the shift (YYYY-MM-DD).',
                'default' => gmdate('Y-m-d'),
            ])
            ->addOption('reference-date', [
                'help' => 'Date represented as today in the source snapshot (YYYY-MM-DD).',
                'default' => self::BASELINE_REFERENCE_DATE,
            ])
            ->addOption('dry-run', [
                'boolean' => true,
                'default' => false,
                'help' => 'Report the calendar shift without changing data.',
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if (!(bool)Configure::read('debug')) {
            $io->error('Development seed dates can only be advanced when debug mode is enabled.');

            return Command::CODE_ERROR;
        }

        $asOf = $this->parseDate((string)$args->getOption('as-of'));
        $referenceDate = $this->parseDate((string)$args->getOption('reference-date'));
        if ($asOf === null || $referenceDate === null) {
            $io->error('The --as-of and --reference-date options must use valid YYYY-MM-DD dates.');

            return Command::CODE_ERROR;
        }

        $shiftDays = (int)$referenceDate->diff($asOf)->format('%r%a');
        if ($shiftDays < 0) {
            $io->error('The --as-of date cannot be earlier than the snapshot reference date.');

            return Command::CODE_ERROR;
        }

        $io->out(sprintf(
            'Development seed calendar: %s -> %s (%d day%s).',
            $referenceDate->format('Y-m-d'),
            $asOf->format('Y-m-d'),
            $shiftDays,
            $shiftDays === 1 ? '' : 's',
        ));

        if ((bool)$args->getOption('dry-run')) {
            $io->info('Dry run; no seed dates were changed.');

            return Command::CODE_SUCCESS;
        }

        if ($shiftDays === 0) {
            $io->success('The development seed calendar is already at the requested reference date.');

            return Command::CODE_SUCCESS;
        }

        try {
            $connection = ConnectionManager::get('default');
            if (!$connection instanceof Connection) {
                throw new RuntimeException('The default datasource is not a relational database connection.');
            }
            $updatedRows = $connection->transactional(
                fn(Connection $db): int => $this->shiftDates($db, $shiftDays),
            );
        } catch (Throwable $e) {
            $io->error('Unable to advance development seed dates: ' . $e->getMessage());

            return Command::CODE_ERROR;
        }

        $io->success(sprintf('Advanced date-sensitive seed data across %d table rows.', $updatedRows));

        return Command::CODE_SUCCESS;
    }

    /**
     * Shift effective dates in all available snapshot tables.
     *
     * @param \Cake\Database\Connection $connection Database connection.
     * @param int $shiftDays Number of days to add.
     * @return int Number of table rows updated.
     */
    private function shiftDates(Connection $connection, int $shiftDays): int
    {
        $driver = $connection->getDriver();
        if (!$driver instanceof Postgres && !$driver instanceof Mysql) {
            throw new RuntimeException('Only PostgreSQL and MySQL/MariaDB development snapshots are supported.');
        }

        $availableTables = array_fill_keys($connection->getSchemaCollection()->listTables(), true);
        $updatedRows = 0;
        foreach (self::SHIFTABLE_COLUMNS as $table => $columns) {
            if (!isset($availableTables[$table])) {
                continue;
            }

            $quotedTable = $driver->quoteIdentifier($table);
            $assignments = [];
            $conditions = [];
            foreach ($columns as $column) {
                $quotedColumn = $driver->quoteIdentifier($column);
                $assignments[] = sprintf(
                    '%1$s = %2$s',
                    $quotedColumn,
                    $driver instanceof Postgres
                        ? sprintf('%s + (%d * INTERVAL \'1 day\')', $quotedColumn, $shiftDays)
                        : sprintf('DATE_ADD(%s, INTERVAL %d DAY)', $quotedColumn, $shiftDays),
                );
                $conditions[] = sprintf('%s IS NOT NULL', $quotedColumn);
            }

            $statement = $connection->execute(sprintf(
                'UPDATE %s SET %s WHERE %s',
                $quotedTable,
                implode(', ', $assignments),
                implode(' OR ', $conditions),
            ));
            $updatedRows += $statement->rowCount();
        }

        if (isset($availableTables['warrant_rosters'])) {
            $updatedRows += $this->shiftRosterNames($connection, $shiftDays);
        }

        return $updatedRows;
    }

    /**
     * Keep ISO date ranges embedded in warrant roster names aligned with their warrants.
     *
     * @param \Cake\Database\Connection $connection Database connection.
     * @param int $shiftDays Number of days to add.
     * @return int Number of roster names updated.
     */
    private function shiftRosterNames(Connection $connection, int $shiftDays): int
    {
        $rows = $connection->execute('SELECT id, name FROM warrant_rosters')->fetchAll('assoc');
        $updated = 0;
        foreach ($rows as $row) {
            $name = (string)$row['name'];
            $shiftedName = preg_replace_callback(
                '/\b\d{4}-\d{2}-\d{2}\b/',
                function (array $matches) use ($shiftDays): string {
                    $date = $this->parseDate($matches[0]);

                    return $date === null
                        ? $matches[0]
                        : $date->modify(sprintf('+%d days', $shiftDays))->format('Y-m-d');
                },
                $name,
            );
            if ($shiftedName === null || $shiftedName === $name) {
                continue;
            }

            $connection->update('warrant_rosters', ['name' => $shiftedName], ['id' => $row['id']]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Parse an exact ISO calendar date in UTC.
     *
     * @param string $value Date value.
     * @return \DateTimeImmutable|null Parsed date, or null for invalid input.
     */
    private function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }

        return $date;
    }
}
