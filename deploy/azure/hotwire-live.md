# Hotwire live delivery deployment contract

The web app owns identity, tenant/branch authorization, content rendering and the
transactional outbox. The independently deployed `services/live-updates` gateway
owns SSE connections and Redis replay/fanout. The application runtime team owns
both services and their release evidence. Neither service publishes private HTML
or user records through shared channels.

## Configuration and keys

Both `KMP_TURBO_ENABLED` and `KMP_LIVE_UPDATES_ENABLED` default to false. Drive can
operate without live delivery. Live requires Drive, shared Redis cache/sessions,
the outbox migrations, ordinary tenant worker scheduling, and the gateway.

| Setting | Consumer | Contract |
| --- | --- | --- |
| `KMP_TURBO_ENABLED` | Web/workers | Drive compatibility and fallback switch |
| `KMP_LIVE_UPDATES_ENABLED` | Web/workers | Independent live delivery switch |
| `KMP_LIVE_PRIVATE_KEY` | Web only | RSA signing PEM, secret injected from Key Vault |
| `KMP_LIVE_PUBLIC_KEY` | Gateway | Matching SPKI PEM public key |
| `KMP_LIVE_GATEWAY_URL` | Web | Browser-reachable HTTPS `/events` URL |
| `KMP_LIVE_ALLOWED_ORIGINS` | Gateway | Explicit comma-separated tenant origins, including ports locally |
| `REDIS_URL` | Web/workers/gateway | Shared Redis; authenticated TLS in Azure, with URI-encoded credentials |
| `KMP_LIVE_PREFIX` | Web/workers/gateway | Identical dedicated key namespace on every replica |
| `KMP_SESSION_DEFAULTS` | Web | `cache` with shared Redis cache configuration |

Generate an RSA key pair through the environment's secret-management process.
Never commit keys, place tokens in URLs, include Authorization headers in access
logs, or copy tenant event data into metrics. Signing material goes only to the
web service; the gateway verifies with a public key. Rotate by deploying the new
verifier and signer together after existing 120-second subscriptions expire, or
disable live delivery during rotation. Existing credentials stay in Key Vault.

`live-updates.bicep` deploys at least two gateway replicas with liveness/readiness
probes, non-PHP persistent connections, and a shared Redis prerequisite. The main
template exposes `enableTurbo`, `enableLiveUpdates`, gateway image, signing key,
public key, and allowed-origin parameters. Adding a tenant host requires updating
the gateway's exact-origin allowlist before enabling subscriptions for that host.
All replica clocks must be synchronized.

Production Apache limits short HTTP work with `APACHE_MAX_REQUEST_WORKERS`
(default 16; 4–128). Size its connection budget across every web replica and
tenant worker: a web request can hold both a platform and a tenant connection.
Leave room for migrations, monitoring and administrative jobs. Raising PostgreSQL
connection limits alone does not increase CPU or query throughput. Keep live
connections on the independent gateway. Azure ingress or the local Caddy proxy
retains browser connections; Apache backend keepalive is disabled so idle sockets
cannot occupy all prefork workers while other requests queue. See
[Apache prefork guidance](https://httpd.apache.org/docs/2.4/mod/prefork.html).
The immutable-image acceptance test
`python3 docker/test-apache-capacity.py --image IMAGE` proves that concurrent
requests queue within the configured PHP limit and invalid limits fail startup.

`main.bicep` exposes `webCpu` with matching Consumption memory and
`apacheMaxRequestWorkers`; existing deployments retain the 0.5-core default until
explicitly resized. The healthy 500-session diagnostic used two 4-core web
replicas, 16 PHP workers each, and a 4-core PostgreSQL server. Use that CPU budget
as the starting point for the release workload; set at least two web replicas.
Record actual resource limits in the final baseline/live reports. A short local
diagnostic is not the full capacity gate or evidence of Azure performance.

The gateway has its own managed identity, with ACR pull and Key Vault access scoped
only to the Redis secret. It receives no signing private key, tenant database
credentials, document-storage grants, or administrative secrets. Web and ordinary
workers retain their existing runtime identities and database boundaries.

Use the existing **NoCluster** Redis contract: both CakePHP sessions/cache and the
publisher's multi-key Lua operation require it. Budget separate Redis capacity for
sessions, cache, replay and pubsub; choose eviction/availability settings so
session storage cannot be evicted by replay growth. The main template selects
`NoEviction` when live delivery is enabled; monitor memory headroom and rejected
writes so a full cache does not become an availability incident. Replay is bounded per tenant
and expires after one day. Outbox rows contain metadata and delivered rows are
pruned in bounded batches after one day; undelivered rows remain retryable.

## Local verification

The opt-in `docker/compose.live.yml` overlay provides a dedicated Redis instance
and gateway. Supply generated local PEMs through environment injection, then run
Docker Compose with the root configuration and this overlay using the `live`
profile. Keys are required when parsing the overlay. The default gateway binds
only to loopback port 9080, and Redis is internal to Docker.

Build `docker/Dockerfile.live` from the repository root. Run `npm ci` and `npm test`
in `services/live-updates`; run `npm run test:redis` with `KMP_TEST_REDIS_URL`
pointing to a disposable Redis instance. The integration check uses its own random
namespace, proves independent replica fanout and complete stream-ID replay, and
removes its keys. It does not test production Redis or customer tenants.

## Health, outages and fallback

Gateway `/livez` reports process liveness; `/health` reports Redis readiness and
connection/delivery/rejection counters. Browser stream connections close on
delivery outages, expiry, shutdown, or navigation. Reconnect reauthorizes through
CakePHP; replay gaps trigger authorized reconciliation. During an outage, the
browser checks visible subscribed content every 30 seconds and retains drafts.
Monitor request errors/latency, active connections, reconnect/rejection rates,
Redis memory, outbox attempts/age, worker health, and web/gateway CPU and memory.

To fall back, set `KMP_LIVE_UPDATES_ENABLED=false` on web and ordinary workers.
Existing subscriptions expire within 120 seconds. Setting `KMP_TURBO_ENABLED=false`
additionally restores native document navigation while retaining Frames/Streams.
Snapshot context includes both switches, so restored authenticated snapshots must
revalidate. Keep database migrations applied; do not drop the outbox or rewrite
migration histories as part of fallback. Retain queued events for investigation.

## Promotion requirements

Publish and promote both images by immutable digest for the same candidate
commit. Run all app/gateway/infrastructure checks, sequential three-browser UAT,
isolated platform tests, authorization/outage tests, accessibility checks and the
500-session capacity gate specified in
[Hotwire contracts](../../app/docs/hotwire-interactions.md). Validate that exact
candidate and both digests in POC through the established release workflow before
production promotion. Template compilation and local tests alone do not establish
release readiness. Production deployment remains a separate authorized action.

The image workflow builds matching web and `kmp-live` images with the candidate
revision label. POC imports both digests, verifies the gateway's ready revision
before cutting over workers/web, and records `poc-image-pair-<commit>` evidence only
after successful POC validation. Release promotion requires that exact paired
artifact and attaches the pair to the GitHub Release. It never rebuilds the images.
The production environment approval remains mandatory.

Configure `AZURE_LIVE_APP_NAME` only after the gateway infrastructure exists, and
set the protected environment's `LIVE_UPDATES_ENABLED` deployment prerequisite to
match its runtime switch. Enabled delivery fails closed without a gateway name.
The cutover captures the previous immutable gateway image, restores it if gateway
health fails, and also restores it when the subsequent web cutover rolls back.
The paired image is still imported when live delivery is disabled, so later
enablement uses the tested candidate rather than an unrelated gateway build.
