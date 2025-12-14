#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TOOLS_DIR="$APP_ROOT/.phpdoc"
PHAR_PATH="$TOOLS_DIR/phpDocumentor.phar"
PHAR_URL="https://phpdoc.org/phpDocumentor.phar"

mkdir -p "$TOOLS_DIR"

if [ ! -f "$PHAR_PATH" ]; then
  echo "Downloading phpDocumentor PHAR..."
  curl -Ls "$PHAR_URL" -o "$PHAR_PATH"
  chmod +x "$PHAR_PATH"
fi

php "$PHAR_PATH" run -c "$APP_ROOT/phpdoc.dist.xml"
