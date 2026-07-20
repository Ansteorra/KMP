# Real-Time Push / WebSockets Assessment

**Date:** 2026-07-05
**Status:** Assessment / proposal — no implementation
**Context:** Migration to Azure Container Apps removes the old platform constraint that made
long-lived connections impractical, so this document evaluates whether and how KMP should
adopt WebSockets (or another push transport).

---

## 1. Verdict up front

**Raw WebSockets terminated by the KMP PHP application itself: bad idea.** The runtime is
Apache prefork + `mod_php` (see `docker/Dockerfile.base`, `docker/entrypoint.prod.sh`). Every
WebSocket connection would pin one Apache worker process for the life of the connection.
A few dozen idle dashboards would exhaust `MaxRequestWorkers` and take the whole site down.
This is not fixable with configuration; it is the architecture of the runtime.

**Real-time push in general: worth doing, but modestly and via a hub that is not the PHP app.**
KMP has a handful of genuinely multi-user, staleness-sensitive surfaces (approval kanban,
court agenda board, action items, queue-backed long operations) plus one active polling
hotspot (backup/restore status polls every ~1s). Push would improve those. But the transport
should be either **Azure Web PubSub** (managed WebSockets; PHP only publishes over REST) or a
**Mercure hub sidecar** (self-hosted SSE; PHP only publishes over HTTP POST). In both designs
the PHP containers never hold a long-lived connection, so the prefork model is irrelevant.

**Prerequisite regardless of transport:** the concurrency bugs that motivate "live boards"
are not solved by push. The kanban and court-agenda boards are last-write-wins today
(`app/assets/js/controllers/approval-kanban-controller.js`,
`app/plugins/Awards/Assets/js/controllers/court-agenda-board-controller.js`), and
`WorkflowApproval` has a `version` column that is never checked on write. Push shrinks the
conflict window; only server-side optimistic locking closes it. Do the locking first — it is
valuable even if push is never built.

---

## 2. Current state (what the exploration found)

### Frontend / liveness

- Stimulus 3.2 + Turbo 8 (`app/package.json`, `app/assets/js/index.js`). Turbo Frames and
  Turbo Streams are used for partial updates, but only in the request/response cycle — the
  app has **no push channel of any kind** (no WebSocket, SSE, EventSource anywhere).
- Notifications are transient session flash messages rendered via Turbo Stream
  (`app/templates/element/turbo_stream_flash.php`) plus email. There is no persistent
  in-app notification store.

### Polling inventory

| Where | Interval | Notes |
|---|---|---|
| `backup-restore-status-controller.js` | ~1s (configurable) | Polls status for operations that can run 30+ minutes — ~1,800 requests per operation |
| `member-mobile-card-pwa-controller.js` | 10s connectivity probe + 5-min page refresh | Intentional offline-detection design for the mobile card; **not** a push candidate |
| `gathering-map-controller.js` | 100ms script-load check | Utility, irrelevant |

### Multi-user surfaces where staleness matters

| Surface | Today | Failure mode |
|---|---|---|
| Approval kanban (`approval-kanban-controller.js`) | Optimistic drag + POST, revert on HTTP failure | Two approvers move the same card → silent last-write-wins |
| Court agenda board (`court-agenda-board-controller.js`) | Drag + POST with locally computed `sort_order` | Concurrent reordering races on sort_order |
| Action items / My Tasks (`app/templates/ActionItems/my_tasks.php`) | Grid + modal Turbo Stream | Grid goes stale when someone else completes/reassigns |
| Workflow approvals (`WorkflowApproval` entity) | Counter columns (`approved_count` etc.), unused `version` field | Simultaneous approver responses can race the counters |
| Queue-backed jobs (Queue plugin, backup/restore) | Client polls | Polling overhead, 1–2s latency |

### Infrastructure

- **Runtime:** PHP 8.4, Apache prefork, `mod_php`; no PHP-FPM, no async runtime (no Swoole /
  ReactPHP / Ratchet installed). `php-redis` extension **is** in the base image.
- **Local (docker-compose):** `kmp-app` (Apache, 8080→80), `kmp-scheduler`
  (scheduled work and queues), PostgreSQL 16, pgAdmin, Mailpit. **No Redis container.**
