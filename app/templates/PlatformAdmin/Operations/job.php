<?php
declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * @var \App\View\AppView $this
 * @var array<string, mixed> $job
 * @var list<array<string, mixed>> $events
 * @var string $retryNonce
 * @var bool $canRetry
 */
$this->assign('title', __('Platform Job'));
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
    <div>
        <p class="ops-panel__kicker mb-1"><?= __('Execution record') ?></p>
        <h1 class="display-6 fw-bold mb-1"><?= h(str_replace('_', ' ', (string)$job['job_type'])) ?></h1>
        <p class="text-muted mb-0"><?= h($job['tenant_slug'] ?? __('platform scope')) ?> · <?= h((string)$job['id']) ?></p>
    </div>
    <?= $this->Html->link(__('Back to jobs'), ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'jobs'], ['class' => 'btn btn-outline-secondary']) ?>
</div>

<section class="ops-stat-grid mb-4" aria-label="<?= __('Job timing and status') ?>">
    <article class="ops-stat ops-stat--fleet">
        <div class="ops-stat__label"><?= __('Status') ?></div>
        <div class="mt-3"><span class="ops-risk <?= ($job['status'] ?? '') === 'completed' ? 'ops-risk--healthy' : 'ops-risk--critical' ?>"><?= h($job['status'] ?? '') ?></span></div>
        <p class="ops-stat__detail mt-3"><?= !empty($job['has_error']) ? __('A sanitized failure event should appear below.') : __('No failure marker is recorded.') ?></p>
    </article>
    <article class="ops-stat">
        <div class="ops-stat__label"><?= __('Queued') ?></div>
        <div class="fs-5 fw-bold mt-3"><?= h($job['created_at'] ?? __('unknown')) ?></div>
    </article>
    <article class="ops-stat ops-stat--traffic">
        <div class="ops-stat__label"><?= __('Started') ?></div>
        <div class="fs-5 fw-bold mt-3"><?= h($job['started_at'] ?? __('not started')) ?></div>
    </article>
    <article class="ops-stat ops-stat--protection">
        <div class="ops-stat__label"><?= __('Finished') ?></div>
        <div class="fs-5 fw-bold mt-3"><?= h($job['finished_at'] ?? __('not finished')) ?></div>
    </article>
</section>

<div class="row g-4">
    <section class="col-12 col-xl-7" aria-labelledby="job-events-heading">
        <div class="ops-panel h-100">
            <div class="ops-panel__header">
                <div>
                    <p class="ops-panel__kicker mb-1"><?= __('Sanitized operator log') ?></p>
                    <h2 id="job-events-heading" class="h4 mb-0"><?= __('Progress timeline') ?></h2>
                </div>
            </div>
            <ol class="list-group list-group-flush list-group-numbered">
                <?php foreach ($events as $event) : ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start gap-3 py-3">
                        <div>
                            <strong><?= h($event['message'] ?? '') ?></strong>
                            <div class="small text-muted"><?= h($event['created_at'] ?? '') ?> · <?= h(str_replace('.', ' ', (string)($event['event_code'] ?? ''))) ?></div>
                        </div>
                        <span class="ops-inline-state <?= ($event['event_level'] ?? '') === 'error' ? 'ops-inline-state--bad' : 'ops-inline-state--good' ?>"><?= h($event['event_level'] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if ($events === []) : ?>
                    <li class="ops-empty list-group-item"><?= __('No progress events were recorded. Jobs created before operational event tracking may not have a timeline.') ?></li>
                <?php endif; ?>
            </ol>
        </div>
    </section>

    <aside class="col-12 col-xl-5" aria-labelledby="job-retry-heading">
        <div class="ops-panel">
            <div class="ops-panel__header">
                <div>
                    <p class="ops-panel__kicker mb-1"><?= __('Recovery control') ?></p>
                    <h2 id="job-retry-heading" class="h4 mb-0"><?= __('Retry failed operation') ?></h2>
                </div>
            </div>
            <div class="p-3">
                <?php if ($canRetry) : ?>
                    <p><?= __('A retry creates a new audited job with the same safe parameters. Existing records are never overwritten.') ?></p>
                    <?= $this->Form->create(null, [
                        'url' => ['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'retryJob', $job['id']],
                    ]) ?>
                    <?= $this->Form->hidden('nonce', ['value' => $retryNonce]) ?>
                    <?= $this->Form->control('confirmation', ['label' => __('Type RETRY job')]) ?>
                    <?= $this->Form->control('reason', ['label' => __('Operator reason'), 'required' => true]) ?>
                    <?= $this->Form->control('totp', ['label' => __('MFA code'), 'autocomplete' => 'one-time-code']) ?>
                    <?= $this->Form->button(__('Queue new attempt'), ['class' => 'btn btn-danger']) ?>
                    <?= $this->Form->end() ?>
                <?php else : ?>
                    <p class="text-muted mb-0"><?= __('Only failed jobs implemented by the platform worker can be retried from this portal.') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>
