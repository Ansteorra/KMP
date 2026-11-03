#!/usr/bin/env bash
# Exercise real PostgreSQL lifecycle and isolation in temporary checkouts.
# Requires the same PostgreSQL prerequisites as environment.sh; no PHP needed.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FIXTURE_DIR="$(mktemp -d)"
PG_BIN="${KMP_CODEX_PG_BINDIR:-/usr/lib/postgresql/16/bin}"
TEST_PORT="${KMP_CODEX_PGPORT:-55432}"
OTHER_PORT="$((TEST_PORT + 1))"

cleanup() {
    local fixture
    for fixture in primary other; do
        KMP_CODEX_PGPORT="$TEST_PORT" bash "$FIXTURE_DIR/$fixture/tools/codex/environment.sh" stop >/dev/null 2>&1 || true
    done
    rm -rf "$FIXTURE_DIR"
}
trap cleanup EXIT
chmod 755 "$FIXTURE_DIR"
for fixture in primary other; do
    mkdir -p "$FIXTURE_DIR/$fixture/tools/codex" "$FIXTURE_DIR/$fixture/app/config"
    cp "$SCRIPT_DIR/environment.sh" "$FIXTURE_DIR/$fixture/tools/codex/environment.sh"
    # A shell-sourced .env would execute this and make the test fail.
    printf 'exit 91\n' > "$FIXTURE_DIR/$fixture/app/config/.env"
done

primary=(bash "$FIXTURE_DIR/primary/tools/codex/environment.sh")
other=(bash "$FIXTURE_DIR/other/tools/codex/environment.sh")
export KMP_CODEX_PGPORT="$TEST_PORT"
psql=("$PG_BIN/psql" -X -h 127.0.0.1 -p "$TEST_PORT" -U kmp_codex -d postgres -v ON_ERROR_STOP=1 -At)
export PGPASSWORD=kmp_codex_disposable

"${primary[@]}" start
[[ "$("${psql[@]}" -c "SELECT count(*) FROM pg_database WHERE datname IN ('kmp_codex', 'kmp_codex_test', 'kmp_codex_platform', 'kmp_codex_platform_test')")" == 4 ]]
"${psql[@]}" -d kmp_codex_test -c 'CREATE TABLE codex_preserved (id integer); INSERT INTO codex_preserved VALUES (42);'
"${primary[@]}" start
[[ "$("${psql[@]}" -d kmp_codex_test -c 'SELECT id FROM codex_preserved')" == 42 ]]

# Verify actual command scope, argument handling, and inherited admin removal.
# shellcheck disable=SC2016 # The child shell evaluates these variables.
DATABASE_ADMIN_URL=must-not-survive KMP_ADMIN_JOB=true "${primary[@]}" run bash -c '
    set -euo pipefail
    [[ "$PWD" == "$1" && "$2" == "one argument with spaces" ]]
    [[ "$APP_NAME" == KMP && "$KMP_ENV" == development && "$KMP_LOAD_DOTENV" == false ]]
    [[ "$DB_HOST" == 127.0.0.1 && "$DB_PORT" == "$KMP_CODEX_PGPORT" ]]
    [[ "$DATABASE_TEST_URL" == *"/kmp_codex_test" ]]
    [[ "$PLATFORM_DATABASE_TEST_URL" == *"/kmp_codex_platform_test" ]]
    [[ ! -v DATABASE_ADMIN_URL && ! -v KMP_ADMIN_JOB ]]
' _ "$FIXTURE_DIR/primary/app" 'one argument with spaces'
if "${primary[@]}" run bash -c 'exit 23'; then
    echo 'Command failure was swallowed.' >&2
    exit 1
else
    [[ "$?" == 23 ]]
fi

"${primary[@]}" stop
"${primary[@]}" stop
"${primary[@]}" start
[[ "$("${psql[@]}" -d kmp_codex_test -c 'SELECT id FROM codex_preserved')" == 42 ]]

KMP_CODEX_PGPORT="$OTHER_PORT" "${other[@]}" start
"${psql[@]}" -p "$OTHER_PORT" -c 'DROP DATABASE kmp_codex_platform'
if KMP_CODEX_PGPORT="$OTHER_PORT" "${primary[@]}" start; then
    echo 'Foreign cluster was accepted.' >&2
    exit 1
fi
[[ "$("${psql[@]}" -p "$OTHER_PORT" -c "SELECT count(*) FROM pg_database WHERE datname = 'kmp_codex_platform'")" == 0 ]]
if KMP_CODEX_PGPORT=05432 "${primary[@]}" start; then
    echo 'Invalid port was accepted.' >&2
    exit 1
fi

echo 'Codex lifecycle, command environment, persistence, and isolation checks passed.'
