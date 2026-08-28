#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/app"
DOCS_DIR="$ROOT_DIR/docs"
API_OUTPUT_ROOT="$DOCS_DIR/api"
LOCK_FILE="$DOCS_DIR/.api-docs.lock"
WORK_DIR=""
STAGED_API=""
PHP_STAGE=""
JS_STAGE=""
BACKUP_API=""
TRANSACTION_STARTED=0
PUBLISH_STARTED=0
PUBLISH_COMPLETE=0

require_command() {
  local command_name="$1"

  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "Required documentation tool is not available: $command_name" >&2
    exit 1
  fi
}

path_exists() {
  [ -e "$1" ] || [ -L "$1" ]
}

ensure_real_directory() {
  local target="$1"
  local description="$2"

  if [ -L "$target" ] || { path_exists "$target" && [ ! -d "$target" ]; }; then
    echo "$description must be a real directory or absent: $target" >&2
    return 1
  fi

  mkdir -p "$target"
}

remove_staging_directory() {
  local target="$1"

  case "$target" in
    "$DOCS_DIR"/.api-docs-build.*)
      ;;
    *)
      echo "Refusing to remove unexpected API documentation staging path: $target" >&2
      return 1
      ;;
  esac

  if [ -L "$target" ] || [ ! -d "$target" ]; then
    echo "Refusing to remove an invalid API documentation staging path: $target" >&2
    return 1
  fi

  rm -rf -- "$target"
}

remove_work_directory() {
  if [ -z "$WORK_DIR" ]; then
    return 0
  fi

  if ! remove_staging_directory "$WORK_DIR"; then
    return 1
  fi

  WORK_DIR=""
}

recover_orphaned_builds() {
  local orphan
  local recovery_dir=""

  if path_exists "$API_OUTPUT_ROOT" &&
    { [ -L "$API_OUTPUT_ROOT" ] || [ ! -d "$API_OUTPUT_ROOT" ]; }; then
    echo "API documentation root is not a real directory: $API_OUTPUT_ROOT" >&2
    return 1
  fi

  shopt -s nullglob
  for orphan in "$DOCS_DIR"/.api-docs-build.*; do
    if [ -L "$orphan" ] || [ ! -d "$orphan" ]; then
      echo "Refusing invalid orphaned API documentation staging path: $orphan" >&2
      shopt -u nullglob
      return 1
    fi

    if path_exists "$orphan/original-api"; then
      if [ -L "$orphan/original-api" ] || [ ! -d "$orphan/original-api" ]; then
        echo "Refusing invalid API documentation recovery tree: $orphan/original-api" >&2
        shopt -u nullglob
        return 1
      fi

      if ! path_exists "$API_OUTPUT_ROOT"; then
        if [ -n "$recovery_dir" ]; then
          echo "Multiple API documentation recovery trees require manual review." >&2
          shopt -u nullglob
          return 1
        fi
        recovery_dir="$orphan"
      fi
    fi
  done
  shopt -u nullglob

  if [ -n "$recovery_dir" ]; then
    echo "Restoring API documentation interrupted during publication." >&2
    mv -T -- "$recovery_dir/original-api" "$API_OUTPUT_ROOT"
  fi

  shopt -s nullglob
  for orphan in "$DOCS_DIR"/.api-docs-build.*; do
    remove_staging_directory "$orphan"
  done
  shopt -u nullglob
}

copy_handwritten_api_content() {
  local entry
  local entry_name

  shopt -s dotglob nullglob
  for entry in "$API_OUTPUT_ROOT"/*; do
    entry_name="${entry##*/}"
    case "$entry_name" in
      php|js)
        continue
        ;;
    esac

    cp -a -- "$entry" "$STAGED_API/"
  done
  shopt -u dotglob nullglob
}

