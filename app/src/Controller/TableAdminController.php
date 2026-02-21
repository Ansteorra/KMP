<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\KmpIdentityInterface;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\ForbiddenException;
use Exception;

/**
 * Simple database administration interface for super users.
 */
class TableAdminController extends AppController
{
    public function index(): void
    {
        $this->request->allowMethod(['get', 'post']);
        $this->authorizeCurrentUrl();
        $this->requireSuperUser();

        $connection = ConnectionManager::get('default');
        $schemaCollection = $connection->getSchemaCollection();

        $tables = $schemaCollection->listTables();
        sort($tables);

        $selectedTable = trim((string)$this->request->getQuery('table', ''));
        $page = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(1, min(200, (int)$this->request->getQuery('limit', 50)));

        if ($this->request->is('post')) {
            $selectedTable = trim((string)$this->request->getData('selected_table', $selectedTable));
            $page = max(1, (int)$this->request->getData('selected_page', $page));
            $limit = max(1, min(200, (int)$this->request->getData('selected_limit', $limit)));
        }

        $sqlInput = '';
        $sqlMessage = null;
        $sqlError = null;
        $sqlHasResultSet = false;
        $sqlResultColumns = [];
        $sqlResultRows = [];
        $sqlTruncated = false;
        $sqlMaxRows = 500;

        if ($this->request->is('post')) {
            $sqlInput = trim((string)$this->request->getData('sql_query', ''));
            if ($sqlInput === '') {
                $sqlError = __('SQL query is required.');
            } else {
                $confirmationRequiredType = $this->getConfirmationRequiredType($sqlInput);
                $mutationType = $this->getMutationType($sqlInput);
                $mutationConfirmed = filter_var(
                    $this->request->getData('confirm_mutation', false),
                    FILTER_VALIDATE_BOOLEAN,
                );

                if ($confirmationRequiredType !== null && !$mutationConfirmed) {
                    $sqlError = __('Confirmation required before running {0} statements.', $confirmationRequiredType);
                } else {
                    try {
                        if ($mutationType !== null) {
                            $connection->begin();
                        }

                        $statement = $connection->execute($sqlInput);

                        if ($mutationType !== null) {
                            $connection->commit();
                        }
                        if ($statement->columnCount() > 0) {
                            $sqlHasResultSet = true;
                            $rowCount = 0;
                            while (($row = $statement->fetch('assoc')) !== false) {
                                $rowCount++;
                                if ($rowCount > $sqlMaxRows) {
                                    $sqlTruncated = true;
                                    break;
                                }
                                $sqlResultRows[] = $row;
                            }
                            if (!empty($sqlResultRows)) {
                                $sqlResultColumns = array_keys($sqlResultRows[0]);
                            }
                            $sqlMessage = __('Query executed successfully. Returned {0} row(s).', count($sqlResultRows));
                            if ($sqlTruncated) {
                                $sqlMessage .= ' ' . __('Results were limited to the first {0} row(s).', $sqlMaxRows);
                            }
                        } else {
                            $sqlMessage = __('Statement executed successfully. {0} row(s) affected.', (int)$statement->rowCount());
                            if ($mutationType !== null) {
                                $sqlMessage .= ' ' . __('{0} was wrapped in a transaction.', $mutationType);
                            }
                        }
                    } catch (Exception $e) {
                        if ($connection->inTransaction()) {
                            $connection->rollback();
                        }
                        $sqlError = $e->getMessage();
                    }
                }

                $tables = $schemaCollection->listTables();
                sort($tables);
            }
        }

        $tableColumns = [];
        $tableRows = [];
        $tableError = null;
        $totalRows = 0;
        $totalPages = 1;

        if ($selectedTable !== '') {
            if (!in_array($selectedTable, $tables, true)) {
                $tableError = __('Unknown table selected.');
                $selectedTable = '';
            } else {
                try {
                    $snapshot = $this->loadTableSnapshot($connection, $selectedTable, $page, $limit);
                    $tableColumns = $snapshot['columns'];
                    $tableRows = $snapshot['rows'];
                    $totalRows = $snapshot['totalRows'];
                    $totalPages = max(1, (int)ceil($totalRows / $limit));
                    if ($page > $totalPages) {
                        $page = $totalPages;
                        $snapshot = $this->loadTableSnapshot($connection, $selectedTable, $page, $limit);
                        $tableRows = $snapshot['rows'];
                    }
                } catch (Exception $e) {
                    $tableError = $e->getMessage();
                }
            }
        }

        $this->set(compact(
            'tables',
            'selectedTable',
            'page',
            'limit',
            'totalRows',
            'totalPages',
            'tableColumns',
            'tableRows',
            'tableError',
            'sqlInput',
            'sqlMessage',
            'sqlError',
            'sqlHasResultSet',
            'sqlResultColumns',
            'sqlResultRows',
            'sqlTruncated',
            'sqlMaxRows',
        ));
    }

    /**
     * @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>, totalRows: int}
     */
    private function loadTableSnapshot(Connection $connection, string $tableName, int $page, int $limit): array
    {
        $schema = $connection->getSchemaCollection()->describe($tableName);
        $columns = $schema->columns();
        $quotedTable = $connection->getDriver()->quoteIdentifier($tableName);

        $countResult = $connection->execute("SELECT COUNT(*) AS row_count FROM {$quotedTable}")->fetch('assoc') ?: [];
        $totalRows = (int)($countResult['row_count'] ?? array_values($countResult)[0] ?? 0);

        $offset = max(0, ($page - 1) * $limit);
        $rows = $connection
            ->execute("SELECT * FROM {$quotedTable} LIMIT {$limit} OFFSET {$offset}")
            ->fetchAll('assoc') ?: [];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totalRows' => $totalRows,
        ];
    }

    private function requireSuperUser(): void
    {
        $identity = $this->request->getAttribute('identity');
        if (!$identity instanceof KmpIdentityInterface || !$identity->isSuperUser()) {
            throw new ForbiddenException(__('Only super users can access Table Admin.'));
        }
    }

    private function getMutationType(string $sql): ?string
    {
        if (!preg_match('/^\s*(insert|update|delete)\b/i', $sql, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    private function getConfirmationRequiredType(string $sql): ?string
    {
        if (!preg_match('/^\s*(insert|update|delete|truncate)\b/i', $sql, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }
}
