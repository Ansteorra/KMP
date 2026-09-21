-- PostgreSQL 1.5.9/1.5.10 repair body. Execute inside one transaction.
-- Supply reviewed JSON via SET LOCAL kmp.authorization_repair_manifest = '...'.
-- Preview is the default; SET LOCAL kmp.authorization_repair_apply = 'on' applies.
-- The manifest is environment-specific and must not be committed to the repository.
DO $repair$
DECLARE
    manifest jsonb := current_setting('kmp.authorization_repair_manifest')::jsonb;
    apply_changes boolean := coalesce(current_setting('kmp.authorization_repair_apply', true), 'off') = 'on';
    repair_key constant text := 'authorization-id-repair-2026-09';
    item jsonb;
    evidence jsonb;
    plan jsonb := '[]'::jsonb;
    wi record;
    auth record;
    gate record;
    response record;
    previous record;
    activity record;
    ctx jsonb;
    definition jsonb;
    approved_at timestamp;
    expires_at timestamp;
    audit_data jsonb;
    n integer;
    patched integer := 0;
    activated integer := 0;
    skipped integer := 0;
BEGIN
    IF current_database() IS DISTINCT FROM manifest->>'database' THEN
        RAISE EXCEPTION 'Repair database does not match the reviewed manifest';
    END IF;
    IF jsonb_array_length(manifest->'workflows') IS DISTINCT FROM 46
       OR (SELECT count(DISTINCT (v->>'workflow_id')::int) FROM jsonb_array_elements(manifest->'workflows') v) <> 46
       OR (SELECT count(DISTINCT (v->>'authorization_id')::int) FROM jsonb_array_elements(manifest->'workflows') v) <> 46
       OR (SELECT count(*) FROM jsonb_array_elements(manifest->'workflows') v WHERE v ? 'approval') <> 2 THEN
        RAISE EXCEPTION 'Expected exactly 46 distinct workflow/authorization pairs and two approvals';
    END IF;
    IF apply_changes THEN
        -- Serialize repair runs, then use the approval -> workflow lock order of normal approvals.
        PERFORM pg_advisory_xact_lock(15510, 46);
        PERFORM g.id FROM workflow_approvals g
        WHERE g.workflow_instance_id IN (SELECT (v->>'workflow_id')::int FROM jsonb_array_elements(manifest->'workflows') v)
        ORDER BY g.id FOR UPDATE;
        PERFORM w.id FROM workflow_instances w
        WHERE w.id IN (SELECT (v->>'workflow_id')::int FROM jsonb_array_elements(manifest->'workflows') v)
        ORDER BY w.id FOR UPDATE;
        PERFORM r.id FROM workflow_approval_responses r JOIN workflow_approvals g ON g.id=r.workflow_approval_id
        WHERE g.workflow_instance_id IN (SELECT (v->>'workflow_id')::int FROM jsonb_array_elements(manifest->'workflows') v)
        ORDER BY r.id FOR UPDATE OF r;
        PERFORM a.id FROM activities_activities a WHERE a.id IN (
            SELECT (v->'approval'->>'activity_id')::int FROM jsonb_array_elements(manifest->'workflows') v WHERE v ? 'approval'
        ) ORDER BY a.id FOR UPDATE;
        PERFORM a.id FROM activities_authorizations a WHERE a.id IN (
            SELECT (v->>'authorization_id')::int FROM jsonb_array_elements(manifest->'workflows') v
            UNION SELECT (v->'approval'->'predecessor'->>'id')::int FROM jsonb_array_elements(manifest->'workflows') v
        ) ORDER BY a.id FOR UPDATE;
    END IF;
    -- Validate the entire manifest before performing any updates.
    FOR item IN SELECT value FROM jsonb_array_elements(manifest->'workflows') LOOP
        SELECT * INTO STRICT wi FROM workflow_instances WHERE id=(item->>'workflow_id')::int;
        SELECT * INTO STRICT auth FROM activities_authorizations WHERE id=(item->>'authorization_id')::int;
        ctx := wi.context::jsonb;
        IF wi.entity_type IS DISTINCT FROM 'Activities.Authorizations' OR wi.entity_id IS DISTINCT FROM auth.id
           OR ctx#>>'{trigger,authorizationId}' IS DISTINCT FROM auth.id::text
           OR ctx->>'migrated' IS DISTINCT FROM 'true'
           OR ctx#>>'{trigger,memberId}' IS DISTINCT FROM auth.member_id::text
           OR ctx#>>'{trigger,activityId}' IS DISTINCT FROM auth.activity_id::text THEN
            RAISE EXCEPTION 'Workflow % does not match its reviewed authorization/trigger', wi.id;
        END IF;
        IF ctx ? repair_key THEN
            IF ctx#>>'{nodes,validate-request,result,authorizationId}' IS DISTINCT FROM auth.id::text
               OR ctx->repair_key->>'authorization_id' IS DISTINCT FROM auth.id::text THEN
                RAISE EXCEPTION 'Previously repaired workflow % has inconsistent repair metadata', wi.id;
            END IF;
            skipped := skipped + 1;
            CONTINUE;
        END IF;
        SELECT v.definition::jsonb INTO STRICT definition FROM workflow_versions v WHERE v.id=wi.workflow_version_id;
        IF definition#>>'{nodes,activate-authorization,config,params,authorizationId}'
                IS DISTINCT FROM '$.nodes.validate-request.result.authorizationId'
           OR ctx#>>'{nodes,validate-request,result,authorizationId}' IS NOT NULL
           OR auth.status::text IS DISTINCT FROM 'Pending' OR auth.start_on IS NOT NULL OR auth.expires_on IS NOT NULL
           OR auth.granted_member_role_id IS NOT NULL OR auth.revoker_id IS NOT NULL THEN
            RAISE EXCEPTION 'Workflow % or authorization % changed since review', wi.id, auth.id;
        END IF;
        SELECT count(*) INTO n FROM workflow_approvals WHERE workflow_instance_id=wi.id AND node_id='approval-gate';
        IF n <> 1 THEN RAISE EXCEPTION 'Workflow % has an ambiguous approval gate', wi.id; END IF;
        SELECT * INTO STRICT gate FROM workflow_approvals WHERE workflow_instance_id=wi.id AND node_id='approval-gate';
        evidence := item->'approval';
        audit_data := jsonb_build_object('repair', repair_key, 'authorization_id', auth.id,
            'workflow_id', wi.id, 'database_user', current_user,
            'authorization_before', to_jsonb(auth), 'context_before', ctx);
        IF evidence IS NULL THEN
            IF wi.status::text IS DISTINCT FROM 'waiting' OR gate.status::text IS DISTINCT FROM 'pending'
               OR NOT (wi.active_nodes::jsonb @> '["approval-gate"]'::jsonb) THEN
                RAISE EXCEPTION 'Expected waiting approval for workflow %', wi.id;
            END IF;
            patched := patched + 1;
        ELSE
            IF wi.status::text IS DISTINCT FROM 'completed' OR gate.status::text IS DISTINCT FROM 'approved'
               OR gate.id IS DISTINCT FROM (evidence->>'approval_id')::int
               OR gate.required_count <> 1 OR gate.approved_count <> 1
               OR auth.member_id IS DISTINCT FROM (evidence->>'member_id')::int
               OR auth.activity_id IS DISTINCT FROM (evidence->>'activity_id')::int THEN
                RAISE EXCEPTION 'Approval state changed for workflow %', wi.id;
            END IF;
            SELECT count(*) INTO n FROM workflow_approval_responses WHERE workflow_approval_id=gate.id;
            IF n <> 1 THEN RAISE EXCEPTION 'Expected one reviewed approval response for workflow %', wi.id; END IF;
            SELECT * INTO STRICT response FROM workflow_approval_responses WHERE workflow_approval_id=gate.id;
            IF response.id IS DISTINCT FROM (evidence->>'response_id')::int
               OR response.member_id IS DISTINCT FROM (evidence->>'approver_id')::int
               OR response.decision::text IS DISTINCT FROM 'approve'
               OR response.responded_at IS DISTINCT FROM (evidence->>'responded_at')::timestamp
               OR ctx#>>'{resumeData,approverId}' IS DISTINCT FROM response.member_id::text
               OR NOT EXISTS (SELECT 1 FROM workflow_execution_logs l WHERE l.workflow_instance_id=wi.id
                   AND l.node_id='activate-authorization'
                   AND coalesce(l.output_data::jsonb->>'activated', l.output_data::jsonb#>>'{data,activated}')='false') THEN
                RAISE EXCEPTION 'Approval evidence changed for workflow %', wi.id;
            END IF;
            SELECT * INTO STRICT activity FROM activities_activities WHERE id=auth.activity_id;
            IF activity.term_length IS DISTINCT FROM (evidence->>'term_months')::int
               OR activity.grants_role_id IS NOT NULL OR activity.term_length <= 0
               OR EXISTS (SELECT 1 FROM member_roles WHERE entity_type='Activities.Authorizations' AND entity_id=auth.id) THEN
                RAISE EXCEPTION 'Unexpected role or term effects for authorization %', auth.id;
            END IF;
            approved_at := date_trunc('second', response.responded_at);
            expires_at := approved_at + make_interval(months => activity.term_length);
            IF expires_at <= (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') THEN
                RAISE EXCEPTION 'Historical authorization % would already be expired; review required', auth.id;
            END IF;
            -- Only the explicitly reviewed predecessor may overlap the reconstructed interval.
            SELECT count(*) INTO n FROM activities_authorizations a
                WHERE a.id<>auth.id AND a.member_id=auth.member_id AND a.activity_id=auth.activity_id
                AND (a.expires_on IS NULL OR a.expires_on >= approved_at)
                AND a.id IS DISTINCT FROM (evidence->'predecessor'->>'id')::int;
            IF n <> 0 THEN RAISE EXCEPTION 'Unreviewed overlapping authorization for %', auth.id; END IF;
            IF evidence ? 'predecessor' THEN
                SELECT * INTO STRICT previous FROM activities_authorizations WHERE id=(evidence->'predecessor'->>'id')::int;
                IF previous.member_id<>auth.member_id OR previous.activity_id<>auth.activity_id
                   OR previous.status::text IS DISTINCT FROM 'Approved' OR previous.granted_member_role_id IS NOT NULL
                   OR previous.revoker_id IS NOT NULL
                   OR previous.start_on IS DISTINCT FROM (evidence->'predecessor'->>'start_on')::timestamp
                   OR previous.expires_on IS DISTINCT FROM (evidence->'predecessor'->>'expires_on')::timestamp
                   OR previous.start_on >= approved_at THEN
                    RAISE EXCEPTION 'Renewal predecessor changed for authorization %', auth.id;
                END IF;
                audit_data := audit_data || jsonb_build_object('predecessor_before', to_jsonb(previous));
            END IF;
            audit_data := audit_data || jsonb_build_object('approval_id', gate.id, 'response_id', response.id,
                'approver_id', response.member_id, 'responded_at', response.responded_at,
                'start_on', approved_at, 'expires_on', expires_at, 'approval_count', gate.approved_count);
            activated := activated + 1;
        END IF;
        plan := plan || jsonb_build_array(jsonb_build_object('item', item, 'audit', audit_data));
        RAISE NOTICE 'Workflow %, authorization %: %', wi.id, auth.id,
            CASE WHEN evidence IS NULL THEN 'repair waiting context only' ELSE
                format('activate; approver=%s; start=%s UTC; expires=%s UTC; predecessor=%s',
                    evidence->>'approver_id', approved_at, expires_at, evidence->'predecessor'->>'id') END;
    END LOOP;
    IF apply_changes THEN
        FOR item IN SELECT value FROM jsonb_array_elements(plan) LOOP
            audit_data := item->'audit';
            evidence := item->'item'->'approval';
            IF evidence IS NOT NULL THEN
                UPDATE activities_authorizations SET status='Approved',
                    start_on=(audit_data->>'start_on')::timestamp, expires_on=(audit_data->>'expires_on')::timestamp,
                    approval_count=(audit_data->>'approval_count')::int
                WHERE id=(audit_data->>'authorization_id')::int;
                IF evidence ? 'predecessor' THEN
                    UPDATE activities_authorizations SET status='Replaced',
                        expires_on=(audit_data->>'start_on')::timestamp - interval '1 second',
                        revoker_id=(audit_data->>'approver_id')::int, revoked_reason=''
                    WHERE id=(evidence->'predecessor'->>'id')::int;
                END IF;
            END IF;
            ctx := audit_data->'context_before';
            ctx := jsonb_set(ctx, '{nodes}', coalesce(ctx->'nodes', '{}'::jsonb) || jsonb_build_object(
                'validate-request', coalesce(ctx#>'{nodes,validate-request}', '{}'::jsonb) || jsonb_build_object(
                    'result', coalesce(ctx#>'{nodes,validate-request,result}', '{}'::jsonb) ||
                        jsonb_build_object('authorizationId', (audit_data->>'authorization_id')::int))));
            ctx := ctx || jsonb_build_object(repair_key, jsonb_build_object(
                'authorization_id', (audit_data->>'authorization_id')::int,
                'repaired_at', CURRENT_TIMESTAMP AT TIME ZONE 'UTC', 'database_user', current_user,
                'approval', evidence));
            UPDATE workflow_instances SET context=ctx::json, modified=CURRENT_TIMESTAMP AT TIME ZONE 'UTC'
                WHERE id=(audit_data->>'workflow_id')::int;
            INSERT INTO workflow_execution_logs
                (workflow_instance_id,node_id,node_type,attempt_number,status,input_data,output_data,started_at,completed_at,created)
            VALUES ((audit_data->>'workflow_id')::int,repair_key,'action',1,'completed',audit_data::json,
                jsonb_build_object('success',true,'context_repaired',true,'activated',evidence IS NOT NULL,
                    'approval',evidence)::json,
                CURRENT_TIMESTAMP AT TIME ZONE 'UTC',CURRENT_TIMESTAMP AT TIME ZONE 'UTC',CURRENT_TIMESTAMP AT TIME ZONE 'UTC');
        END LOOP;
    END IF;
    RAISE NOTICE 'Repair %: waiting=%, approved=%, already_repaired=%; original approvals and logs preserved',
        CASE WHEN apply_changes THEN 'APPLIED' ELSE 'PREVIEW (no writes)' END, patched, activated, skipped;
END
$repair$;
