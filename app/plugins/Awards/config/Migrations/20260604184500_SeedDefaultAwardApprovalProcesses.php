<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class SeedDefaultAwardApprovalProcesses extends BaseMigration
{
    private const SINGLE_CROWN_PROCESS = 'Single Approver - Crown';
    private const SINGLE_LOCAL_PROCESS = 'Single Approver - Local';
    private const SINGLE_PRINCIPALITY_PROCESS = 'Single Approver - Principality Coronet';
    private const LOCAL_THEN_CROWN_PROCESS = 'Dual Approver - Local then Crown';
    private const PRINCIPALITY_DOMAIN_ID = 11;

    /**
     * Seed the default award approval process assignments.
     *
     * @return void
     */
    public function up(): void
    {
        $ansteorraBranchId = $this->lookupOptionalId(
            'branches',
            "name = 'Ansteorra' AND type = 'Kingdom'",
        );
        $nonArmigerousLevelId = $this->lookupOptionalId(
            'awards_levels',
            "name = 'Non-Armigerous'",
        );
        $crownOfficeId = $this->lookupOptionalId(
            'officers_offices',
            "name = 'Crown'",
        );
        $landedNobilityOfficeId = $this->lookupOptionalId(
            'officers_offices',
            "name = 'Landed Nobility'",
        );
        $principalityCoronetOfficeId = $this->lookupOptionalId(
            'officers_offices',
            "name = 'Principality Coronet'",
        );

        if (
            $ansteorraBranchId === null
            || $nonArmigerousLevelId === null
            || $crownOfficeId === null
            || $landedNobilityOfficeId === null
            || $principalityCoronetOfficeId === null
        ) {
            return;
        }

        $singleCrownProcessId = $this->upsertProcess(
            self::SINGLE_CROWN_PROCESS,
            'Single approval queue for kingdom awards. Any current Crown office holder may approve.',
        );
        $singleLocalProcessId = $this->upsertProcess(
            self::SINGLE_LOCAL_PROCESS,
            'Single local approval queue for non-armigerous local awards.',
        );
        $singlePrincipalityProcessId = $this->upsertProcess(
            self::SINGLE_PRINCIPALITY_PROCESS,
            'Single approval queue for principality awards. Any current Coronet office holder may approve.',
        );
        $localThenCrownProcessId = $this->upsertProcess(
            self::LOCAL_THEN_CROWN_PROCESS,
            'Local approval followed by Crown approval for armigerous local awards.',
        );

        $this->replaceSteps($singleCrownProcessId, [
            [
                'step_key' => 'crown',
                'label' => 'Crown Approval',
                'sequence' => 1,
                'approver_source_id' => $crownOfficeId,
                'branch_mode' => 'award_branch',
                'branch_type' => null,
                'threshold_mode' => 'all',
            ],
        ]);
        $this->replaceSteps($singleLocalProcessId, [
            [
                'step_key' => 'local',
                'label' => 'Local Approval',
                'sequence' => 1,
                'approver_source_id' => $landedNobilityOfficeId,
                'branch_mode' => 'award_branch',
                'branch_type' => null,
                'threshold_mode' => 'all',
            ],
        ]);
        $this->replaceSteps($singlePrincipalityProcessId, [
            [
                'step_key' => 'principality',
                'label' => 'Principality Coronet Approval',
                'sequence' => 1,
                'approver_source_id' => $principalityCoronetOfficeId,
                'branch_mode' => 'award_branch',
                'branch_type' => null,
                'threshold_mode' => 'all',
            ],
        ]);
        $this->replaceSteps($localThenCrownProcessId, [
            [
                'step_key' => 'local',
                'label' => 'Local Approval',
                'sequence' => 1,
                'approver_source_id' => $landedNobilityOfficeId,
                'branch_mode' => 'award_branch',
                'branch_type' => null,
                'threshold_mode' => 'all',
            ],
            [
                'step_key' => 'crown',
                'label' => 'Crown Approval',
                'sequence' => 2,
                'approver_source_id' => $crownOfficeId,
                'branch_mode' => 'ancestor_branch_type',
                'branch_type' => 'Kingdom',
                'threshold_mode' => 'all',
            ],
        ]);

        $this->assignAwards(
            $ansteorraBranchId,
            $nonArmigerousLevelId,
            $singleCrownProcessId,
            $singleLocalProcessId,
            $singlePrincipalityProcessId,
            $localThenCrownProcessId,
        );
    }

    /**
     * Remove the default process seeds and clear assignments that still point to them.
     *
     * @return void
     */
    public function down(): void
    {
        $names = $this->quotedProcessNames();
        $this->execute(
            'UPDATE awards_awards SET approval_process_id = NULL '
                . "WHERE approval_process_id IN (SELECT id FROM awards_approval_processes WHERE name IN ({$names}))",
        );
        $this->execute("DELETE FROM awards_approval_processes WHERE name IN ({$names})");
    }

    /**
     * @param string $table Table name.
     * @param string $where SQL where clause.
     * @param string $description Human-readable lookup description.
     * @return int
     */
    private function lookupOptionalId(string $table, string $where): ?int
    {
        $row = $this->fetchRow("SELECT id FROM {$table} WHERE {$where} AND deleted IS NULL LIMIT 1");
        if ($row === false) {
            return null;
        }

        return (int)$row['id'];
    }

    /**
     * @param string $name Process name.
     * @param string $description Process description.
     * @return int
     */
    private function upsertProcess(string $name, string $description): int
    {
        $now = date('Y-m-d H:i:s');
        $nameSql = $this->quoteSql($name);
        $descriptionSql = $this->quoteSql($description);
        $row = $this->fetchRow("SELECT id FROM awards_approval_processes WHERE name = {$nameSql} LIMIT 1");

        if ($row === false) {
            $this->table('awards_approval_processes')->insert([
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'created' => $now,
                'modified' => $now,
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => null,
            ])->saveData();

            $row = $this->fetchRow("SELECT id FROM awards_approval_processes WHERE name = {$nameSql} LIMIT 1");
            if ($row === false) {
                throw new RuntimeException("Unable to seed award approval process {$name}.");
            }

            return (int)$row['id'];
        }

        $this->execute(
            'UPDATE awards_approval_processes '
                . "SET description = {$descriptionSql}, is_active = TRUE, modified = '{$now}', modified_by = 1, "
                . 'deleted = NULL WHERE id = ' . (int)$row['id'],
        );

        return (int)$row['id'];
    }

    /**
     * @param int $processId Approval process ID.
     * @param array<int, array<string, mixed>> $steps Step configs.
     * @return void
     */
    private function replaceSteps(int $processId, array $steps): void
    {
        $this->execute("DELETE FROM awards_approval_process_steps WHERE approval_process_id = {$processId}");

        $now = date('Y-m-d H:i:s');
        $stepsTable = $this->table('awards_approval_process_steps');
        foreach ($steps as $step) {
            $stepsTable->insert([
                'approval_process_id' => $processId,
                'step_key' => $step['step_key'],
                'label' => $step['label'],
                'sequence' => $step['sequence'],
                'step_type' => 'approval',
                'approver_type' => 'office',
                'approver_source_id' => $step['approver_source_id'],
                'approver_source_key' => null,
                'branch_mode' => $step['branch_mode'],
                'branch_type' => $step['branch_type'],
                'threshold_mode' => $step['threshold_mode'],
                'required_count' => null,
                'on_reject' => 'return_previous',
                'on_request_changes' => 'return_previous',
                'retain_read_visibility' => true,
                'created' => $now,
                'modified' => $now,
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => null,
            ]);
        }
        $stepsTable->saveData();
    }

    /**
     * @param int $ansteorraBranchId Ansteorra branch ID.
     * @param int $nonArmigerousLevelId Non-armigerous award level ID.
     * @param int $singleCrownProcessId Crown process ID.
     * @param int $singleLocalProcessId Local process ID.
     * @param int $singlePrincipalityProcessId Principality process ID.
     * @param int $localThenCrownProcessId Local then Crown process ID.
     * @return void
     */
    private function assignAwards(
        int $ansteorraBranchId,
        int $nonArmigerousLevelId,
        int $singleCrownProcessId,
        int $singleLocalProcessId,
        int $singlePrincipalityProcessId,
        int $localThenCrownProcessId,
    ): void {
        $this->execute(
            "UPDATE awards_awards SET approval_process_id = {$singleCrownProcessId} "
                . "WHERE branch_id = {$ansteorraBranchId} AND deleted IS NULL",
        );
        $this->execute(
            "UPDATE awards_awards SET approval_process_id = {$singlePrincipalityProcessId} "
                . 'WHERE domain_id = ' . self::PRINCIPALITY_DOMAIN_ID . ' '
                . 'AND deleted IS NULL',
        );
        $this->execute(
            "UPDATE awards_awards SET approval_process_id = {$singleLocalProcessId} "
                . "WHERE branch_id <> {$ansteorraBranchId} "
                . 'AND (domain_id IS NULL OR domain_id <> ' . self::PRINCIPALITY_DOMAIN_ID . ') '
                . "AND level_id = {$nonArmigerousLevelId} AND deleted IS NULL",
        );
        $this->execute(
            "UPDATE awards_awards SET approval_process_id = {$localThenCrownProcessId} "
                . "WHERE branch_id <> {$ansteorraBranchId} "
                . 'AND (domain_id IS NULL OR domain_id <> ' . self::PRINCIPALITY_DOMAIN_ID . ') '
                . "AND level_id <> {$nonArmigerousLevelId} AND deleted IS NULL",
        );
    }

    /**
     * @return string SQL list of seeded process names.
     */
    private function quotedProcessNames(): string
    {
        return implode(', ', array_map(
            fn(string $name): string => $this->quoteSql($name),
            [
                self::SINGLE_CROWN_PROCESS,
                self::SINGLE_LOCAL_PROCESS,
                self::SINGLE_PRINCIPALITY_PROCESS,
                self::LOCAL_THEN_CROWN_PROCESS,
            ],
        ));
    }

    /**
     * @param string $value Value to quote.
     * @return string SQL string literal.
     */
    private function quoteSql(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
