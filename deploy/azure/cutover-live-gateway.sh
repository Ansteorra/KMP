#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
resource_group=''
app=''
image=''
snapshot_dir=''
while [[ $# -gt 0 ]]; do
    case "$1" in
        --resource-group) resource_group="$2"; shift 2 ;;
        --app) app="$2"; shift 2 ;;
        --image) image="$2"; shift 2 ;;
        --snapshot-dir) snapshot_dir="$2"; shift 2 ;;
        *) echo "Unknown gateway cutover argument: $1" >&2; exit 64 ;;
    esac
done
for variable in resource_group app image snapshot_dir; do
    [[ -n "${!variable}" ]] || { echo "Missing gateway argument: $variable" >&2; exit 64; }
done
[[ "$image" =~ @sha256:[0-9a-f]{64}$ ]] || { echo 'Gateway cutover requires an immutable image.' >&2; exit 64; }
mkdir -p "$snapshot_dir"
umask 077
az containerapp show --resource-group "$resource_group" --name "$app" --output json > "$snapshot_dir/live.json"
previous="$(jq -r '.properties.template.containers[] | select(.name == "live") | .image' "$snapshot_dir/live.json")"
[[ -n "$previous" ]] || { echo 'Gateway has no registered live container.' >&2; exit 1; }
[[ "$previous" =~ @sha256:[0-9a-f]{64}$ ]] || { echo 'Existing gateway image must be immutable before cutover.' >&2; exit 1; }
revision="$(az containerapp update --resource-group "$resource_group" --name "$app" --image "$image" \
    --query properties.latestRevisionName --output tsv --only-show-errors)"
if ! bash "$here/verify-web-revision.sh" --resource-group "$resource_group" --web-app "$app" \
    --container live --revision "$revision" --image "$image"; then
    echo 'Gateway cutover failed; restoring its previous image. Web cutover will not proceed.' >&2
    rollback_revision="$(az containerapp update --resource-group "$resource_group" --name "$app" --image "$previous" \
        --query properties.latestRevisionName --output tsv --only-show-errors)"
    bash "$here/verify-web-revision.sh" --resource-group "$resource_group" --web-app "$app" \
        --container live --revision "$rollback_revision" --image "$previous"
    exit 1
fi