- **Azure (`deploy/azure/main.bicep`):** Container Apps environment; web app 1→3 replicas,
  ingress `transport: 'auto'` (WebSocket upgrade already passes through), external ingress,
  PostgreSQL Flexible Server, Blob storage, Key Vault, optional Front Door; Container Apps
  Jobs for queue/scheduler. **No Redis, no Web PubSub/SignalR.** Cache defaults to APCu
  (per-replica, not shared) unless `CACHE_ENGINE=redis` is set.
- Sessions are PHP-native (per-replica files by default) — relevant below for authenticating
  a push channel across replicas.

---

## 3. Where push would actually help (ranked)

1. **In-app notifications** (high value, low effort once a hub exists): approval requests,
   action-item assignment, workflow completion — today these are email-only until the next
   page load. Requires a persistent `notifications` table regardless of transport.
2. **Backup/restore & queue-job progress** (clear win, smallest scope): replace the 1s poll
   loop with pushed progress events; worker containers already know the progress and can
   publish it.
3. **Approval kanban live sync** (visible wow factor): broadcast card moves so other open
   boards update; pair with optimistic locking so a stale drag gets a "board changed,
   refreshing" instead of silent overwrite.
4. **Court agenda board live sync**: same shape as #3; matters most during court prep when
   several people edit at once.
5. **Action-items grid refresh**: push an event, let the existing Turbo/grid machinery
   re-fetch the frame.

Not worth push: the mobile PWA connectivity probe (its whole point is detecting the *absence*
of a network), and general page content (Turbo Frames on navigation is fine).

A useful framing: **every one of these is server→client fan-out.** Nothing in KMP needs
client→server messaging beyond what normal POSTs already do. That means full-duplex
WebSockets are not actually required — one-way push (SSE) covers 100% of the identified use
cases. This significantly changes the cost/benefit of the options below.

---

## 4. Options

### Option 0 — Do nothing / smarter polling (baseline)

Turbo Streams over regular responses already handles "update the page after *my* action."
The gaps are cross-user updates and long-job progress. A cheap stopgap is slow polling
(15–30s) on the boards with an ETag/`updated_since` check. Nearly free, but it doesn't
feel live and adds load at scale.

### Option A — Azure Web PubSub (managed WebSockets) ✅ recommended if Azure-first

