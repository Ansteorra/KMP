#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/app"

pushd "$APP_DIR" >/dev/null

if command -v composer >/dev/null 2>&1; then
  composer docs:php --no-ansi --no-interaction
else
  echo "[warn] Composer not found on PATH; skipping PHP API docs" >&2
fi

if command -v npm >/dev/null 2>&1; then
  npm run docs:js --silent
else
  echo "[warn] npm not found on PATH; skipping JavaScript API docs" >&2
fi

popd >/dev/null

echo "API documentation refreshed in docs/api/php and docs/api/js."
