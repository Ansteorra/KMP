#!/usr/bin/env bash
set -euo pipefail

REPOSITORY="${GITHUB_REPOSITORY:-Ansteorra/KMP}"
SUBSCRIPTION_ID="${AZURE_SUBSCRIPTION_ID:?AZURE_SUBSCRIPTION_ID must be set}"
TENANT_ID="${AZURE_TENANT_ID:?AZURE_TENANT_ID must be set}"
PRODUCTION_REVIEWER="${PRODUCTION_REVIEWER:?PRODUCTION_REVIEWER must be set}"

need() {
    command -v "$1" >/dev/null 2>&1 || {
        echo "Required command not found: $1" >&2
        exit 69
    }
}

for command in az curl gh jq; do
    need "$command"
done

az account set --subscription "$SUBSCRIPTION_ID"
GITHUB_TOKEN_VALUE="$(gh auth token)"

github_api() {
    local method="$1"
    local path="$2"
    local body="${3:-}"
    local response_file status attempt
    response_file="$(mktemp)"
    for attempt in $(seq 1 8); do
        local args=(
            --silent
            --show-error
            --output "$response_file"
            --write-out '%{http_code}'
            --request "$method"
            --header "Authorization: Bearer $GITHUB_TOKEN_VALUE"
            --header 'Accept: application/vnd.github+json'
            --header 'X-GitHub-Api-Version: 2022-11-28'
        )
        if [[ -n "$body" ]]; then
            args+=(--header 'Content-Type: application/json' --data "$body")
        fi
        status="$(curl "${args[@]}" "https://api.github.com/$path")"
        if [[ "$status" =~ ^2[0-9][0-9]$ ]]; then
            cat "$response_file"
            rm -f "$response_file"
            return
        fi
        if [[ "$status" =~ ^(429|502|503|504)$ ]]; then
            sleep "$attempt"
            continue
        fi
        cat "$response_file" >&2
        rm -f "$response_file"
        echo "GitHub API request failed with HTTP $status: $method $path" >&2
        return 1
    done
    cat "$response_file" >&2
    rm -f "$response_file"
    echo "GitHub API request did not recover: $method $path" >&2
    return 1
}

ensure_identity() {
    local environment="$1"
    local resource_group="$2"
    local display_name="kmp-${environment}-github-oidc"
    local app_id sp_object_id resource_group_id credential_name subject credential_parameters

    app_id="$(az ad app list \
        --display-name "$display_name" \
        --query '[0].appId' \
        --output tsv 2>/dev/null || true)"
    if [[ -z "$app_id" ]]; then
        app_id="$(az ad app create \
            --display-name "$display_name" \
            --query appId \
            --output tsv)"
    fi

    sp_object_id="$(az ad sp list \
        --filter "appId eq '$app_id'" \
        --query '[0].id' \
        --output tsv 2>/dev/null || true)"
    if [[ -z "$sp_object_id" ]]; then
        sp_object_id="$(az ad sp create --id "$app_id" --query id --output tsv)"
    fi

    resource_group_id="$(az group show \
        --name "$resource_group" \
        --query id \
        --output tsv)"
    if [[ "$(az role assignment list \
        --assignee "$sp_object_id" \
        --scope "$resource_group_id" \
        --role Contributor \
        --query 'length(@)' \
        --output tsv)" == "0" ]]; then
        az role assignment create \
            --assignee-object-id "$sp_object_id" \
            --assignee-principal-type ServicePrincipal \
            --role Contributor \
            --scope "$resource_group_id" \
            --output none
    fi

    credential_name="github-${environment}"
    subject="repo:${REPOSITORY}:environment:${environment}"
    if [[ "$(az ad app federated-credential list \
        --id "$app_id" \
        --query "[?name=='$credential_name'] | length(@)" \
        --output tsv)" == "0" ]]; then
        credential_parameters="$(jq -c -n \
            --arg name "$credential_name" \
            --arg subject "$subject" \
            '{
                name: $name,
                issuer: "https://token.actions.githubusercontent.com",
                subject: $subject,
                audiences: ["api://AzureADTokenExchange"]
            }')"
        az ad app federated-credential create \
            --id "$app_id" \
            --parameters "$credential_parameters" \
            --output none
    fi

    echo "$app_id"
}

