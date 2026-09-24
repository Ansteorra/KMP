<?php
declare(strict_types=1);

namespace App\Services;

use App\Mailer\GridSubscriptionMailer;
use App\Model\Entity\GridSubscription;
use Authorization\Exception\ForbiddenException as AuthorizationForbiddenException;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Text;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/** Claim tenant-local due subscriptions; rebuild and authorize immediately before sending. */
class GridSubscriptionService
{
    use LocatorAwareTrait;

    /** Configure report dependencies. */
    public function __construct(private readonly ?GridSubscriptionReportService $reports = null)
    {
    }

    /** Bound and normalize grid query parameters; route selection is exclusively server-owned. */
    public function normalizeQuery(string $queryString): array
    {
        if (strlen($queryString) > 12000) {
            throw new InvalidArgumentException('This view has too many filters to subscribe to.');
        }
        parse_str(ltrim($queryString, '?'), $query);
        $allowed = ['view_id', 'filter', 'search', 'sort', 'direction', 'columns', 'dirty', 'ignore_default'];
        foreach (array_keys($query) as $key) {
            if (!in_array($key, $allowed, true) && !preg_match('/^[a-zA-Z0-9_]+_(start|end)$/', $key)) {
                unset($query[$key]);
            }
        }
        foreach (['view_id', 'search', 'sort', 'direction', 'columns'] as $key) {
            if (isset($query[$key]) && !is_string($query[$key])) {
                throw new InvalidArgumentException('Invalid view parameters.');
            }
        }
        unset($query['ignore_default']);
        if (empty($query['view_id']) || $query['view_id'] === 'all') {
            $query['ignore_default'] = '1';
        }

        return $query;
    }

    /** Validate current grid access before recording the member's opt-in. */
    public function subscribe(int $memberId, array $data, string $origin): GridSubscription
    {
        [$gridKey, $name, $query] = $this->requestedView($data);
        $days = filter_var($data['intervalDays'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($days, [1, 3, 7], true)) {
            throw new InvalidArgumentException('Choose daily, every 3 days, or weekly.');
        }
        $table = $this->fetchTable('GridSubscriptions');
        if ($table->find()->where(['member_id' => $memberId])->count() >= 25) {
            throw new InvalidArgumentException('Cancel an existing subscription before adding another (maximum 25).');
        }
        $reports = $this->reports ?? throw new LogicException('Report service is required.');
        $reports->generate($memberId, $gridKey, $query, $origin);
        $subscription = $table->newEmptyEntity();
        $subscription->patch([
            'member_id' => $memberId, 'grid_key' => $gridKey, 'name' => $name,
            'query_params' => json_encode($query, JSON_THROW_ON_ERROR), 'origin' => $origin,
            'interval_days' => $days, 'status' => 'active',
            'next_run_at' => DateTime::now()->addDays($days),
        ], ['guard' => false]);

        return $table->saveOrFail($subscription);
    }

    /** Send the current authorized view to its requesting member without scheduling anything. */
    public function sendSample(int $memberId, array $data, string $origin): void
    {
        [$gridKey, $name, $query] = $this->requestedView($data);
        $reports = $this->reports ?? throw new LogicException('Report service is required.');
        $report = $reports->generate($memberId, $gridKey, $query, $origin);
        $member = $this->fetchTable('Members')->get($memberId);
        if (!filter_var($member->email_address, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Update your account email address before requesting a sample.');
        }
        $report['sample'] = true;
        $this->send($member->email_address, $name, $report);
    }

    /** Share view validation between immediate samples and recurring subscriptions. */
    private function requestedView(array $data): array
    {
        $gridKey = (string)($data['gridKey'] ?? '');
        GridSubscriptionRegistry::get($gridKey);
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Enter a subscription name of 150 characters or fewer.');
        }

        return [$gridKey, $name, $this->normalizeQuery((string)($data['query'] ?? ''))];
    }

    /** Queue bounded work atomically, reclaiming abandoned claims after one hour. */
    public function enqueueDue(): int
    {
        $table = $this->fetchTable('GridSubscriptions');
        $jobs = $this->fetchTable('Queue.QueuedJobs');
        $now = DateTime::now();

        return $table->getConnection()->transactional(function () use ($table, $jobs, $now): int {
            $due = $table->find()->where([
                'status' => 'active', 'next_run_at <=' => $now,
                'OR' => [['claimed_at IS' => null], ['claimed_at <' => $now->subHours(1)]],
            ])->orderBy(['next_run_at' => 'ASC'])->limit(50)->epilog('FOR UPDATE SKIP LOCKED')->all();
            $count = 0;
            foreach ($due as $subscription) {
                $subscription->claim_token = Text::uuid();
                $subscription->claimed_at = $now;
                $table->saveOrFail($subscription);
                if (
                    !$jobs->createJob('GridSubscription', [
                    'subscriptionId' => $subscription->id, 'claimToken' => $subscription->claim_token,
                    ])
                ) {
                    throw new RuntimeException('Could not enqueue grid subscription.');
                }
                $count++;
            }

            return $count;
        });
    }

    /** Cancelled/stale jobs are no-ops; access loss stops delivery without repeated failures. */
    public function deliver(int $id, string $token): void
    {
        $table = $this->fetchTable('GridSubscriptions');
        $table->getConnection()->transactional(function () use ($table, $id, $token): void {
            $subscription = $table->find()->where(['id' => $id])->epilog('FOR UPDATE')->first();
            if (!$subscription || $subscription->status !== 'active' || $subscription->claim_token !== $token) {
                return;
            }
            try {
                if (!GridSubscriptionRegistry::supports($subscription->grid_key)) {
                    throw new ForbiddenException('The grid is no longer available.');
                }
                $report = ($this->reports ?? throw new LogicException('Report service is required.'))->generate(
                    (int)$subscription->member_id,
                    $subscription->grid_key,
                    json_decode($subscription->query_params, true, 32, JSON_THROW_ON_ERROR),
                    $subscription->origin,
                );
                $member = $this->fetchTable('Members')->get($subscription->member_id);
                if (!filter_var($member->email_address, FILTER_VALIDATE_EMAIL)) {
                    throw new ForbiddenException('A valid account email address is required.');
                }
                if ($report['rows'] !== []) {
                    // Send within the claimed job, never queue a stale snapshot of authorized data.
                    $this->send($member->email_address, $subscription->name, $report);
                    $subscription->last_sent_at = DateTime::now();
                }
                $subscription->next_run_at = DateTime::now()->addDays($subscription->interval_days);
            } catch (ForbiddenException | AuthorizationForbiddenException $exception) {
                $subscription->status = 'stopped';
                $subscription->stop_reason = 'Access to this view or account is no longer available.';
            }
            $subscription->claim_token = null;
            $subscription->claimed_at = null;
            $table->saveOrFail($subscription);
        });
    }

    /** Separate transport seam keeps authorization/delivery tests independent of SMTP. */
    protected function send(string $email, string $name, array $report): void
    {
        (new GridSubscriptionMailer())->send('summary', [$email, $name, $report]);
    }
}