- Clients open a WebSocket **directly to the Web PubSub service**, not to KMP. PHP's only
  jobs: a `/realtime/negotiate` endpoint that mints a client access JWT (group claims =
  the user's channels), and REST calls to publish events. No long-lived connections ever
  touch Apache. Works unchanged at 1 or 30 replicas with **no Redis**.
- Free tier: 20 concurrent connections (enough for dev/POC). Standard ≈ $49/unit/month for
  1,000 concurrent connections — one unit covers a kingdom's realistic concurrent usage.
- Local dev: Azure provides a Web PubSub **local emulator**, or gate the feature behind a
  config flag so local falls back to existing polling.
- Trade-offs: vendor-specific client/negotiate flow; one more Azure resource + secret;
  local story is emulator-or-flag rather than identical.

### Option B — Mercure hub sidecar (self-hosted SSE) ✅ recommended if parity/vendor-neutrality matters

- [Mercure](https://mercure.rocks) is a single Go binary (official Docker image) purpose-built
  for exactly this pattern in PHP ecosystems (it's the Symfony/API-Platform standard).
  Browsers subscribe with native `EventSource` (no client library); PHP publishes with one
  HTTP POST + JWT. Topic-level authorization via a JWT cookie the app sets.
- Runs **identically** as a docker-compose service and as a small internal Container App —
  full dev/prod parity, no vendor lock-in, effectively free to operate.
- Hotwire integration is idiomatic: publish Turbo Stream HTML fragments to a topic;
  a ~20-line Stimulus controller feeds them to `Turbo.renderStreamMessage()`. Server-rendered
  updates stay server-rendered, matching the project constitution's Turbo mandate.
- It's SSE, not WebSockets — which is *fine*, because (see §3) KMP only needs server→client.
  SSE also auto-reconnects natively and traverses proxies as plain HTTP.
- Trade-offs: one more service you run and monitor; a single hub instance is a SPOF for
  push (degrade gracefully: if the stream drops, controllers fall back to polling).

### Option C — Ratchet / Swoole / Node sidecar inside the app container ❌ not recommended

Running a WebSocket server process alongside Apache in the same container couples scaling
(each app replica holds a shard of connections → you need Redis pub/sub so replica A's PHP
can reach replica B's sockets → you must add and pay for Redis → clients need
replica-affinity), complicates the image, and re-introduces everything Options A/B avoid.
Only worth revisiting if KMP someday needs true client→server streaming (it doesn't today).

### Recommendation

**Phase 0 (now, no transport):** server-side optimistic locking on kanban / court agenda /
approval responses using the existing `version` columns; return HTTP 409 with a Turbo Stream
that refreshes the board and toasts "updated by someone else."

**Phase 1:** pick the hub — **Option B (Mercure)** if you value dev/prod parity and zero
vendor lock-in; **Option A (Web PubSub)** if you prefer managed-everything on Azure. Wire it
behind a `realtime` feature flag with polling fallback. First consumers: backup/restore
progress (deletes the 1s poll loop) and a persistent-notifications table with live toasts.

**Phase 2:** live board sync (kanban, court agenda, action items) by publishing Turbo Stream
fragments on entity events.

---

## 5. What enabling this requires — Docker (local dev)

Shown for Option B (Mercure); Option A local dev uses the Web PubSub emulator or feature-flag
fallback instead and needs no new compose service.

### 5.1 docker-compose.yml

```yaml
  kmp-mercure:
    image: dunglas/mercure:latest
    restart: unless-stopped
    environment:
      MERCURE_PUBLISHER_JWT_KEY: "${MERCURE_JWT_SECRET:-!ChangeThisMercureSecret!}"
      MERCURE_SUBSCRIBER_JWT_KEY: "${MERCURE_JWT_SECRET:-!ChangeThisMercureSecret!}"
      MERCURE_EXTRA_DIRECTIVES: |
        cors_origins http://localhost:8080
        anonymous 0
    ports:
      - "8081:80"        # dev only; in prod the hub sits behind ingress/proxy
```

`kmp-app` and `kmp-scheduler` get two env vars:

- `MERCURE_PUBLISH_URL=http://kmp-mercure/.well-known/mercure` (server-to-hub publishing)
- `MERCURE_PUBLIC_URL=http://localhost:8080/.well-known/mercure` (what browsers connect to)
- `MERCURE_JWT_SECRET` shared with the hub.

The scheduler container matters: **queue jobs are the natural publishers** for
progress and notification events, and it runs separately from the web container with app code
and config loaded.

### 5.2 Same-origin routing (recommended over exposing port 8081)

To keep cookies/CSP simple, proxy the hub path through Apache so browsers talk to one origin:

- `docker/Dockerfile.base`: `a2enmod proxy proxy_http` (and `proxy_wstunnel` only if a
  WebSocket-based hub is ever chosen — Mercure/SSE needs plain `proxy_http`).
- `docker/apache-vhost.conf`:

  ```apache
  ProxyPass        /.well-known/mercure http://kmp-mercure/.well-known/mercure flushpackets=on
  ProxyPassReverse /.well-known/mercure http://kmp-mercure/.well-known/mercure
  ```

  (`flushpackets=on` so Apache doesn't buffer the event stream.)

Note this proxying happens in **Apache's event-free `mod_proxy` path but still occupies a
prefork worker per subscriber** — fine for local dev, but in Azure the hub should be reached
directly via its own ingress (§6), *not* proxied through the PHP containers, for exactly the
prefork reason in §1.

### 5.3 Application changes (both options, transport-agnostic core)

- `RealtimePublisher` service interface with `MercurePublisher` / `WebPubSubPublisher` /
  `NullPublisher` implementations; config-selected. Publish points: queue job progress,
  workflow/approval events (there are already event listeners, e.g.
  `BestowalTodoCompletionListener`), action-item mutations.
- A controller endpoint that issues subscriber JWTs (topics scoped to the member's
  branches/roles, reusing existing policy checks). Use a **signed JWT, not session lookup**,
  so authorization is replica-agnostic (sessions are per-replica files today).
- One Stimulus controller (`realtime-stream-controller`) that opens the EventSource /
  WebSocket, feeds Turbo Stream payloads to `Turbo.renderStreamMessage()`, and falls back to
  the existing polling behavior on error/absence of config.
- CSP: `connect-src` must include the hub origin
  (see `app/src/... ` CSP middleware config if/where headers are set).

---

## 6. What enabling this requires — Azure Container Apps

### Already in place (no work)

- Container Apps ingress `transport: 'auto'` already accepts WebSocket upgrades and streams
  SSE; the existing web app bicep needs no ingress change
  ([ingress docs](https://learn.microsoft.com/en-us/azure/container-apps/ingress-overview)).
- `TRUST_PROXY=true` / forwarded-header handling already configured in `entrypoint.prod.sh`.

### Option A — Web PubSub additions to `deploy/azure/main.bicep`

1. `Microsoft.SignalRService/webPubSub` resource (Free_F1 for POC, Standard_S1 for prod).
2. Connection string (or better: managed-identity + `Web PubSub Service Owner` role for the
   web app and job identities) → Key Vault → env var, matching the existing secret pattern.
3. App changes from §5.3; clients connect straight to `wss://<hub>.webpubsub.azure.com`, so
   **Front Door and the app ingress are not in the push path at all** — no Front Door
   WebSocket concerns, no session-affinity needs, no connection load on the PHP replicas.
4. `connect-src` CSP entry for the Web PubSub endpoint.

### Option B — Mercure as a second Container App

1. New Container App `kmp-mercure` in the same environment: image `dunglas/mercure`,
   **external ingress** (browsers connect directly), targetPort 80, JWT secret from
   Key Vault. Scale 1→1 (a single small replica handles thousands of SSE subscribers; scale
   out later only with the hub's HA setup).
2. Web app + jobs get `MERCURE_PUBLISH_URL` pointing at the hub's **internal** FQDN and the
   shared JWT secret; `MERCURE_PUBLIC_URL` pointing at its external FQDN (or a
   `push.<domain>` custom domain).
3. Do **not** route subscriber traffic through the PHP app's Apache in Azure (prefork worker
   pinning, §1). Same-origin niceties can be had with a Front Door route instead.
4. CSP `connect-src` for the hub origin; hub CORS configured for the app origin(s).

### Front Door / ingress caveats (both options, and any future raw-WS endpoint)

- Front Door Standard/Premium supports WebSockets (GA, on by default) but: connections are
  capped at **4 hours** and **3,000 concurrent per profile**, and **caching must be disabled
  on WebSocket/SSE routes** or the Upgrade header is dropped and the connection is treated as
  a cacheable HTTP request
  ([Front Door WebSocket docs](https://learn.microsoft.com/en-us/azure/frontdoor/standard-premium/websocket)).
  For SSE, likewise ensure the route bypasses caching/compression. Simplest posture: keep
  push traffic **off** Front Door (direct to Web PubSub or to the Mercure app's FQDN).
- If a future design ever holds connections on the app itself (Option C), you'd additionally
  need Container Apps **session affinity**, awareness that HTTP-concurrency autoscaling
  counts idle sockets, and reconnect logic for scale-in events — three more reasons to keep
  connections off the app replicas.
- Client code must reconnect gracefully regardless (Front Door 4h cap, ingress idle
  timeouts, replica restarts). `EventSource` does this natively; WebSocket clients need it
  written.

---

## 7. Cost & effort summary

| Item | Option A (Web PubSub) | Option B (Mercure) |
|---|---|---|
| New infra | 1 managed resource (~$0 free tier / ~$49/mo Standard) | 1 tiny Container App (~consumption pennies) |
| Redis needed | No | No |
| Local dev | Emulator or feature-flag fallback | Identical compose service (full parity) |
| Client tech | WebSocket + WPS client lib | Native `EventSource`, no lib |
| Lock-in | Azure-specific | None |
| Phase 0 (optimistic locking) | ~3–5 days | same |
| Phase 1 (hub + notifications + job progress) | ~1.5–2 weeks | ~1.5–2 weeks |
| Phase 2 (live boards) | ~1–2 weeks | ~1–2 weeks |

---

## 8. Key file references

- Polling: `app/assets/js/controllers/backup-restore-status-controller.js`,
  `app/assets/js/controllers/member-mobile-card-pwa-controller.js`
- Boards: `app/assets/js/controllers/approval-kanban-controller.js`,
  `app/plugins/Awards/Assets/js/controllers/court-agenda-board-controller.js`
- Unused version field: `app/src/Model/Entity/WorkflowApproval.php`
- Runtime: `docker/Dockerfile.base`, `docker/entrypoint.prod.sh`, `docker/apache-vhost.conf`
- Compose: `docker-compose.yml`; Azure IaC: `deploy/azure/main.bicep`
- Frontend entry: `app/assets/js/index.js`; flash streams:
  `app/templates/element/turbo_stream_flash.php`
