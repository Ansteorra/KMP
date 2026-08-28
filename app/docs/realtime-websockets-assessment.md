# Real-time delivery assessment

**Status:** historical assessment; no real-time transport is implemented or approved.

KMP currently uses ordinary HTTP requests, Turbo Frames/Streams returned from those requests,
and targeted polling for a small number of long-running operations. The application does not
run a WebSocket or Server-Sent Events endpoint, provide a browser subscription API, provision
Azure Web PubSub/SignalR, or ship a Mercure/Redis fan-out service. Do not use this note as an
implementation contract or deployment requirement.

## Current architectural decision

Keep user actions on authenticated, CSRF-protected HTTP endpoints and keep asynchronous work
in tenant queues or central platform jobs as appropriate. Turbo Stream markup does not imply
server push; today it is delivered in the initiating response.

The current Apache/PHP web runtime is not an appropriate owner for long-lived client
connections. If live updates become a product requirement, terminate connections in a
separate managed or purpose-built hub and let PHP publish short outbound messages. One-way
server-to-browser delivery is sufficient for the previously identified cases; normal POSTs
remain the client-to-server channel.

Real-time delivery also does not solve write concurrency. Use transactional invariants,
version checks, locks, and conflict responses first. The workflow approval manager already
uses version-aware writes; preserve that protection.

## Requirements for a future ADR

Before adding any transport, a new decision record must establish:

- a measured user problem and why bounded polling is insufficient;
- tenant-scoped topics derived from `TenantContext`, never a client-provided tenant ID;
- subscriber authorization, revocation, reconnect, token lifetime, and platform-admin
  separation;
- privacy-safe event envelopes with no secrets or unnecessary member data;
- delivery semantics, ordering, idempotency, graceful degradation, and polling fallback;
- local, POC, and production topology, cost, capacity, health checks, and incident ownership;
- CSP `connect-src` changes and cross-origin/cookie implications;
- two-tenant isolation, stale-client conflict, reconnect, failover, and accessibility tests.

Publishing HTML fragments should use existing server renderers and Turbo conventions. A
client must re-fetch authoritative state after a gap or conflict rather than treating an event
stream as the database.

## Previously identified candidates

Long-operation progress, approval/action-item refresh, and shared board refresh remain
possible candidates. They are not commitments, and no environment variables or infrastructure
for them should appear in required setup until an implementation ADR is accepted.

For current behavior, consult `assets/js/controllers`, `src/Queue`,
`plugins/Queue`, and the platform job/schedule services rather than this assessment.