ensure_environment() {
    local environment="$1"
    local policy_name="$2"
    local policy_type="$3"
    local reviewer="${4:-}"
    local reviewer_id='' environment_parameters policies policy_parameters

    if [[ -n "$reviewer" ]]; then
        reviewer_id="$(github_api GET "users/$reviewer" | jq -r .id)"
        environment_parameters="$(jq -c -n \
            --argjson reviewer_id "$reviewer_id" \
            '{
                wait_timer: 0,
                prevent_self_review: false,
                reviewers: [{type: "User", id: $reviewer_id}],
                deployment_branch_policy: {
                    protected_branches: false,
                    custom_branch_policies: true
                }
            }')"
    else
        environment_parameters="$(jq -c -n \
            '{
                wait_timer: 0,
                prevent_self_review: false,
                reviewers: [],
                deployment_branch_policy: {
                    protected_branches: false,
                    custom_branch_policies: true
                }
            }')"
    fi

    github_api PUT \
        "repos/${REPOSITORY}/environments/${environment}" \
        "$environment_parameters" >/dev/null

    policies="$(github_api GET \
        "repos/${REPOSITORY}/environments/${environment}/deployment-branch-policies")"
    if [[ "$(jq \
        --arg name "$policy_name" \
        --arg type "$policy_type" \
        '[.branch_policies[] | select(.name == $name and .type == $type)] | length' \
        <<< "$policies")" == "0" ]]; then
        policy_parameters="$(jq -c -n \
            --arg name "$policy_name" \
            --arg type "$policy_type" \
            '{name: $name, type: $type}')"
        github_api POST \
            "repos/${REPOSITORY}/environments/${environment}/deployment-branch-policies" \
            "$policy_parameters" >/dev/null
    fi
}

set_variable() {
    local environment="$1"
    local name="$2"
    local value="$3"
    local parameters variables
    parameters="$(jq -c -n \
        --arg name "$name" \
        --arg value "$value" \
        '{name: $name, value: $value}')"
    variables="$(github_api GET \
        "repos/${REPOSITORY}/environments/${environment}/variables?per_page=100")"
    if [[ "$(jq --arg name "$name" '[.variables[] | select(.name == $name)] | length' \
        <<< "$variables")" == "0" ]]; then
        github_api POST \
            "repos/${REPOSITORY}/environments/${environment}/variables" \
            "$parameters" >/dev/null
    else
        github_api PATCH \
            "repos/${REPOSITORY}/environments/${environment}/variables/${name}" \
            "$parameters" >/dev/null
    fi
}

discover_postgres_server() {
    local resource_group="$1"
    local servers server_count

    servers="$(az postgres flexible-server list \
        --resource-group "$resource_group" \
        --query '[].name' \
        --output tsv)"
    server_count="$(awk 'NF { count++ } END { print count + 0 }' <<< "$servers")"
    if [[ "$server_count" -ne 1 ]]; then
        echo "Expected exactly one PostgreSQL Flexible Server in $resource_group; found $server_count." >&2
        return 1
    fi

    printf '%s\n' "$servers"
}

configure_environment() {
    local environment="$1"
    local app_id="$2"
    local resource_group="$3"
    local acr_name="$4"
    local web_app="$5"
    local migrate_job="$6"
    local queue_job="$7"
    local restore_job="$8"
    local provision_job="$9"
    local hourly_job="${10}"
    local daily_job="${11}"
    local postgres_server

    postgres_server="$(discover_postgres_server "$resource_group")"

    set_variable "$environment" AZURE_CLIENT_ID "$app_id"
    set_variable "$environment" AZURE_TENANT_ID "$TENANT_ID"
    set_variable "$environment" AZURE_SUBSCRIPTION_ID "$SUBSCRIPTION_ID"
    set_variable "$environment" AZURE_RESOURCE_GROUP "$resource_group"
    set_variable "$environment" AZURE_ACR_NAME "$acr_name"
    set_variable "$environment" AZURE_POSTGRES_SERVER_NAME "$postgres_server"
    set_variable "$environment" AZURE_WEB_APP_NAME "$web_app"
    set_variable "$environment" AZURE_MIGRATE_JOB_NAME "$migrate_job"
    set_variable "$environment" AZURE_QUEUE_JOB_NAME "$queue_job"
    set_variable "$environment" AZURE_RESTORE_JOB_NAME "$restore_job"

    if [[ -n "$provision_job" ]]; then
        set_variable "$environment" AZURE_PROVISION_JOB_NAME "$provision_job"
    fi
    if [[ -n "$hourly_job" ]]; then
        set_variable "$environment" AZURE_SCHED_HOURLY_JOB_NAME "$hourly_job"
    fi
    if [[ -n "$daily_job" ]]; then
        set_variable "$environment" AZURE_SCHED_DAILY_JOB_NAME "$daily_job"
    fi
}

poc_app_id="$(ensure_identity poc kmp-nightly-rg)"
production_app_id="$(ensure_identity production kmp-production-rg)"

ensure_environment poc main branch
ensure_environment production 'v*' tag "$PRODUCTION_REVIEWER"

configure_environment \
    poc \
    "$poc_app_id" \
    kmp-nightly-rg \
    kmpnightlyacrd346d2 \
    kmpnightly-web \
    kmpnightly-migrate \
    kmpnightly-queue \
    kmpnightly-reset \
    '' \
    '' \
    kmpnightly-sync

configure_environment \
    production \
    "$production_app_id" \
    kmp-production-rg \
    kmpprodacrc5e6bc \
    kmpprod-web \
    kmpprod-migrate \
    kmpprod-queue \
    kmpprod-restore \
    kmpprod-provision \
    kmpprod-sched-hourly \
    ''

echo "Configured GitHub OIDC deployment environments for $REPOSITORY."
