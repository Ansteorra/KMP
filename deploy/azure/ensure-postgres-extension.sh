#!/usr/bin/env bash
# =============================================================================
# ensure-postgres-extension.sh
#
# Idempotently adds a PostgreSQL extension to the azure.extensions allowlist
# on an Azure Database for PostgreSQL Flexible Server. Existing allowlisted
# extensions are preserved; the check is case-insensitive.
#
# Usage:
#   ensure-postgres-extension.sh \
#       --resource-group RG \
#       --server-name SERVER_NAME \
#       --extension CITEXT \
#       [--dry-run]
#
# Requirements:
#   az CLI authenticated with permission to read and update Flexible Server
#   configurations (Contributor or PostgreSQL Flexible Server Contributor
#   on the resource group).
#
# Exit codes:
#   0  — extension is (or will be) present
#   1  — unexpected error
#   64 — bad arguments
#   69 — required CLI tool not found
# =============================================================================
set -euo pipefail

usage() {
    sed -n '3,23p' "$0"
}

resource_group=''
server_name=''
extension=''
dry_run=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --resource-group) resource_group="$2"; shift 2 ;;
        --server-name)    server_name="$2";    shift 2 ;;
        --extension)      extension="$2";      shift 2 ;;
        --dry-run)        dry_run=true;        shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 64 ;;
    esac
done

for value in resource_group server_name extension; do
    if [[ -z "${!value}" ]]; then
        echo "Missing required argument: --${value//_/-}" >&2
        exit 64
    fi
done

for cmd in az; do
    command -v "$cmd" >/dev/null 2>&1 || {
        echo "Required command not found: $cmd" >&2
        exit 69
    }
done

# Normalise to uppercase for consistent comparison and storage.
ext_upper="$(printf '%s' "$extension" | tr '[:lower:]' '[:upper:]')"

echo "==> Checking azure.extensions on $server_name (RG: $resource_group)"

current_value="$(az postgres flexible-server parameter show \
    --resource-group "$resource_group" \
    --server-name "$server_name" \
    --name 'azure.extensions' \
    --query 'value' \
    --output tsv)"

# Split into an array (comma-separated, tolerate spaces around commas).
existing_exts=()
IFS=',' read -ra existing_exts <<< "$current_value" || true
already_present=false
normalised_list=()
for ext in "${existing_exts[@]}"; do
    trimmed="${ext#"${ext%%[! ]*}"}"   # ltrim
    trimmed="${trimmed%"${trimmed##*[! ]}"}"  # rtrim
    [[ -z "$trimmed" ]] && continue
    normalised_list+=("$trimmed")
    if [[ "$(printf '%s' "$trimmed" | tr '[:lower:]' '[:upper:]')" == "$ext_upper" ]]; then
        already_present=true
    fi
done

if "$already_present"; then
    echo "    $ext_upper is already in azure.extensions — no change needed."
    exit 0
fi

# Build the new value by appending the requested extension.
normalised_list+=("$ext_upper")
new_value="$(IFS=','; printf '%s' "${normalised_list[*]}")"

if "$dry_run"; then
    echo "    [dry-run] Would set azure.extensions to: $new_value"
    exit 0
fi

echo "    Adding $ext_upper; new value: $new_value"
az postgres flexible-server parameter set \
    --resource-group "$resource_group" \
    --server-name "$server_name" \
    --name 'azure.extensions' \
    --value "$new_value" \
    --output none

echo "    Done. azure.extensions = $new_value"
