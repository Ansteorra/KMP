<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\Backup\BackupPayloadUpgradeService;
use Cake\TestSuite\TestCase;

/**
 * @covers \App\Services\Backup\BackupPayloadUpgradeService
 * @covers \App\Services\Backup\MainToWorkflowEngineBranchBackupMigrator
 */
class BackupPayloadUpgradeServiceTest extends TestCase
{
    public function testUpgradePromotesBaselineRecommendationPayloadToBestowalObjectModel(): void
    {
        $payload = [
            'meta' => [
                'version' => 1,
                'created_at' => '2026-06-22T14:19:30+00:00',
            ],
            'tables' => [
                'awards_recommendations' => [
                    [
                        'id' => 10,
                        'member_id' => 100,
                        'award_id' => 200,
                        'state' => 'Scheduled',
                        'gathering_id' => 300,
                        'recommendation_group_id' => null,
                        'bestowal_id' => null,
                        'specialty' => 'Armored combat',
                        'reason' => 'Excellent service.',
                        'requester_sca_name' => 'Test Submitter',
                        'call_into_court' => 'yes',
                        'court_availability' => 'morning',
                        'person_to_notify' => 'Herald',
                        'created' => '2026-06-01 10:00:00',
                        'modified' => '2026-06-02 10:00:00',
                        'created_by' => 1,
                        'modified_by' => 2,
                    ],
                    [
                        'id' => 11,
                        'member_id' => 101,
                        'award_id' => 201,
                        'state' => 'Submitted',
                        'recommendation_group_id' => null,
                        'bestowal_id' => null,
                    ],
                    [
                        'id' => 12,
                        'member_id' => 102,
                        'award_id' => 202,
                        'state' => 'Scheduled',
                        'recommendation_group_id' => 10,
                        'bestowal_id' => null,
                        'reason' => 'Additional evidence.',
                        'requester_sca_name' => 'Second Submitter',
                    ],
                ],
            ],
        ];

        $result = (new BackupPayloadUpgradeService())->upgrade($payload);
        $upgraded = $result['payload'];

        $this->assertSame('baseline-v1', $result['stats']['source_version']);
        $this->assertSame(1, $result['stats']['migrators_applied']);
        $this->assertCount(1, $upgraded['tables']['awards_bestowals']);
        $this->assertCount(2, $upgraded['tables']['awards_bestowal_recommendations']);

        $bestowal = $upgraded['tables']['awards_bestowals'][0];
        $this->assertSame(1, $bestowal['id']);
        $this->assertSame(10, $bestowal['primary_recommendation_id']);
        $this->assertSame(200, $bestowal['award_id']);
        $this->assertSame('Court Scheduled', $bestowal['state']);
        $this->assertSame('Scheduling', $bestowal['status']);
        $this->assertFalse($bestowal['roaming_court']);
        $this->assertSame('Armored combat', $bestowal['specialty']);
        $this->assertStringContainsString('Submitted by Test Submitter', $bestowal['reason_summary']);
        $this->assertStringContainsString('Excellent service.', $bestowal['reason_summary']);
        $this->assertStringContainsString('Submitted by Second Submitter', $bestowal['reason_summary']);
        $this->assertStringContainsString('Additional evidence.', $bestowal['reason_summary']);

        $this->assertSame(1, $upgraded['tables']['awards_recommendations'][0]['bestowal_id']);
        $this->assertSame(1, $upgraded['tables']['awards_recommendations'][2]['bestowal_id']);
        $this->assertArrayHasKey('payload_upgrade_target', $upgraded['meta']);
    }

    public function testUpgradeIsIdempotentForAlreadyLinkedBestowals(): void
    {
        $payload = [
            'meta' => ['version' => 1],
            'tables' => [
                'awards_bestowals' => [
                    [
                        'id' => 5,
                        'primary_recommendation_id' => 10,
                        'reason_summary' => 'Already summarized.',
                    ],
                ],
                'awards_bestowal_recommendations' => [
                    [
                        'id' => 7,
                        'bestowal_id' => 5,
                        'recommendation_id' => 10,
                        'created' => '2026-06-01 10:00:00',
                    ],
                ],
                'awards_recommendations' => [
                    [
                        'id' => 10,
                        'member_id' => 100,
                        'award_id' => 200,
                        'state' => 'Scheduled',
                        'recommendation_group_id' => null,
                        'bestowal_id' => 5,
                    ],
                ],
            ],
        ];

        $result = (new BackupPayloadUpgradeService())->upgrade($payload);
        $upgraded = $result['payload'];

        $this->assertSame(0, $result['stats']['migrators']['main-to-workflow-engine-20260622']['bestowals_created']);
        $this->assertCount(1, $upgraded['tables']['awards_bestowals']);
        $this->assertCount(1, $upgraded['tables']['awards_bestowal_recommendations']);
        $this->assertSame(5, $upgraded['tables']['awards_recommendations'][0]['bestowal_id']);
    }

    public function testUpgradeBackfillsLegacyEmailTemplateSlugs(): void
    {
        $payload = [
            'meta' => ['version' => 1],
            'tables' => [
                'email_templates' => [
                    [
                        'id' => 1,
                        'mailer_class' => 'App\Mailer\KMPMailer',
                        'action_method' => 'resetPassword',
                        'subject_template' => 'Reset password',
                        'available_vars' => json_encode([
                            ['name' => 'email', 'description' => 'Email'],
                            ['name' => 'passwordResetUrl', 'description' => 'Password reset url'],
                        ]),
                    ],
                    [
                        'id' => 2,
                        'mailer_class' => 'App\Mailer\KMPMailer',
                        'action_method' => 'resetPassword',
                        'subject_template' => 'Duplicate reset password',
                        'available_vars' => json_encode([
                            ['name' => 'email', 'description' => 'Email'],
                        ]),
                    ],
                    [
                        'id' => 3,
                        'mailer_class' => 'Custom\Mailer\LegacyMailer',
                        'action_method' => 'sendNotice',
                        'subject_template' => 'Custom notice',
                    ],
                    [
                        'id' => 4,
                        'slug' => 'already-current',
                        'subject_template' => 'Already current',
                    ],
                ],
            ],
        ];

        $result = (new BackupPayloadUpgradeService())->upgrade($payload);
        $upgraded = $result['payload'];
        $stats = $result['stats']['migrators']['main-to-workflow-engine-20260622'];

        $this->assertSame(3, $stats['email_template_slugs_backfilled']);
        $this->assertSame(1, $stats['email_template_slugs_already_present']);
        $this->assertSame(1, $stats['email_template_slugs_generated']);
        $this->assertSame('password-reset', $upgraded['tables']['email_templates'][0]['slug']);
        $this->assertSame('Password Reset', $upgraded['tables']['email_templates'][0]['name']);
        $this->assertSame('password-reset-2', $upgraded['tables']['email_templates'][1]['slug']);
        $this->assertSame('legacy-legacy-send-notice', $upgraded['tables']['email_templates'][2]['slug']);
        $this->assertSame('already-current', $upgraded['tables']['email_templates'][3]['slug']);
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                'email' => ['type' => 'string', 'label' => 'Email'],
                'passwordResetUrl' => ['type' => 'string', 'label' => 'Password reset url'],
            ]),
            $upgraded['tables']['email_templates'][0]['variables_schema'],
        );
    }
}
