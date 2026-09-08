<?php
declare(strict_types=1);

namespace App\Services\Security;

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\ServiceUnavailableException;
use Cake\Utility\Security;
use Closure;
use InvalidArgumentException;
use Throwable;

/** Atomic fixed-window limits shared by every replica using the same database. */
class RequestRateLimiter
{
    public const BUCKET_EMAIL_TAKEN = 'members.email_taken';
    public const BUCKET_SEARCH_MEMBERS = 'members.search_members';
    public const BUCKET_GITHUB_ISSUE = 'github.issue_submit';
    public const BUCKET_RESET_IP = 'members.reset_ip';
    public const BUCKET_RESET_ACCOUNT = 'members.reset_account';
    public const BUCKET_RESET_COOLDOWN = 'members.reset_cooldown';
    public const BUCKET_PIN = 'members.pin';
    public const BUCKET_PLATFORM_LOGIN_IP = 'platform.login_ip';
    public const BUCKET_PLATFORM_LOGIN_ACCOUNT = 'platform.login_account';
    public const BUCKET_PLATFORM_STEP_UP = 'platform.step_up';

    private const LIMITS = [
        self::BUCKET_EMAIL_TAKEN => ['max' => 10, 'window' => 900],
        self::BUCKET_SEARCH_MEMBERS => ['max' => 15, 'window' => 900],
        self::BUCKET_GITHUB_ISSUE => ['max' => 5, 'window' => 3600],
        self::BUCKET_RESET_IP => ['max' => 10, 'window' => 900],
        self::BUCKET_RESET_ACCOUNT => ['max' => 3, 'window' => 3600],
        self::BUCKET_RESET_COOLDOWN => ['max' => 1, 'window' => 300],
        self::BUCKET_PIN => ['max' => 5, 'window' => 300],
        self::BUCKET_PLATFORM_LOGIN_IP => ['max' => 20, 'window' => 900],
        self::BUCKET_PLATFORM_LOGIN_ACCOUNT => ['max' => 5, 'window' => 900],
        self::BUCKET_PLATFORM_STEP_UP => ['max' => 5, 'window' => 900],
    ];

    private readonly Closure $clock;

    /** Use an explicit connection for tests or platform operations. */
    public function __construct(private readonly ?Connection $connection = null, ?callable $clock = null)
    {
        $this->clock = $clock !== null ? $clock(...) : static fn(): int => time();
    }

    /** Atomically reserve an attempt; database errors fail closed. */
    public function attempt(string $bucket, string $clientKey): RateLimitResult
    {
        $limits = self::LIMITS[$bucket] ?? throw new InvalidArgumentException('Unknown rate limit bucket.');
        $now = ($this->clock)();
        $expires = (intdiv($now, $limits['window']) + 1) * $limits['window'];
        $scope = str_starts_with($bucket, 'platform.') ? 'platform' : MemberSessionState::tenantId();
        if ($scope === null) {
            throw new ServiceUnavailableException('Authentication context is unavailable.');
        }
        $key = hash_hmac('sha256', json_encode([$scope, $bucket, $clientKey, $expires]), Security::getSalt());
        try {
            $connectionName = $scope === 'single-tenant' ? 'default' : 'platform';
            $connection = $this->connection ?? ConnectionManager::get($connectionName);

            return $connection->transactional(function (Connection $connection) use ($key, $expires, $limits, $now) {
                $sql = 'INSERT INTO security_rate_limits (bucket_key, attempts, expires_at) VALUES (:key, 0, :expires)';
                if ($connection->getDriver() instanceof Mysql) {
                    $sql .= ' ON DUPLICATE KEY UPDATE bucket_key = bucket_key';
                } else {
                    $sql .= ' ON CONFLICT (bucket_key) DO NOTHING';
                }
                $connection->execute($sql, ['key' => $key, 'expires' => $expires]);
                $changed = $connection->execute(
                    'UPDATE security_rate_limits SET attempts = attempts + 1 ' .
                    'WHERE bucket_key = :key AND attempts < :max',
                    ['key' => $key, 'max' => $limits['max']],
                )->rowCount();
                $row = $connection->execute(
                    'SELECT attempts FROM security_rate_limits WHERE bucket_key = :key',
                    ['key' => $key],
                )->fetch('assoc');
                // Bounded retention: only timestamps and opaque keyed digests are stored.
                $connection->execute(
                    'DELETE FROM security_rate_limits WHERE expires_at < :cutoff',
                    ['cutoff' => $now - 86400],
                );

                return new RateLimitResult(
                    $changed === 1,
                    max(0, $limits['max'] - (int)$row['attempts']),
                    $changed === 1 ? 0 : max(1, $expires - $now),
                    $changed === 1 ? $key : null,
                );
            });
        } catch (Throwable $exception) {
            throw new ServiceUnavailableException('Request protection is temporarily unavailable.', null, $exception);
        }
    }

    /** Release one successful MFA reservation without forgiving earlier failures. */
    public function releaseSuccessfulAttempt(RateLimitResult $attempt): void
    {
        if (!$attempt->allowed || $attempt->reservationKey === null || $this->connection === null) {
            return;
        }
        $this->connection->execute(
            'UPDATE security_rate_limits SET attempts = attempts - 1 WHERE bucket_key = :key AND attempts > 0',
            ['key' => $attempt->reservationKey],
        );
    }
}
