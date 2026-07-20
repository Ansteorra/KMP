BEGIN;

CREATE TEMP TABLE keep_second_tenant_members (
    id BIGINT PRIMARY KEY,
    email_address TEXT NOT NULL,
    sca_name TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    branch_id BIGINT NOT NULL
);

INSERT INTO keep_second_tenant_members (
    id,
    email_address,
    sca_name,
    first_name,
    last_name,
    branch_id
) VALUES
    (1, 'admin@amp2demo.com', 'Admin of the Second Kingdom', 'Admin', 'Second', 24),
    (2872, 'bryce@amp2demo.com', 'Bryce Second Local Seneschal', 'Bryce', 'Second', 22),
    (2875, 'eirik@amp2demo.com', 'Eirik Second Kingdom Seneschal', 'Eirik', 'Second', 36),
    (2878, 'iris@amp2demo.com', 'Iris Second Basic User', 'Iris', 'Second', 30);

TRUNCATE TABLE
    activities_authorization_approvals,
    activities_authorizations,
    awards_recommendations_events,
    awards_recommendations_states_logs,
    awards_recommendations,
    backups,
    gathering_attendances,
    gathering_staff,
    gatherings_gathering_activities,
    gathering_scheduled_activities,
    gatherings,
    grid_view_preferences,
    grid_views,
    impersonation_action_logs,
    impersonation_session_logs,
    member_quick_login_devices,
    officers_officers,
    queued_jobs,
    service_principal_audit_logs,
    service_principal_roles,
    service_principal_tokens,
    service_principals,
    tokens,
    waivers_gathering_waiver_closures,
    waivers_gathering_waivers,
    warrants,
    workflow_approvals,
    workflow_approval_responses,
    workflow_execution_logs,
    workflow_instance_migrations,
    workflow_instances,
    workflow_tasks
RESTART IDENTITY CASCADE;

DELETE FROM member_roles
WHERE member_id NOT IN (SELECT id FROM keep_second_tenant_members)
   OR approver_id NOT IN (SELECT id FROM keep_second_tenant_members);

UPDATE branches
SET contact_id = NULL
WHERE contact_id NOT IN (SELECT id FROM keep_second_tenant_members);

UPDATE documents
SET
    uploaded_by = CASE WHEN uploaded_by IN (SELECT id FROM keep_second_tenant_members) THEN uploaded_by ELSE NULL END,
    created_by = CASE WHEN created_by IN (SELECT id FROM keep_second_tenant_members) THEN created_by ELSE NULL END,
    modified_by = CASE WHEN modified_by IN (SELECT id FROM keep_second_tenant_members) THEN modified_by ELSE NULL END;

UPDATE email_templates
SET
    created_by = CASE WHEN created_by IN (SELECT id FROM keep_second_tenant_members) THEN created_by ELSE NULL END,
    modified_by = CASE WHEN modified_by IN (SELECT id FROM keep_second_tenant_members) THEN modified_by ELSE NULL END;

UPDATE workflow_definitions
SET
    created_by = CASE WHEN created_by IN (SELECT id FROM keep_second_tenant_members) THEN created_by ELSE NULL END,
    modified_by = CASE WHEN modified_by IN (SELECT id FROM keep_second_tenant_members) THEN modified_by ELSE NULL END;

UPDATE workflow_versions
SET
    created_by = CASE WHEN created_by IN (SELECT id FROM keep_second_tenant_members) THEN created_by ELSE NULL END,
    published_by = CASE WHEN published_by IN (SELECT id FROM keep_second_tenant_members) THEN published_by ELSE NULL END;

DELETE FROM members
WHERE id NOT IN (SELECT id FROM keep_second_tenant_members);

UPDATE members AS m
SET
    email_address = k.email_address,
    sca_name = k.sca_name,
    first_name = k.first_name,
    last_name = k.last_name,
    branch_id = k.branch_id,
    modified = CURRENT_TIMESTAMP
FROM keep_second_tenant_members AS k
WHERE m.id = k.id;

UPDATE app_settings
SET value = 'Second Kingdom'
WHERE name = 'KMP.KingdomName';

UPDATE branches
SET
    name = 'Second Kingdom',
    location = 'Second Kingdom demonstration realm',
    modified = CURRENT_TIMESTAMP
WHERE id = 2;

UPDATE branches
SET
    name = 'Second Kingdom Lands',
    location = 'All Second Kingdom lands not supported by a local group.',
    modified = CURRENT_TIMESTAMP
WHERE id = 24;

COMMIT;
