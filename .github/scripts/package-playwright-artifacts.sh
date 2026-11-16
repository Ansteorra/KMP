#!/usr/bin/env bash
set -euo pipefail

output="${1:?Supply the output archive path.}"
# Archive report paths intact: BDD tags can contain colons rejected by upload-artifact.
paths=()
for path in ui-reports ui-results; do
    [[ ! -d "app/tests/$path" ]] || paths+=("$path")
done
if [[ "${#paths[@]}" == 0 ]]; then
    tar -czf "$output" --files-from /dev/null
else
    tar -czf "$output" -C app/tests "${paths[@]}"
fi
