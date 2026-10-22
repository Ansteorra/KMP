# Security verification

Run `bash security-checker.sh` for the Composer and both npm locks. Add
`--image IMAGE` to scan the final container and create its CycloneDX SBOM using Trivy.
Missing tools, network failures and malformed reports fail the command. Reports default to a
private temporary directory; never commit reports containing runtime configuration or PII.
Composer advisories/abandoned packages and npm High/Critical issues block. The image gate
blocks fixable High/Critical findings; every remaining native finding still needs reachability
triage. Custom PHP and PECL components require explicit build-version checks beyond OS scans.

Final-image audits execute `docker/verify-php-version.php` using the image's PHP
interpreter before the Trivy scan, independently of the image's bundled checker.
The container entrypoint is bypassed; networking is disabled and the filesystem
and policy mount are read-only. The check rejects affected PHP versions even
when the scanner database has not yet indexed the critical October 2026 CVEs.
See [the PHP update contract](../docker/README.md#php-security-updates) for the
branch-specific minimum versions and image refresh workflow.

`exceptions.json` contains only reviewed image exceptions with `id`, `package`, `owner`,
`reason` and ISO `expires` date. Expired/incomplete exceptions fail. Do not use blanket package
or kernel suppressions; distinguish userland headers from the provider's running kernel.

The dependency inventory also includes `app/plugins/Queue` (copied source), first-party
plugins, emitted frontend bundles, face-api/OpenCV model assets, the Ruby documentation
lock, PHP's bundled GD and separately compiled PECL extensions. Composer/npm audits do not
cover every embedded copy. Review these when their source/version changes; preserve face
recognition compatibility. Retired Go installer/updater distribution has been removed.

The scoped npm override for `@istanbuljs/load-nyc-config` selects `js-yaml` 4
to remove its legacy `argparse`/`sprintf-js` chain. The loader uses the supported
`load()` API; verify YAML NYC configuration loading and run Jest when refreshing
this override. Do not accept npm audit suggestions that downgrade Jest to an old
major version. Keep face-detection and OpenCV versions unchanged during unrelated
dependency refreshes.

Action SHAs, native build image digests, the PostgreSQL signing-key checksum and PECL versions
are reviewed inputs. Published candidates are scanned by their exact digest for both
architectures before smoke tests and POC deployment; production promotion preserves that digest. Dependabot
opens updates; scheduled verification detects newly disclosed advisories. A base update must
produce a tested immutable application image before release promotion. Production deploys
remain approval-gated and never rebuild the POC-tested image.

Published candidates and every security-gate image build refresh the native runtime stage so
cached APT layers cannot hide new security updates, including branch-push quality evidence.
Scheduled base builds also refresh native layers; application
asset and Composer stages retain their normal caches.

## PHP October 21, 2026 behavioral validation

This is a controlled source-runtime comparison, not validation of a deployed KMP
release. PHP's [joint fix](https://github.com/php/php-src/commit/31c0ea7d7ff1d1fccb6492e52dab544011628065)
addresses both CVEs. The selected unmodified upstream PHPT tests and helpers come
from the official PHP 8.5.12 source tarball and correspond to release commit
`136e049ac10b032c83018e65b63b6df858943750`.

**Observed:** four cases fail on the known-vulnerable PHP 8.5.11 baseline and pass
on PHP 8.5.12. All nine cases pass on 8.5.12, with no skips or warnings. The five
cases that also pass on 8.5.11 provide regression coverage only. Neither memory
corruption nor a complete RCE exploit was reproduced by this comparison.

| Upstream test path | PHP 8.5.11 | PHP 8.5.12 |
| --- | --- | --- |
| `Zend/tests/oom_after_main_script.phpt` | FAIL | PASS |
| `Zend/tests/oom_hard_limit_exceeded_during_complilation.phpt` | FAIL | PASS |
| `Zend/tests/oom_pending_at_shutdown.phpt` | PASS | PASS |
| `Zend/tests/oom_request_parsing.phpt` | PASS | PASS |
| `Zend/tests/oom_soft_limit_exceeded.phpt` | PASS | PASS |
| `ext/session/tests/rfc1867_incomplete_class_reserved_props.phpt` | FAIL | PASS |
| `ext/session/tests/rfc1867_no_user_code.phpt` | FAIL | PASS |
| `sapi/cli/tests/oom_request_parsing_next_request.phpt` | PASS | PASS |
| `sapi/cli/tests/oom_shutdown_function_next_request.phpt` | PASS | PASS |

The baseline differences are concrete:

- `oom_after_main_script.phpt` fails because the baseline aborts before printing
  `Done`; the patched engine delays the soft OOM and reaches that point before
  reporting it and running the shutdown function.
- `oom_hard_limit_exceeded_during_complilation.phpt` observes an ordinary 10 MiB
  memory-limit error on the baseline. The patch implements the expected 20 MiB
  hard-limit diagnostic and deliberate child termination (`Termsig=9`). This
  proves the new hard-limit behavior, not a reproduced memory-corruption exploit.
- `rfc1867_incomplete_class_reserved_props.phpt` observes the baseline accepting
  reserved bookkeeping properties and producing two warnings for corrupt data.
  The patch refuses those reserved properties and emits the expected one warning.
- `rfc1867_no_user_code.phpt` observes the baseline trying early class resolution,
  losing the session record and leaving `_SESSION` visible before `session_start`.
  The patch preserves the record and meets the early-deserialization expectations.
  These session cases use CGI and synthetic records; they do not induce OOM or
  recreate the retained Apache/FPM worker stale-pointer chain.

Both next-request tests pass on both versions. Their local server runs with `-n`
and explicit test INI; this does not exercise KMP's Apache module, normal PHP INI,
application extensions, tenant routes, or production AMD64 image. The minimal
source builds are native ARM64, NTS, with CLI, CGI and session support, using:

```sh
CFLAGS='-O0 -g0' ./configure --disable-all --enable-cli --enable-cgi     --enable-session --disable-phpdbg --without-pear --prefix=/opt/php-regression
make -j2
```

The existing development image's PHP 8.4.24 runs the same upstream runner for both
versions; `-p` selects each rebuilt target interpreter. The exact runner invocation
is below, with `TEST_PHP_CGI_EXECUTABLE` set to the corresponding rebuilt CGI:

```sh
export TEST_PHP_EXECUTABLE=/artifacts/build-$version/php
export TEST_PHP_CGI_EXECUTABLE=/artifacts/build-$version/php-cgi
export NO_INTERACTION=1 REPORT_EXIT_STATUS=1
php -n run-tests.php -q -n -p "$TEST_PHP_EXECUTABLE" --offline     --show-diff --show-out --set-timeout 60     Zend/tests/oom_after_main_script.phpt     Zend/tests/oom_hard_limit_exceeded_during_complilation.phpt     Zend/tests/oom_soft_limit_exceeded.phpt     Zend/tests/oom_pending_at_shutdown.phpt     Zend/tests/oom_request_parsing.phpt     sapi/cli/tests/oom_request_parsing_next_request.phpt     sapi/cli/tests/oom_shutdown_function_next_request.phpt     ext/session/tests/rfc1867_no_user_code.phpt     ext/session/tests/rfc1867_incomplete_class_reserved_props.phpt
```

The source build ran in a disposable container with `--init`, no network, read-only root,
all capabilities dropped, no-new-privileges, non-root UID/GID, 2 CPUs, 2 GiB memory
and swap ceiling, 128 PIDs, a 1,500 MiB `/work` tmpfs and 64 MiB `/tmp` tmpfs, and a
900-second process timeout. Only verified test inputs and a private results
directory were mounted. The two test containers used the same isolation with
1 CPU, 512 MiB memory/swap ceiling, 64 PIDs, 128 MiB `/work` tmpfs, 64 MiB `/tmp`
tmpfs, a 240-second suite timeout and 60-second per-test timeout. No ports, Docker
socket, repository, application data or secrets were mounted. Localhost sockets
remained entirely inside the isolated container.

`OOMKilled=false` was recorded for both full-suite containers. A targeted rerun
of only the hard-limit case additionally recorded cgroup `oom=0` and `oom_kill=0`
before and after on both versions. Peak container memory was 24,768,512 bytes for
8.5.11 and 35,540,992 bytes for 8.5.12, below the 512 MiB ceiling.
The allocator calls `_exit(137)` after the hard-limit diagnostic; the upstream
runner also formats exit codes 129 through 159 as `Termsig=exitcode-128`.
Consequently `Termsig=9` here is not evidence of a real signal, SIGKILL, heap
crash, or container OOM event. See the
[allocator](https://github.com/php/php-src/blob/136e049ac10b032c83018e65b63b6df858943750/Zend/zend_alloc.c#L483)
and [runner](https://github.com/php/php-src/blob/136e049ac10b032c83018e65b63b6df858943750/run-tests.php#L1234).

### Source and test integrity

Both source archives were checked against the [8.5.11 release metadata](https://www.php.net/releases/index.php?json&version=8.5.11)
and [8.5.12 release metadata](https://www.php.net/releases/index.php?json&version=8.5.12)
over HTTPS before extraction or execution:

```text
d9be75c08e8c316f4c8f4194d8fbe1750a15f6a6d9d4e3fe72082abeeb800360  php-8.5.11.tar.xz
08bc2eba2a588654ade1357174aa646b761556c862dda0678345ee29ed481209  php-8.5.12.tar.xz
```

The exact same test/helper/runner bytes were copied into fresh writable scratch
for both runs. SHA256 values are:

```text
46b9f109962708fbdeb68022830e0826ec27ffdec63a88c6e0d81198c1ab02ef  Zend/tests/oom_after_main_script.phpt
39b0ed52644d53fdfdb1b990704d4f7adeb75d70b1e11feee0752b69136c8e11  Zend/tests/oom_hard_limit_exceeded_during_complilation.phpt
fc03f1db7b4cd4e1bd39b43e7ea858ae55bd005cf56551897e8fc26e1f6693d0  Zend/tests/oom_pending_at_shutdown.phpt
22ea84377254dfd56f1f95f9f30f148b3acd786860f7e21e33c3592ee9840b89  Zend/tests/oom_request_parsing.phpt
ec8f05666adbb43921e4e2cd22439481174fcf26d5f4020466a7b243fcb0472d  Zend/tests/oom_soft_limit_exceeded.phpt
b3ecd87bdb508f65f7ffd220734ab84f7beb920b80a41f6751438667ef4e16ee  ext/session/tests/rfc1867_incomplete_class_reserved_props.phpt
220046e362cacd61b27a79e468b5f738ce541cb52b2b509c424fdd7a8e52890a  ext/session/tests/rfc1867_no_user_code.phpt
5cdb71271cc6a800982f7e478e4387ab375477cbcc355a21724c8bb4c99e1980  ext/session/tests/skipif.inc
c1f4d3ebcdcc734daa7ef29bb191642a905700da4431c4e095ca9e17c0127161  run-tests.php
5ad0bf4f5ed9d742efbd7226d820c916b39a0f869cd1d856a79c084ed54c8cab  sapi/cli/tests/oom_request_parsing_next_request.phpt
2fa6753ab45cb76d1284d11860e7a6b4347d4a1f7fb279deb5a386f6fbe5ce70  sapi/cli/tests/oom_shutdown_function_next_request.phpt
b4dddad9194a4979235ca382a9b6b3e2523b28f2dbfdf3f16cef6a9efdadf65a  sapi/cli/tests/php_cli_server.inc
```

The official release tarball expands the runner's Git `$Id$` marker to
`3d0bc7241ba32687a1a49aa1978397c20f39d24c`; that export substitution explains
its different byte hash from the repository file. The runner otherwise matches
the immutable release after final-newline normalization. The two next-request
tests, their helper, the request-parsing test and both session tests matched the
pinned GitHub blobs directly. The remaining cases were read from the verified
archive and reviewed in the joint fix.

### Raw evidence and remaining validation

Private raw evidence for this run is under
`/tmp/kmp-php-regressions-pej59jle` on the task host. These temporary files are
not committed and must be retained separately if longer-term raw audit evidence
is required:

- `inputs/build.sh`, `inputs/run-suite.sh` and `inputs/run-hardlimit.sh` contain
  the exact executed scripts; `inputs/test-manifest.json` records test hashes.
- `inputs/release-8.5.11.json` and `inputs/release-8.5.12.json` record the source
  checksum metadata; both verified source archives are in `inputs/`.
- `results/build-8.5.11/` and `results/build-8.5.12/` contain configure/make logs,
  interpreter versions, executables and their hashes.
- `results/run-8.5.11/` and `results/run-8.5.12/` contain the exact Docker command
  arrays (`container-command.json`), container states, runner logs, exit codes,
  copied PHPT artifacts and baseline `.diff`/`.out` files.
- `results/hardlimit-8.5.11/` and `results/hardlimit-8.5.12/` contain targeted
  runner logs, exact commands, container states, cgroup counters and memory peaks.
- `results/comparison.json` contains the per-test matrix and integrity manifest.

This demonstrates four expected behavioral changes in the official fix; it does
not certify full RCE prevention, prove application reachability, or establish the
safety of any deployed KMP instance. The actual KMP candidate still needs a
verified patched official image digest, native/application builds, checks under
its real PHP configuration, vulnerability scanning, and POC validation. Repeat
applicable cases against that exact candidate. Report missing CGI tests or other
prerequisites as unrun; do not credit a test as fix evidence unless the same case
first distinguishes the vulnerable baseline. No public exploit or production
request was used, and no PHP/upload/session security setting was changed.
