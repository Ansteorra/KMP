# Runtime services guide

Own independently deployed runtime services used by the CakePHP application.

- `live-updates` owns SSE connections, subscription-token verification, Redis fanout,
  reconnect history, and connection health. CakePHP owns resource authorization and HTML.
- Never send private record content through shared live channels. Keep tokens out of URLs,
  logs, and metrics. Validate issuer, audience, algorithm, expiry, tenant, topic, and Origin.
- Use locked dependencies, bounded buffers/history, graceful shutdown, and health checks.
- The SSE image pins the maintained Node 24 Trixie runtime, refreshes OS patches, and removes npm/Yarn after installing locked dependencies. CI scans the final image, blocks fixable High/Critical issues, and retains all findings plus an SBOM; remaining native findings still require release triage.
- Container definitions belong in root `docker/`; Azure resources belong in `deploy/azure`.
- Run `npm test` from the changed service and test its real Redis integration before release.

No child guides are present.
