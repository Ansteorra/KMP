<?php
declare(strict_types=1);

namespace App\Test\TestCase\Config;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CaseInsensitiveTextMigrationsTest extends TestCase
{
    private const MIGRATIONS = [
        'config/Migrations/20260721120500_ExpandCaseInsensitiveHumanText.php'
            => 'ExpandCaseInsensitiveHumanText',
        'plugins/Activities/config/Migrations/20260721120600_ExpandCaseInsensitiveActivityText.php'
            => 'ExpandCaseInsensitiveActivityText',
        'plugins/Officers/config/Migrations/20260721120700_ExpandCaseInsensitiveOfficerText.php'
            => 'ExpandCaseInsensitiveOfficerText',
        'plugins/Awards/config/Migrations/20260721120800_ExpandCaseInsensitiveAwardText.php'
            => 'ExpandCaseInsensitiveAwardText',
        'plugins/Waivers/config/Migrations/20260721120900_ExpandCaseInsensitiveWaiverText.php'
            => 'ExpandCaseInsensitiveWaiverText',
        'plugins/Queue/config/Migrations/20260721121000_EnableCaseInsensitiveQueueStatusText.php'
            => 'EnableCaseInsensitiveQueueStatusText',
        'config/PlatformMigrations/20260721121100_EnableCaseInsensitivePlatformText.php'
            => 'EnableCaseInsensitivePlatformText',
    ];

    public function testHumanTextAndLifecycleFieldsAreIncluded(): void
    {
        $columns = $this->migrationColumns();
        $requiredColumns = [
            'members.street_address',
            'members.city',
            'members.state',
            'members.phone_number',
            'members.status',
            'branches.location',
            'permissions.scoping_rule',
            'gatherings.description',
            'gathering_staff.email',
            'action_items.status',
            'workflow_approvals.status',
            'workflow_approval_triage_states.state',
            'activities_authorizations.status',
            'officers_officers.email_address',
            'officers_officers.status',
            'awards_recommendations.status',
            'awards_recommendations.state',
            'awards_recommendations.group_origin_status',
            'awards_recommendations.group_origin_state',
            'awards_recommendations_states_logs.from_status',
            'awards_recommendations_states_logs.to_state',
            'awards_bestowals.lifecycle_status',
            'awards_recommendation_feedback_requests.status',
            'waivers_gathering_waivers.status',
            'waivers_gathering_waivers.exemption_reason',
            'queued_jobs.status',
            'platform_jobs.status',
            'platform_schedules.name',
            'platform_users.email',
            'tenant_hosts.host',
            'tenants.display_name',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertArrayHasKey($column, $columns);
        }
        $this->assertCount(212, $columns);
    }

    public function testSensitiveAndInherentlyCaseSensitiveFieldsAreExcluded(): void
    {
        $columns = $this->migrationColumns();
        $excludedColumns = [
            'members.password',
            'members.password_token',
            'members.mobile_card_token',
            'members.public_id',
            'members.additional_info',
            'members.membership_number',
            'app_settings.name',
            'app_settings.value',
            'documents.file_path',
            'documents.checksum',
            'email_templates.slug',
            'email_templates.html_template',
            'gatherings.website_url',
            'gatherings.timezone',
            'impersonation_action_logs.request_method',
            'impersonation_session_logs.user_agent',
            'member_quick_login_devices.device_id',
            'member_quick_login_devices.pin_hash',
            'member_quick_login_devices.configured_user_agent',
            'service_principals.client_id',
            'service_principals.client_secret_hash',
            'service_principal_audit_logs.http_method',
            'service_principal_tokens.token_hash',
            'tokens.token_key',
            'tokens.content',
            'tokens.type',
            'workflow_approvals.approval_token',
            'workflow_definitions.slug',
            'workflow_schedules.claim_token',
            'activities_authorization_approvals.authorization_token',
            'awards_approval_process_steps.step_key',
            'awards_approval_process_steps.approver_source_key',
            'awards_awards.specialties',
            'awards_bestowal_todo_template_items.item_key',
            'awards_bestowal_todo_template_items.assignee_source_key',
            'officers_offices.applicable_branch_types',
            'waivers_waiver_types.exemption_reasons',
            'waivers_waiver_types.retention_policy',
            'waivers_waiver_types.template_path',
            'queued_jobs.data',
            'queued_jobs.job_task',
            'platform_auth_sessions.selector_hash',
            'platform_users.password_hash',
            'platform_settings.key',
            'platform_settings.value',
            'tenant_backups.object_uri',
            'tenant_secrets_index.name',
            'tenants.db_name',
            'tenants.slug',
        ];

        foreach ($excludedColumns as $column) {
            $this->assertArrayNotHasKey($column, $columns);
        }
    }

    public function testEveryColumnHasAReversibleOriginalType(): void
    {
        foreach ($this->migrationColumns() as $column => $type) {
            $this->assertMatchesRegularExpression(
                '/^(?:text|varchar\([1-9][0-9]*\))$/',
                $type,
                sprintf('%s must declare its original PostgreSQL type.', $column),
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function migrationColumns(): array
    {
        $result = [];
        $appDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR;
        foreach (self::MIGRATIONS as $path => $className) {
            require_once $appDirectory . $path;
            $constant = (new ReflectionClass($className))->getReflectionConstant('COLUMNS');
            $this->assertNotFalse($constant);

            foreach ($constant->getValue() as $table => $columns) {
                foreach ($columns as $column => $type) {
                    $key = $table . '.' . $column;
                    $this->assertArrayNotHasKey($key, $result);
                    $result[$key] = $type;
                }
            }
        }

        return $result;
    }
}
