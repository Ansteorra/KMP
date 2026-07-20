#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_DIR"

if [ "$#" -gt 0 ]; then
    lane="$1"
    shift
else
    lane="uat"
fi

case "$lane" in
    smoke)
        specs=(
            "tests/ui/gen/@auth/UserLogin.feature.spec.js"
            "tests/ui/gen/@workflows/workflow-admin.feature.spec.js"
        )
        ;;
    journey)
        specs=(
            "tests/ui/gen/@tenancy/Tenancy.feature.spec.js"
            "tests/ui/gen/@members/MemberRegistration.feature.spec.js"
            "tests/ui/gen/@activities/@mode:serial/RequestAndReceiveAuth.feature.spec.js"
            "tests/ui/gen/@officers/OfficerReleaseWorkflow.feature.spec.js"
            "tests/ui/gen/@warrants/WarrantRosterDecline.feature.spec.js"
            "tests/ui/gen/@gatherings/Gatherings.feature.spec.js"
            "tests/ui/gen/@awards/AwardRecommendations.feature.spec.js"
            "tests/ui/gen/@awards/AwardBestowals.feature.spec.js"
            "tests/ui/gen/@awards/AwardHotwireGrid.feature.spec.js"
        )
        ;;
    platform)
        export PLAYWRIGHT_INCLUDE_DESTRUCTIVE=1
        specs=(
            "tests/ui/gen/@platform-admin/PlatformTenantProvisioning.feature.spec.js"
        )
        ;;
    uat|full)
        specs=()
        ;;
    *)
        echo "Usage: bash bin/run-playwright-lane.sh [smoke|journey|platform|uat|full] [playwright args...]" >&2
        exit 1
        ;;
esac

npx bddgen test

if [[ "${PLAYWRIGHT_RESET_DB:-1}" != "0" ]]; then
    bash ../reset_dev_database.sh
fi

# After the one-time lane reset above, prevent per-scenario features from running
# their own redundant (~10 min) full DB reset, which would blow the per-test
# timeout. Features build their own fixtures additively via runPhpJson with
# unique timestamped tokens, so the lane's single reset is authoritative.
export PLAYWRIGHT_RESET_DB=0

if [ "$lane" = "smoke" ] || [ "$lane" = "journey" ] || [ "$lane" = "platform" ]; then
    npx playwright test "${specs[@]}" "$@"
else
    npx playwright test "$@"
fi
