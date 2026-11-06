# Performance verification

Own executable performance workloads and release capacity evidence. Follow the root
and application guides. Run only against isolated synthetic tenant fixtures; never
reuse customer accounts or reset a shared development database.

`live-capacity-benchmark.mjs` uses real authenticated HTTP sessions and direct SSE
connections. Keep passwords in the environment and tokens/cookies in memory. Its
private manifest records synthetic accounts and immutable candidate images. Reports
contain aggregate timings, errors and resource metrics, never credentials.

Keep the release thresholds fixed: 500 distinct authenticated sessions, five tenants,
two web and two gateway replicas, 50 ordinary requests and five mutations per second
for 30 minutes. Compare the same candidate and fixture with an independent baseline.
Shortened runs are diagnostics and must fail release acceptance. Missing telemetry,
failed requests, lost updates or unmatched candidate evidence also fail acceptance.

Run `npm run test:perf:contracts` for workload/gate changes. Run the complete workload
after runtime or infrastructure changes that affect the release candidate. Browser
lanes and load tests must run separately to keep evidence attributable.
