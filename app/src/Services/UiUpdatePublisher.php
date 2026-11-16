<?php
declare(strict_types=1);

namespace App\Services;

use Redis;
use RuntimeException;

/** Redis fanout and bounded history are hints; tenant data remains authoritative. */
final class UiUpdatePublisher
{
    /** @param array<string, mixed> $event Committed outbox metadata. */
    public function publish(array $event): void
    {
        $config = parse_url((string)env('REDIS_URL', ''));
        if (
            !is_array($config) || empty($config['host'])
            || !in_array($config['scheme'] ?? '', ['redis', 'rediss'], true)
        ) {
            throw new RuntimeException('Live delivery requires Redis.');
        }
        $redis = new Redis();
        $host = ($config['scheme'] ?? '') === 'rediss' ? 'tls://' . $config['host'] : $config['host'];
        try {
            if (!$redis->connect($host, (int)($config['port'] ?? 6379), 0.2)) {
                throw new RuntimeException('Unable to connect to live delivery.');
            }
            $redis->setOption(Redis::OPT_READ_TIMEOUT, 0.2);
            if (!empty($config['pass'])) {
                $credentials = empty($config['user']) ? rawurldecode($config['pass'])
                : [rawurldecode($config['user']), rawurldecode($config['pass'])];
                if (!$redis->auth($credentials)) {
                    throw new RuntimeException('Unable to authenticate live delivery.');
                }
            }
            $prefix = (string)env('KMP_LIVE_PREFIX', 'kmp:live:');
            $tenant = (string)$event['tenant_id'];
            $topics = $event['topics'];
            $data = json_encode([
                'id' => $event['id'], 'tenant' => $tenant,
                'requestId' => $event['source_request'] ?? null,
                'topics' => is_string($topics) ? json_decode($topics, true, 32, JSON_THROW_ON_ERROR) : $topics,
            ], JSON_THROW_ON_ERROR);
            $result = $redis->eval(<<<'LUA'
if redis.call('EXISTS', KEYS[1]) == 0 then
  local cursor = redis.call('XADD', KEYS[2], 'MAXLEN', '~', 10000, '*', 'data', ARGV[1])
  redis.call('EXPIRE', KEYS[2], 86400)
  redis.call('PUBLISH', KEYS[3], cjson.encode({cursor=cursor, data=ARGV[1]}))
  redis.call('SET', KEYS[1], '1', 'EX', 86400)
end
return 1
LUA, [$prefix . 'sent:' . $event['id'], $prefix . 'history:' . $tenant, $prefix . 'events', $data], 3);
            if ($result !== 1) {
                throw new RuntimeException('Live delivery did not acknowledge the event.');
            }
        } finally {
            if ($redis->isConnected()) {
                $redis->close();
            }
        }
    }
}
