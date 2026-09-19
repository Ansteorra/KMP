<?php
declare(strict_types=1);

namespace Activities\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Cake\Database\Driver\Postgres;
use Cake\Database\Exception\QueryException;

/**
 * Exercise the operator SQL against isolated copies of the application's PostgreSQL tables.
 */
class MigratedAuthorizationRepairTest extends BaseTestCase
{
    private array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertInstanceOf(Postgres::class, $this->connection->getDriver(), 'This repair suite requires PostgreSQL.');
        foreach (
            [
            'workflow_instances', 'workflow_versions', 'workflow_approvals', 'workflow_approval_responses',
            'workflow_execution_logs', 'activities_authorizations', 'activities_activities', 'member_roles',
            ] as $table
        ) {
            $this->connection->execute("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING DEFAULTS INCLUDING IDENTITY) ON COMMIT DROP");
            $this->connection->execute("ALTER TABLE $table ALTER COLUMN created SET DEFAULT CURRENT_TIMESTAMP");
        }
        $this->connection->execute("INSERT INTO workflow_versions (id,workflow_definition_id,version_number,definition,status)
            VALUES (1,1,1,:definition,'published')", ['definition' => json_encode(['nodes' => [
                'activate-authorization' => ['config' => ['params' => [
                    'authorizationId' => '$.nodes.validate-request.result.authorizationId',
                ]]],
            ]])]);
        $this->connection->execute('INSERT INTO activities_activities SELECT * FROM public.activities_activities ORDER BY id LIMIT 1');
        $this->connection->execute("UPDATE activities_activities SET id=1, name='Repair test', term_length=48, grants_role_id=NULL");
        $this->manifest = [
            'database' => $this->connection->execute('SELECT current_database() AS name')->fetch('assoc')['name'],
            'workflows' => [],
        ];
        for ($i = 1; $i <= 46; $i++) {
            $complete = $i <= 2;
            $ctx = ['migrated' => true, 'trigger' => [
                'authorizationId' => $i, 'memberId' => $i, 'activityId' => 1,
            ], 'resumeData' => ['approverId' => self::ADMIN_MEMBER_ID], 'retained' => 'original'];
            $this->connection->execute('INSERT INTO workflow_instances
                (id,workflow_definition_id,workflow_version_id,entity_type,entity_id,status,context,active_nodes)
                VALUES (:id,1,1,\'Activities.Authorizations\',:id,:status,:context,:nodes)', [
                    'id' => $i, 'status' => $complete ? 'completed' : 'waiting', 'context' => json_encode($ctx),
                    'nodes' => $complete ? '[]' : '["approval-gate"]',
                ]);
            $this->connection->execute("INSERT INTO activities_authorizations (id,member_id,activity_id,status,approval_count)
                VALUES (:id,:id,1,'Pending',0)", ['id' => $i]);
            $this->connection->execute('INSERT INTO workflow_approvals
                (id,workflow_instance_id,node_id,execution_log_id,status,required_count,approved_count,approver_type,approver_config)
                VALUES (:id,:id,\'approval-gate\',:id,:status,1,:count,\'member\',\'{}\')', [
                    'id' => $i, 'status' => $complete ? 'approved' : 'pending', 'count' => $complete ? 1 : 0,
                ]);
            $item = ['workflow_id' => $i, 'authorization_id' => $i];
            if ($complete) {
                $this->connection->execute("INSERT INTO workflow_approval_responses
                    (id,workflow_approval_id,member_id,decision,responded_at,comment)
                    VALUES (:id,:id,:actor,'approve','2026-07-20 00:17:26.308957','Preserved decision')", [
                        'id' => $i, 'actor' => self::ADMIN_MEMBER_ID,
                    ]);
                $this->connection->execute("INSERT INTO workflow_execution_logs
                    (workflow_instance_id,node_id,node_type,status,output_data)
                    VALUES (:id,'activate-authorization','action','completed','{\"activated\":false}')", ['id' => $i]);
                $item['approval'] = [
                    'approval_id' => $i, 'response_id' => $i, 'member_id' => $i, 'activity_id' => 1,
                    'approver_id' => self::ADMIN_MEMBER_ID, 'responded_at' => '2026-07-20 00:17:26.308957',
                    'term_months' => 48,
                ];
            }
            $this->manifest['workflows'][] = $item;
        }
        $this->connection->execute("INSERT INTO activities_authorizations
            (id,member_id,activity_id,status,start_on,expires_on,approval_count)
            VALUES (100,2,1,'Approved','2022-11-23','2026-11-23',1)");
        $this->manifest['workflows'][1]['approval']['predecessor'] = [
            'id' => 100, 'start_on' => '2022-11-23 00:00:00', 'expires_on' => '2026-11-23 00:00:00',
        ];
        $this->manifest['workflows'] = array_reverse($this->manifest['workflows']);
    }

    private function repair(bool $apply): void
    {
        $this->connection->execute("SELECT set_config('kmp.authorization_repair_manifest',:manifest,true)", [
            'manifest' => json_encode($this->manifest),
        ]);
        $this->connection->execute("SELECT set_config('kmp.authorization_repair_apply',:mode,true)", [
            'mode' => $apply ? 'on' : 'off',
        ]);
        $this->connection->execute(file_get_contents(ROOT . '/plugins/Activities/config/repairs/migrated-authorizations.sql'));
    }

    public function testPreviewDoesNotWriteAndApplyPreservesEvidenceAndIsIdempotent(): void
    {
        $before = $this->connection->execute('SELECT * FROM workflow_approval_responses ORDER BY id')->fetchAll('assoc');
        $this->repair(false);
        $this->assertSame(46, (int)$this->connection->execute("SELECT count(*) AS n FROM activities_authorizations WHERE status='Pending'")->fetch('assoc')['n']);
        $this->assertSame(2, (int)$this->connection->execute('SELECT count(*) AS n FROM workflow_execution_logs')->fetch('assoc')['n']);
        $this->repair(true);
        $auth = $this->connection->execute('SELECT * FROM activities_authorizations WHERE id=2')->fetch('assoc');
        $this->assertSame('Approved', $auth['status']);
        $this->assertSame('2026-07-20 00:17:26', $auth['start_on']);
        $this->assertSame('2030-07-20 00:17:26', $auth['expires_on']);
        $this->assertSame(1, (int)$auth['approval_count']);
        $old = $this->connection->execute('SELECT * FROM activities_authorizations WHERE id=100')->fetch('assoc');
        $this->assertSame('Replaced', $old['status']);
        $this->assertSame('2026-07-20 00:17:25', $old['expires_on']);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$old['revoker_id']);
        $waiting = $this->connection->execute('SELECT context,status FROM workflow_instances WHERE id=46')->fetch('assoc');
        $this->assertSame('waiting', $waiting['status']);
        $ctx = json_decode($waiting['context'], true);
        $this->assertSame(46, $ctx['nodes']['validate-request']['result']['authorizationId']);
        $this->assertSame('original', $ctx['retained']);
        $this->assertSame($before, $this->connection->execute('SELECT * FROM workflow_approval_responses ORDER BY id')->fetchAll('assoc'));
        $this->repair(true);
        $this->assertSame(48, (int)$this->connection->execute('SELECT count(*) AS n FROM workflow_execution_logs')->fetch('assoc')['n']);
    }

    public function testChangedApprovalEvidenceAbortsWholeRepair(): void
    {
        $this->connection->execute('UPDATE workflow_approval_responses SET member_id=member_id+1 WHERE id=2');
        $this->connection->execute('SAVEPOINT repair_check');
        try {
            $this->repair(true);
            $this->fail('Changed evidence must abort');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Approval evidence changed', $e->getMessage());
            $this->connection->execute('ROLLBACK TO SAVEPOINT repair_check');
        }
        $this->assertSame(46, (int)$this->connection->execute("SELECT count(*) AS n FROM activities_authorizations WHERE status='Pending'")->fetch('assoc')['n']);
    }

    public function testChangedWaitingWorkflowDoesNotPartiallyRepairEarlierRows(): void
    {
        $this->connection->execute("UPDATE workflow_instances SET status='cancelled' WHERE id=46");
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Expected waiting approval');
        $this->repair(true);
    }

    public function testUnexpectedRoleGrantAborts(): void
    {
        $this->connection->execute('UPDATE activities_activities SET grants_role_id=1 WHERE id=1');
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Unexpected role or term effects');
        $this->repair(true);
    }

    public function testWriteFailureRollsBackActivationContextAndAuditTogether(): void
    {
        $this->connection->execute("ALTER TABLE workflow_execution_logs ADD CONSTRAINT reject_last_audit
            CHECK (workflow_instance_id <> 1 OR node_id <> 'authorization-id-repair-2026-09')");
        $this->connection->execute('SAVEPOINT repair_write_check');
        try {
            $this->repair(true);
            $this->fail('Audit failure must abort the repair');
        } catch (QueryException $e) {
            $this->assertStringContainsString('reject_last_audit', $e->getMessage());
            $this->connection->execute('ROLLBACK TO SAVEPOINT repair_write_check');
        }
        $this->assertSame(46, (int)$this->connection->execute("SELECT count(*) AS n FROM activities_authorizations WHERE status='Pending'")->fetch('assoc')['n']);
        $this->assertSame(2, (int)$this->connection->execute('SELECT count(*) AS n FROM workflow_execution_logs')->fetch('assoc')['n']);
        $this->assertSame('Approved', $this->connection->execute('SELECT status FROM activities_authorizations WHERE id=100')->fetch('assoc')['status']);
    }

    public function testWrongDatabaseAborts(): void
    {
        $this->manifest['database'] .= '-different-tenant';
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Repair database does not match');
        $this->repair(true);
    }
}