rollback_publication() {
  local rollback_failed=0

  echo "API documentation publication failed; restoring the previous API tree." >&2

  if [ "$PUBLISH_STARTED" -eq 1 ] && path_exists "$API_OUTPUT_ROOT"; then
    if ! mv -T -- "$API_OUTPUT_ROOT" "$WORK_DIR/failed-api"; then
      echo "Could not move the failed API tree out of the live path." >&2
      rollback_failed=1
    fi
  fi

  if path_exists "$BACKUP_API"; then
    if path_exists "$API_OUTPUT_ROOT" || ! mv -T -- "$BACKUP_API" "$API_OUTPUT_ROOT"; then
      echo "Could not restore the previous API tree." >&2
      rollback_failed=1
    fi
  fi

  return "$rollback_failed"
}

cleanup() {
  local status="$?"
  local rollback_succeeded=1

  trap - EXIT HUP INT TERM

  if [ "$status" -ne 0 ] && [ "$TRANSACTION_STARTED" -eq 1 ] && [ "$PUBLISH_COMPLETE" -eq 0 ]; then
    if ! rollback_publication; then
      rollback_succeeded=0
      status=1
    fi
  fi

  if [ "$rollback_succeeded" -eq 1 ]; then
    if ! remove_work_directory; then
      status=1
    fi
  else
    echo "Preserved recovery data at $WORK_DIR." >&2
  fi

  exit "$status"
}

publish_api_tree() {
  if [ -L "$API_OUTPUT_ROOT" ] || [ ! -d "$API_OUTPUT_ROOT" ]; then
    echo "Live API documentation path changed during generation: $API_OUTPUT_ROOT" >&2
    return 1
  fi

  if [ -L "$STAGED_API" ] || [ ! -d "$STAGED_API" ]; then
    echo "Staged API documentation tree is not a real directory: $STAGED_API" >&2
    return 1
  fi

  TRANSACTION_STARTED=1
  mv -T -- "$API_OUTPUT_ROOT" "$BACKUP_API"

  if path_exists "$API_OUTPUT_ROOT"; then
    echo "Live API documentation path unexpectedly reappeared during publication." >&2
    return 1
  fi

  PUBLISH_STARTED=1
  mv -T -- "$STAGED_API" "$API_OUTPUT_ROOT"
  PUBLISH_COMPLETE=1
}

trap cleanup EXIT
trap 'exit 129' HUP
trap 'exit 130' INT
trap 'exit 143' TERM

for required_command in bash php curl sha256sum grep npm mktemp mkdir cp mv rm flock; do
  require_command "$required_command"
done

ensure_real_directory "$DOCS_DIR" "Documentation root"

if [ -L "$LOCK_FILE" ] || { path_exists "$LOCK_FILE" && [ ! -f "$LOCK_FILE" ]; }; then
  echo "API documentation lock path must be a real file or absent: $LOCK_FILE" >&2
  exit 1
fi

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "Another API documentation build is already running." >&2
  exit 1
fi

recover_orphaned_builds
ensure_real_directory "$API_OUTPUT_ROOT" "API documentation root"

WORK_DIR="$(mktemp -d "$DOCS_DIR/.api-docs-build.XXXXXX")"
STAGED_API="$WORK_DIR/staged-api"
PHP_STAGE="$STAGED_API/php"
JS_STAGE="$STAGED_API/js"
BACKUP_API="$WORK_DIR/original-api"
mkdir -p "$STAGED_API"
copy_handwritten_api_content
mkdir -p "$PHP_STAGE" "$JS_STAGE"

bash "$APP_DIR/bin/run-phpdoc.sh" "$PHP_STAGE"
(
  cd "$APP_DIR"
  npm run docs:js --silent -- --destination "$JS_STAGE"
)

if [ ! -f "$PHP_STAGE/index.html" ]; then
  echo "phpDocumentor did not create the expected staged index: $PHP_STAGE/index.html" >&2
  exit 1
fi

if [ ! -f "$JS_STAGE/index.html" ]; then
  echo "JSDoc did not create the expected staged index: $JS_STAGE/index.html" >&2
  exit 1
fi

publish_api_tree
remove_work_directory
WORK_DIR=""

echo "API documentation refreshed in docs/api/php and docs/api/js."
