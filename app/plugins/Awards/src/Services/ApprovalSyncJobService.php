<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Services\AuthorizationService;
use Authorization\Policy\OrmResolver;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Throwable;

/** Bounded, resumable tenant-queue coordination; each restart and its result commit together. */
class ApprovalSyncJobService
{
    use LocatorAwareTrait;

    /** Initialize collaborators. */
    public function __construct(private RecommendationApprovalWorkflowSyncService $sync)
    {
    }

    /** Enqueue or return the active process synchronization. */
    public function enqueue(int $processId, int $actorId): EntityInterface
    {
        $runs = $this->fetchTable('Awards.ApprovalSyncRuns');

        return $runs->getConnection()->transactional(function () use ($runs, $processId, $actorId) {
            $this->fetchTable('Awards.ApprovalProcesses')->find()->where(['id' => $processId])
                ->epilog('FOR UPDATE')->firstOrFail();
            $active = $runs->find()->where([
                'approval_process_id' => $processId,
                'status IN' => ['queued', 'running'],
            ])->first();
            if ($active !== null) {
                if (!$this->hasExhaustedJob((int)$active->id)) {
                    return $active;
                }
                $this->stop($active, 'Worker retries exhausted. A new synchronization was requested.');
            }
            $run = $runs->newEntity([
                'approval_process_id' => $processId, 'actor_id' => $actorId,
                'fingerprint' => $this->fingerprint($processId), 'status' => 'queued',
                'cursor' => 0, 'discovery_complete' => false,
            ]);
            $runs->saveOrFail($run);
            $this->queue(['runId' => (int)$run->id, 'cursor' => 0]);

            return $run;
        });
    }

    /** Each queue delivery processes one page or one recommendation, never the whole run. */
    public function work(array $data): void
    {
        $runs = $this->fetchTable('Awards.ApprovalSyncRuns');
        $connection = $runs->getConnection();
        $savePointsWereEnabled = $connection->isSavePointsEnabled();
        $connection->enableSavePoints();
        try {
            $connection->transactional(function () use ($data, $runs): void {
                $run = $runs->find()->where(['id' => (int)$data['runId']])->epilog('FOR UPDATE')->firstOrFail();
                if (!in_array($run->status, ['queued', 'running'], true)) {
                    return;
                }
                $process = $this->fetchTable('Awards.ApprovalProcesses')->get((int)$run->approval_process_id);
                $actor = $this->fetchTable('Members')->get((int)$run->actor_id);
                $authorization = new AuthorizationService(new OrmResolver());
                $actor->setAuthorization($authorization);
                if (!$authorization->checkCan($actor, 'syncOpenRecommendations', $process)) {
                    $this->stop($run, 'Synchronization stopped because the requesting member '
                        . 'no longer has permission.');

                    return;
                }
                if (!hash_equals($run->fingerprint, $this->fingerprint((int)$process->id))) {
                    $this->stop($run, 'Configuration changed. Start a new synchronization for the '
                    . 'remaining recommendations.');

                    return;
                }
                $run->status = 'running';
                $items = $this->fetchTable('Awards.ApprovalSyncItems');
                if (isset($data['itemId'])) {
                    $item = $items->find()->where(['id' => (int)$data['itemId'], 'sync_run_id' => $run->id])
                    ->epilog('FOR UPDATE')->firstOrFail();
                    if ($item->status !== 'pending') {
                        return;
                    }
                    try {
                        $outcome = $this->sync->restartRecommendation(
                            (int)$item->recommendation_id,
                            (int)$process->id,
                            $item->expected_run_ids,
                            (int)$actor->id,
                        );
                        $item->status = ($outcome['status'] ?? '') === 'restarted' ? 'restarted' : 'skipped';
                        $item->message = $item->status === 'skipped' ? 'No longer eligible or already current.' : null;
                    } catch (Throwable $exception) {
                        Log::error(sprintf(
                            'Approval sync run %d recommendation %d failed: %s',
                            $run->id,
                            $item->recommendation_id,
                            $exception->getMessage(),
                        ));
                        $item->status = 'failed';
                        $item->message = 'Approval restart failed; original workflow preserved. Review server logs.';
                    }
                    $items->saveOrFail($item);
                } elseif (!$run->discovery_complete && (int)$run->cursor === (int)($data['cursor'] ?? -1)) {
                    $ids = $this->fetchTable('Awards.Recommendations')->find()
                    ->innerJoinWith('Awards', fn($query) => $query->where([
                        'Awards.approval_process_id' => $process->id,
                    ]))
                    ->where(['Recommendations.id >' => $run->cursor])
                    ->select(['Recommendations.id'])->orderByAsc('Recommendations.id')->limit(100)
                    ->all()->extract('id')->toList();
                    $candidates = $this->sync->findOutdatedRecommendationRunMap((int)$process->id, $ids);
                    foreach ($candidates as $id => $expected) {
                        $item = $items->newEntity(['sync_run_id' => $run->id, 'recommendation_id' => $id,
                        'expected_run_ids' => $expected, 'status' => 'pending']);
                        $items->saveOrFail($item);
                        $this->queue(['runId' => (int)$run->id, 'itemId' => (int)$item->id]);
                    }
                    $run->discovery_complete = count($ids) < 100;
                    if ($ids !== []) {
                        $run->cursor = (int)end($ids);
                    }
                    if (!$run->discovery_complete) {
                        $this->queue(['runId' => (int)$run->id, 'cursor' => (int)$run->cursor]);
                    }
                }
                if ($run->discovery_complete && !$items->exists(['sync_run_id' => $run->id, 'status' => 'pending'])) {
                    $run->status = $items->exists(['sync_run_id' => $run->id, 'status' => 'failed'])
                    ? 'partial_failure' : 'completed';
                }
                $runs->saveOrFail($run);
            });
        } finally {
            if (!$savePointsWereEnabled) {
                $connection->disableSavePoints();
            }
        }
    }

