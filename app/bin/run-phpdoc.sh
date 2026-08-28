#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TOOLS_DIR="$APP_ROOT/.phpdoc"
PHPDOC_VERSION="3.10.0"
PHAR_PATH="$TOOLS_DIR/phpDocumentor-$PHPDOC_VERSION.phar"
PHAR_URL="https://github.com/phpDocumentor/phpDocumentor/releases/download/v$PHPDOC_VERSION/phpDocumentor.phar"
PHAR_SHA256="fe1e7c23ba3329aa6f19ac3c807446159a431a195ec5d9163b0c281a15105207"
DEFAULT_OUTPUT_DIR="$APP_ROOT/../docs/api/php"

require_command() {
  local command_name="$1"

  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "Required phpDocumentor tool is not available: $command_name" >&2
    exit 1
  fi
}

if [ "$#" -gt 1 ]; then
  echo "Usage: $0 [output-directory]" >&2
  exit 2
fi

OUTPUT_DIR="${1:-$DEFAULT_OUTPUT_DIR}"
if [[ "$OUTPUT_DIR" != /* ]]; then
  OUTPUT_DIR="$(pwd -P)/$OUTPUT_DIR"
fi
DIAGNOSTIC_REPORT="$OUTPUT_DIR/reports/errors.html"

for required_command in php curl sha256sum grep mktemp mkdir chmod mv rm; do
  require_command "$required_command"
done

if [ -L "$OUTPUT_DIR" ] || { [ -e "$OUTPUT_DIR" ] && [ ! -d "$OUTPUT_DIR" ]; }; then
  echo "phpDocumentor output must be a real directory or absent: $OUTPUT_DIR" >&2
  exit 1
fi

if [ -L "$PHAR_PATH" ] || { [ -e "$PHAR_PATH" ] && [ ! -f "$PHAR_PATH" ]; }; then
  echo "phpDocumentor tool path must be a real file or absent: $PHAR_PATH" >&2
  exit 1
fi

mkdir -p "$TOOLS_DIR" "$OUTPUT_DIR"

download_phpdocumentor() {
  local temp_phar

  temp_phar="$(mktemp "$TOOLS_DIR/phpDocumentor.phar.XXXXXX")"

  echo "Downloading phpDocumentor $PHPDOC_VERSION..."
  if ! curl --fail --location --retry 3 --show-error --silent "$PHAR_URL" --output "$temp_phar"; then
    rm -f -- "$temp_phar"
    return 1
  fi

  if ! printf '%s  %s\n' "$PHAR_SHA256" "$temp_phar" | sha256sum --check --status; then
    echo "phpDocumentor checksum verification failed." >&2
    rm -f -- "$temp_phar"
    return 1
  fi

  if ! chmod +x "$temp_phar" || ! mv -f -- "$temp_phar" "$PHAR_PATH"; then
    rm -f -- "$temp_phar"
    return 1
  fi
}

if [ ! -f "$PHAR_PATH" ] ||
  ! printf '%s  %s\n' "$PHAR_SHA256" "$PHAR_PATH" | sha256sum --check --status; then
  download_phpdocumentor
fi

cd "$APP_ROOT"
php "$PHAR_PATH" run --config="$APP_ROOT/phpdoc.dist.xml" --target="$OUTPUT_DIR"

if [ ! -f "$DIAGNOSTIC_REPORT" ]; then
  echo "phpDocumentor did not create its expected diagnostic report: $DIAGNOSTIC_REPORT" >&2
  exit 1
fi

ERROR_COUNT="$(grep -cF '<td class="phpdocumentor-cell">ERROR</td>' "$DIAGNOSTIC_REPORT" || true)"
WARNING_COUNT="$(grep -cF '<td class="phpdocumentor-cell">WARNING</td>' "$DIAGNOSTIC_REPORT" || true)"
if [[ ! "$ERROR_COUNT" =~ ^[0-9]+$ ]] || [[ ! "$WARNING_COUNT" =~ ^[0-9]+$ ]]; then
  echo "Could not read phpDocumentor diagnostics from $DIAGNOSTIC_REPORT." >&2
  exit 1
fi

if ((ERROR_COUNT > 0 || WARNING_COUNT > 0)); then
  echo "phpDocumentor reported $ERROR_COUNT error(s) and $WARNING_COUNT warning(s)." >&2
  echo "Review $DIAGNOSTIC_REPORT for file and line details." >&2
  exit 1
fi

if [ ! -f "$OUTPUT_DIR/index.html" ]; then
  echo "phpDocumentor did not create its expected index: $OUTPUT_DIR/index.html" >&2
  exit 1
fi

echo "phpDocumentor validation passed with no reported errors or warnings."
