#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOCS_DIR="$ROOT_DIR/docs"
PORT="${JEKYLL_PORT:-4000}"
HOST="${JEKYLL_HOST:-127.0.0.1}"
LIVERELOAD_FLAG="${JEKYLL_LIVERELOAD:-true}"

if ! command -v bundle >/dev/null 2>&1; then
  cat >&2 <<'EOF'
[error] Bundler (bundle) command not found. Install it with:
    gem install bundler
EOF
  exit 1
fi

export BUNDLE_GEMFILE="$DOCS_DIR/Gemfile"
export BUNDLE_PATH="$DOCS_DIR/vendor/bundle"

pushd "$DOCS_DIR" >/dev/null

bundle install --quiet --jobs="${BUNDLE_JOBS:-4}" --retry="${BUNDLE_RETRY:-3}" --path "$BUNDLE_PATH"

SERVE_ARGS=(
  "--host" "$HOST"
  "--port" "$PORT"
)

if [[ "$LIVERELOAD_FLAG" == "true" ]]; then
  SERVE_ARGS+=("--livereload")
fi

bundle exec jekyll serve "${SERVE_ARGS[@]}"

popd >/dev/null
