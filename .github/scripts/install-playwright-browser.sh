#!/usr/bin/env bash
set -euo pipefail

if [[ "${GITHUB_ACTIONS:-}" != true || "${RUNNER_OS:-}" != Linux || $# -eq 0 ]]; then
    echo "Use this installer on a hosted Linux runner with explicit browser names." >&2
    exit 64
fi
for browser in "$@"; do
    case "$browser" in
        chromium|firefox|webkit) ;;
        *) echo "Unsupported browser: $browser" >&2; exit 64 ;;
    esac
done

# Only change the hosted runner's existing mirror list, retaining its Ubuntu keyring and suites.
if ! sudo test -f /etc/apt/apt-mirrors.txt \
    || ! sudo grep -Fq 'mirror+file:/etc/apt/apt-mirrors.txt' /etc/apt/sources.list.d/ubuntu.sources; then
    echo "Expected the GitHub Ubuntu runner's signed archive mirror configuration." >&2
    exit 1
fi
printf '%s\n' \
    $'https://mirrors.edge.kernel.org/ubuntu/\tpriority:1' \
    $'http://azure.archive.ubuntu.com/ubuntu/\tpriority:2' \
    $'https://archive.ubuntu.com/ubuntu/\tpriority:3' \
    $'https://security.ubuntu.com/ubuntu/\tpriority:4' \
    | sudo tee /etc/apt/apt-mirrors.txt >/dev/null
printf '%s\n' \
    'Acquire::Retries "2";' \
    'Acquire::http::Timeout "15";' \
    'Acquire::https::Timeout "15";' \
    'APT::Update::Error-Mode "any";' \
    | sudo tee /etc/apt/apt.conf.d/80-kmp-ci-browser-network >/dev/null

cd "$(dirname "${BASH_SOURCE[0]}")/../../app"
exec npx --no-install playwright install --with-deps "$@"
