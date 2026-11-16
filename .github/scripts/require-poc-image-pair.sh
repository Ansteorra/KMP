#!/usr/bin/env bash
set -euo pipefail

sha="${1:-}"
web="${2:-}"
live="${3:-}"
[[ "$sha" =~ ^[0-9a-f]{40}$ && "$web" =~ @sha256:[0-9a-f]{64}$ && "$live" =~ @sha256:[0-9a-f]{64}$ ]] || {
    echo 'POC pair verification requires an exact commit and both immutable images.' >&2; exit 64;
}
repo="${GITHUB_REPOSITORY:-Ansteorra/KMP}"
artifact_name="poc-image-pair-$sha"
temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT
runs="$(gh api "repos/$repo/actions/workflows/nightly-deploy-azure.yml/runs?status=success&per_page=100" --jq '.workflow_runs[].id')"
for run in $runs; do
    artifact="$(gh api "repos/$repo/actions/runs/$run/artifacts" \
        --jq ".artifacts[] | select(.name == \"$artifact_name\" and .expired == false) | .id")"
    [[ -n "$artifact" ]] || continue
    gh run download "$run" --repo "$repo" --name "$artifact_name" --dir "$temporary"
    jq -e --arg commit "$sha" --arg web "$web" --arg live "$live" \
        '.commit == $commit and .web == $web and .live == $live' "$temporary/poc-image-pair.json" >/dev/null || {
        echo 'POC evidence does not match the selected web/gateway pair.' >&2; exit 1;
    }
    echo "Verified paired POC evidence from successful workflow run $run."
    exit 0
done
echo 'No successful POC workflow has current paired-image evidence for this commit.' >&2
exit 1
