#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  verify-web-revision.sh \
    --resource-group RG \
    --web-app APP \
    --container CONTAINER \
    --revision REVISION \
    --image IMAGE

Waits for an exact Container App revision to become both latest and ready, then
verifies that the revision runs the expected image.
EOF
}

resource_group=''
web_app=''
container=''
revision=''
image=''

while [[ $# -gt 0 ]]; do
    case "$1" in
        --resource-group) resource_group="$2"; shift 2 ;;
        --web-app) web_app="$2"; shift 2 ;;
        --container) container="$2"; shift 2 ;;
        --revision) revision="$2"; shift 2 ;;
        --image) image="$2"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 64 ;;
    esac
done

for value in resource_group web_app container revision image; do
    if [[ -z "${!value}" ]]; then
        echo "Missing required argument: $value" >&2
        exit 64
    fi
done

for command in az jq; do
    command -v "$command" >/dev/null || {
        echo "Required command not found: $command" >&2
        exit 69
    }
done

attempts="${KMP_REVISION_VERIFY_ATTEMPTS:-120}"
delay_seconds="${KMP_REVISION_VERIFY_DELAY_SECONDS:-5}"

if [[ ! "$attempts" =~ ^[1-9][0-9]*$ ]]; then
    echo 'KMP_REVISION_VERIFY_ATTEMPTS must be a positive integer.' >&2
    exit 64
fi
if [[ ! "$delay_seconds" =~ ^[0-9]+$ ]]; then
    echo 'KMP_REVISION_VERIFY_DELAY_SECONDS must be a non-negative integer.' >&2
    exit 64
fi

for attempt in $(seq 1 "$attempts"); do
    revision_state="$(
        az containerapp show \
            --resource-group "$resource_group" \
            --name "$web_app" \
            --query '{
                provisioningState: properties.provisioningState,
                latestRevision: properties.latestRevisionName,
                readyRevision: properties.latestReadyRevisionName
            }' \
            --output json
    )"
    provisioning_state="$(jq -r '.provisioningState // empty' <<< "$revision_state")"
    latest_revision="$(jq -r '.latestRevision // empty' <<< "$revision_state")"
    ready_revision="$(jq -r '.readyRevision // empty' <<< "$revision_state")"

    case "$provisioning_state" in
        Failed|Canceled|Cancelled)
            echo "Web update failed while creating revision $revision." >&2
            exit 1
            ;;
    esac

    if [[ "$latest_revision" == "$revision" && "$ready_revision" == "$revision" ]]; then
        deployed_image="$(
            az containerapp revision show \
                --resource-group "$resource_group" \
                --name "$web_app" \
                --revision "$revision" \
                --query "properties.template.containers[?name=='$container'].image | [0]" \
                --output tsv
        )"
        if [[ "$deployed_image" != "$image" ]]; then
            echo "Ready revision $revision uses unexpected image $deployed_image." >&2
            exit 1
        fi

        echo "Verified $web_app revision $revision with image $image."
        exit 0
    fi

    if [[ "$attempt" -eq "$attempts" ]]; then
        echo "Timed out waiting for $web_app revision $revision to become ready." >&2
        exit 1
    fi
    sleep "$delay_seconds"
done

echo "Revision verification ended without confirming $revision." >&2
exit 1
