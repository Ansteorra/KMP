# Security verification

Run `bash security-checker.sh` for the Composer and both npm locks. Add
`--image IMAGE` to scan the final container and create its CycloneDX SBOM using Trivy.
Missing tools, network failures and malformed reports fail the command. Reports default to a
private temporary directory; never commit reports containing runtime configuration or PII.
Composer advisories/abandoned packages and npm High/Critical issues block. The image gate
blocks fixable High/Critical findings; every remaining native finding still needs reachability
triage. Custom PHP and PECL components require explicit build-version checks beyond OS scans.

`exceptions.json` contains only reviewed image exceptions with `id`, `package`, `owner`,
`reason` and ISO `expires` date. Expired/incomplete exceptions fail. Do not use blanket package
or kernel suppressions; distinguish userland headers from the provider's running kernel.

The dependency inventory also includes `app/plugins/Queue` (copied source), first-party
plugins, emitted frontend bundles, face-api/OpenCV model assets, the Ruby documentation
lock, PHP's bundled GD and separately compiled PECL extensions. Composer/npm audits do not
cover every embedded copy. Review these when their source/version changes; preserve face
recognition compatibility. Retired Go installer/updater distribution has been removed.

Action SHAs, native build image digests, the PostgreSQL signing-key checksum and PECL versions
are reviewed inputs. Published candidates are scanned by their exact digest for both
architectures before smoke tests and POC deployment; production promotion preserves that digest. Dependabot
opens updates; scheduled verification detects newly disclosed advisories. A base update must
produce a tested immutable application image before release promotion. Production deploys
remain approval-gated and never rebuild the POC-tested image.

Published candidates rebuild the native runtime stage so cached APT layers cannot hide new
security updates. Scheduled base/security builds also refresh native layers; application
asset and Composer stages retain their normal caches.