    /** Read durable progress without changing the run. */
    public function latest(int $processId, int $page = 1): array
    {
        $run = $this->fetchTable('Awards.ApprovalSyncRuns')->find()
            ->where(['approval_process_id' => $processId])->orderByDesc('id')->first();
        if ($run === null) {
            return [];
        }
        $items = $this->fetchTable('Awards.ApprovalSyncItems');
        $counts = [];
        foreach (['pending', 'restarted', 'skipped', 'failed'] as $status) {
            $counts[$status] = $items->find()->where(['sync_run_id' => $run->id, 'status' => $status])->count();
        }

        $pages = max(1, (int)ceil(array_sum($counts) / 100));
        $page = min(max(1, $page), $pages);
        $exhausted = in_array($run->status, ['queued', 'running'], true) && $this->hasExhaustedJob((int)$run->id);

        return ['id' => (int)$run->id, 'status' => $exhausted ? 'interrupted' : $run->status,
            'message' => $exhausted ? 'Worker retries exhausted. Start synchronization again to resume '
                . 'remaining work.' : $run->message,
            'discovering' => !$run->discovery_complete, 'counts' => $counts,
            'page' => $page, 'pages' => $pages,
            'attention' => $items->find()->select(['recommendation_id', 'status', 'message'])
                ->where(['sync_run_id' => $run->id])
                ->orderByAsc('id')->limit(100)->page($page)->disableHydration()->all()->toList()];
    }

    /** Stop pending work with a safe explanation. */
    private function stop(EntityInterface $run, string $message): void
    {
        $run->status = 'interrupted';
        $run->message = $message;
        $this->fetchTable('Awards.ApprovalSyncItems')->updateAll(
            ['status' => 'skipped', 'message' => $message],
            ['sync_run_id' => $run->id, 'status' => 'pending'],
        );
        $this->fetchTable('Awards.ApprovalSyncRuns')->saveOrFail($run);
    }

    /** Enqueue tenant-local work in the current transaction. */
    private function queue(array $data): void
    {
        $this->fetchTable('Queue.QueuedJobs')->createJob(
            'Awards.ApprovalSync',
            $data,
            ['reference' => 'awards-approval-sync:' . $data['runId']],
        );
    }

    /** Detect deliveries that exhausted the worker retry budget. */
    private function hasExhaustedJob(int $runId): bool
    {
        return $this->fetchTable('Queue.QueuedJobs')->exists([
            'reference' => 'awards-approval-sync:' . $runId,
            'completed IS' => null,
            // Attempts increment on fetch; allow the fourth delivery its full timeout.
            'attempts >' => 3,
            'fetched <' => DateTime::now()->subSeconds(120),
        ]);
    }

    /** Capture the process and published workflow configuration. */
    private function fingerprint(int $processId): string
    {
        $process = $this->fetchTable('Awards.ApprovalProcesses')->get($processId, contain: ['ApprovalProcessSteps']);
        $versions = $this->fetchTable('WorkflowDefinitions')->find()->select(['id', 'current_version_id'])
            ->where(['is_active' => true, 'deleted IS' => null])->orderByAsc('id')->disableHydration()->all()->toList();

        return hash('sha256', json_encode([$process->configuration_signature, $versions], JSON_THROW_ON_ERROR));
    }
}
