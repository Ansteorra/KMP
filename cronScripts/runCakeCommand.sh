#!/bin/bash

# Generic wrapper for running CakePHP console commands across environments.
#
# Usage examples:
#   ./runCakeCommand.sh --workdir /home/user/app sync_active_window_statuses --dry-run
#   ./runCakeCommand.sh --workdir /home/user/app --php-bin /usr/bin/php queue run -q
#   CAKE_WORKDIR=/home/user/app ./runCakeCommand.sh queue run --max-jobs 5
#
# Optional environment variables:
#   CAKE_WORKDIR   Default workdir if --workdir is omitted
#   CAKE_PHP_BIN   Default PHP binary if --php-bin is omitted
#   CAKE_CAKE_BIN  Default Cake console path if --cake-bin is omitted

set -euo pipefail

# Defaults (can be overridden via env vars or CLI options)
WORKDIR="${CAKE_WORKDIR:-}"
PHP_BIN="${CAKE_PHP_BIN:-/usr/local/php83/bin/php}"
CAKE_BIN_OVERRIDE="${CAKE_CAKE_BIN:-}"

print_usage() {
    cat <<'USAGE'
Usage: runCakeCommand.sh [options] <cake-command> [command-args...]

Options:
  --workdir PATH     Application root containing bin/cake.php (default: $CAKE_WORKDIR)
  --php-bin PATH     PHP executable to use (default: /usr/local/php83/bin/php or $CAKE_PHP_BIN)
  --cake-bin PATH    Explicit path to cake console (default: <workdir>/bin/cake.php or $CAKE_CAKE_BIN)
  -h, --help         Show this help message
  --                 Stop option parsing; remaining args passed to Cake command

Examples:
  runCakeCommand.sh --workdir /home/vscribe/amp-uat.ansteorra.org sync_active_window_statuses
  runCakeCommand.sh --workdir /home/vscribe/amp-prod.ansteorra.org queue run -q
  CAKE_WORKDIR=/home/vscribe/amp-uat.ansteorra.org runCakeCommand.sh sync_active_window_statuses --dry-run
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --workdir)
            [[ $# -lt 2 ]] && { echo "Error: --workdir requires a value" >&2; exit 64; }
            WORKDIR="$2"
            shift 2
            ;;
        --php-bin)
            [[ $# -lt 2 ]] && { echo "Error: --php-bin requires a value" >&2; exit 64; }
            PHP_BIN="$2"
            shift 2
            ;;
        --cake-bin)
            [[ $# -lt 2 ]] && { echo "Error: --cake-bin requires a value" >&2; exit 64; }
            CAKE_BIN_OVERRIDE="$2"
            shift 2
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        --)
            shift
            break
            ;;
        *)
            break
            ;;
    esac
done

if [[ $# -eq 0 ]]; then
    echo "Error: missing Cake command" >&2
    print_usage >&2
    exit 64
fi

if [[ -z "$WORKDIR" ]]; then
    echo "Error: --workdir not set and CAKE_WORKDIR empty" >&2
    exit 64
fi

if [[ -z "$CAKE_BIN_OVERRIDE" ]]; then
    CAKE_BIN="$WORKDIR/bin/cake.php"
else
    CAKE_BIN="$CAKE_BIN_OVERRIDE"
fi

if [[ ! -d "$WORKDIR" ]]; then
    echo "Error: workdir '$WORKDIR' does not exist" >&2
    exit 72
fi

if [[ ! -x "$PHP_BIN" ]]; then
    if [[ -f "$PHP_BIN" ]]; then
        chmod +x "$PHP_BIN" >/dev/null 2>&1 || true
    fi
    if [[ ! -x "$PHP_BIN" ]]; then
        echo "Error: PHP binary '$PHP_BIN' is not executable" >&2
        exit 69
    fi
fi

if [[ ! -f "$CAKE_BIN" ]]; then
    echo "Error: cake console '$CAKE_BIN' not found" >&2
    exit 66
fi

cd "$WORKDIR"

COMMAND_ARGS=("$@")

exec "$PHP_BIN" "$CAKE_BIN" "${COMMAND_ARGS[@]}"
