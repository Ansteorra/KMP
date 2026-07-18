#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  update-web-runtime.sh --resource-group RG --web-app APP --image IMAGE [--dry-run]

Updates the web revision atomically with the request-only environment contract
and split ACA probes. Existing environment variables, secret references,
resources, scale rules, and ingress configuration are preserved.
EOF
}

resource_group=''
web_app=''
image=''
dry_run=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --resource-group) resource_group="$2"; shift 2 ;;
        --web-app) web_app="$2"; shift 2 ;;
        --image) image="$2"; shift 2 ;;
        --dry-run) dry_run=true; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown argument: $1" >&2; usage >&2; exit 64 ;;
    esac
done

for value in resource_group web_app image; do
    if [[ -z "${!value}" ]]; then
        echo "Missing required argument: $value" >&2
        exit 64
    fi
done

if "$dry_run"; then
    printf 'Would update %s/%s to %s with request-only flags and split probes.\n' \
        "$resource_group" "$web_app" "$image"
    exit 0
fi

for command in az jq; do
    command -v "$command" >/dev/null || {
        echo "Required command not found: $command" >&2
        exit 69
    }
done

current_file="$(mktemp)"
patch_file="$(mktemp)"
trap 'rm -f "$current_file" "$patch_file"' EXIT

az containerapp show \
    --resource-group "$resource_group" \
    --name "$web_app" \
    --output json > "$current_file"

resource_id="$(jq -r '.id' "$current_file")"
container_name="$(jq -r '.properties.template.containers[0].name' "$current_file")"

jq --arg image "$image" --arg container "$container_name" '
    {
        properties: {
            template: (
                .properties.template
                | .containers |= map(
                    if .name == $container then
                        .image = $image
                        | .env = (
                            (.env // [] | map(select(
                                .name != "KMP_SKIP_CRON"
                                and .name != "KMP_SKIP_MIGRATIONS"
                            )))
                            + [
                                {"name": "KMP_SKIP_CRON", "value": "true"},
                                {"name": "KMP_SKIP_MIGRATIONS", "value": "true"}
                            ]
                        )
                        | .probes = [
                            {
                                "type": "Liveness",
                                "httpGet": {"path": "/livez", "port": 80},
                                "initialDelaySeconds": 30,
                                "periodSeconds": 60,
                                "timeoutSeconds": 2,
                                "failureThreshold": 3
                            },
                            {
                                "type": "Readiness",
                                "httpGet": {"path": "/health", "port": 80},
                                "initialDelaySeconds": 30,
                                "periodSeconds": 60,
                                "timeoutSeconds": 5,
                                "failureThreshold": 3
                            }
                        ]
                    else
                        .
                    end
                )
            )
        }
    }
' "$current_file" > "$patch_file"

az rest \
    --method patch \
    --uri "https://management.azure.com${resource_id}?api-version=2024-03-01" \
    --body "@$patch_file" \
    --output none

echo "Updated $web_app to $image with request-only flags and split probes."
