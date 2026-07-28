#!/usr/bin/env bash

set -euo pipefail

if [[ "$#" -lt 2 || "$#" -gt 5 ]]; then
    echo "Usage: $0 <workflow-file> <commit-sha> [branch] [event] [wait-seconds]" >&2
    exit 64
fi

workflow_file="$1"
commit_sha="$2"
branch="${3:-main}"
event="${4:-push}"
wait_seconds="${5:-1800}"
repository="${GITHUB_REPOSITORY:-}"

if [[ -z "$repository" ]]; then
    echo "GITHUB_REPOSITORY is required." >&2
    exit 64
fi
if [[ ! "$workflow_file" =~ ^[A-Za-z0-9_.-]+$ ]]; then
    echo "Invalid workflow filename: $workflow_file" >&2
    exit 64
fi
if [[ ! "$commit_sha" =~ ^[0-9a-fA-F]{40}$ ]]; then
    echo "Invalid commit SHA: $commit_sha" >&2
    exit 64
fi
if [[ ! "$wait_seconds" =~ ^[0-9]+$ ]]; then
    echo "Invalid wait duration: $wait_seconds" >&2
    exit 64
fi

deadline=$((SECONDS + wait_seconds))

while true; do
    response="$(
        gh api \
            --method GET \
            "repos/${repository}/actions/workflows/${workflow_file}/runs" \
            -f branch="$branch" \
            -f head_sha="$commit_sha" \
            -F per_page=100
    )"

    successful_run="$(
        jq -r \
            --arg event "$event" \
            '[.workflow_runs[]
                | select(.event == $event and .status == "completed" and .conclusion == "success")]
                | first
                | .html_url // empty' \
            <<< "$response"
    )"
    if [[ -n "$successful_run" ]]; then
        echo "Verified successful ${workflow_file} run for ${commit_sha}: ${successful_run}"
        if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
            echo "run_url=${successful_run}" >> "$GITHUB_OUTPUT"
        fi
        exit 0
    fi

    active_run="$(
        jq -r \
            --arg event "$event" \
            '[.workflow_runs[]
                | select(.event == $event and .status != "completed")]
                | first
                | .html_url // empty' \
            <<< "$response"
    )"
    failed_run="$(
        jq -r \
            --arg event "$event" \
            '[.workflow_runs[]
                | select(.event == $event and .status == "completed" and .conclusion != "success")]
                | first
                | if . == null then empty else "\(.conclusion): \(.html_url)" end' \
            <<< "$response"
    )"
    if [[ -z "$active_run" && -n "$failed_run" ]]; then
        echo "Required ${workflow_file} run did not pass for ${commit_sha}: ${failed_run}" >&2
        exit 1
    fi

    if ((SECONDS >= deadline)); then
        echo "No successful ${workflow_file} run found for ${commit_sha} on ${branch}." >&2
        exit 1
    fi

    if [[ -n "$active_run" ]]; then
        echo "Waiting for ${workflow_file} to pass for ${commit_sha}: ${active_run}"
    else
        echo "Waiting for ${workflow_file} to start for ${commit_sha}..."
    fi
    sleep 15
done
