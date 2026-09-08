#!/usr/bin/env bash
set -euo pipefail
repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
network="kmp-security-pg-$$"
database="kmp-security-pg-$$"
cleanup() {
    docker rm -f "$database" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker network create --internal "$network" >/dev/null
docker run --rm -d --name "$database" --network "$network" --network-alias postgres \
    -e POSTGRES_PASSWORD=synthetic-disposable-test-password postgres:16 >/dev/null
ready=false
for attempt in {1..30}; do
    if docker exec "$database" pg_isready -U postgres >/dev/null 2>&1; then ready=true; break; fi
    sleep 1
done
"$ready" || { echo 'Disposable PostgreSQL did not become ready.' >&2; exit 1; }
source_mount=()
if [[ "${KMP_TEST_USE_IMAGE_SOURCE:-0}" != '1' ]]; then
    source_mount=(-v "$repo/app:/var/www/html:ro")
fi
docker run --rm --network "$network" --entrypoint php \
    -e XDEBUG_MODE=off \
    "${source_mount[@]}" -v "$repo/security:/security:ro" \
    "${KMP_TEST_PHP_IMAGE:-kmp-app:dev}" /security/test-postgres-privileges.php
