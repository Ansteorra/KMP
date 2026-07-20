<?php
declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Migrations\BaseMigration;

class FixMemberRegistrationEmailTemplates extends BaseMigration
{
    use CrossEngineMigrationTrait;

    public function up(): void
    {
        $updates = [
            'member-registration-welcome' => [
                'name' => 'Member Registration Welcome',
                'description' => 'Welcome email sent to a newly registered adult member with a password-reset link.',
                'subject_template' => 'Welcome {{memberScaName}} to {{portalName}}',
                'text_template' =>
                    "Welcome, {{memberScaName}}!\n\n"
                    . "To verify your email address please use the link below to set your password.\n\n"
                    . "{{passwordResetUrl}}\n\n"
                    . "This link will be good for 1 day. If you do not set your password within that time frame "
                    . "you will need to request a new password reset email from the \"forgot password\" link on "
                    . "the login page.\n\n\n"
                    . "Thank you\n{{siteAdminSignature}}.",
            ],
            'member-registration-secretary' => [
                'name' => 'New Member Secretary Notification',
                'description' => 'Sent to the kingdom secretary when a new adult member registers.',
                'subject_template' => 'New Member Registration: {{memberScaName}}',
                'text_template' =>
                    "Good day,\n\n"
                    . "{{memberScaName}} has recently registered. They have been emailed to set their password "
                    . "and their membership card was {{memberCardPresent}}.\n\n"
                    . "You can view their information at the link below:\n"
                    . "{{memberViewUrl}}\n\n\n"
                    . "Thank you\n{{siteAdminSignature}}.",
            ],
            'member-registration-secretary-minor' => [
                'name' => 'New Minor Member Secretary Notification',
                'description' => 'Sent to the kingdom secretary when a new minor member registers.',
                'subject_template' => 'New Minor Member Registration: {{memberScaName}}',
                'text_template' =>
                    "Good day,\n\n"
                    . "A new minor named {{memberScaName}} has recently registered. Their account is currently "
                    . "inaccessible and they have been notified you will follow up. Their membership card "
                    . "was {{memberCardPresent}} at the time of registration.\n\n"
                    . "You can view their information at the link below:\n"
                    . "{{memberViewUrl}}\n\n\n"
                    . "Thank you\n{{siteAdminSignature}}.",
            ],
        ];

        $this->applyUpdates($updates);
    }

    public function down(): void
    {
        $updates = [
            'member-registration-secretary' => [
                'subject_template' => 'New Member Registration',
                'text_template' =>
                    "Good day,\n\n{{memberScaName}} has recently registered. They have been emailed to set their password and their membership card\n"
                    . "was <?= \$memberCardPresent ? \"uploaded\" : \"not uploaded\" ?>.\n\nYou can view their information at the link below:\n"
                    . "{{memberViewUrl}}\n\n\nThank you\n{{siteAdminSignature}}.",
            ],
            'member-registration-secretary-minor' => [
                'subject_template' => 'New Minor Member Registration',
                'text_template' =>
                    "Good day,\n\nA new minor named {{memberScaName}} has recently registered. Their account is currently inaccessable and they have\n"
                    . "been notified you will follow up. Their membership card\nwas <?= \$memberCardPresent ? \"uploaded\" : \"not uploaded\" ?> at the time of registration.\n\n"
                    . "You can view their information at the link below:\n{{memberViewUrl}}\n\n\nThank you\n{{siteAdminSignature}}.",
            ],
        ];

        $this->applyUpdates($updates);
    }

    /**
     * Update templates by slug without using the ORM during migrations.
     *
     * @param array<string, array<string, string>> $updates Template field updates keyed by slug.
     * @return void
     */
    private function applyUpdates(array $updates): void
    {
        $now = date('Y-m-d H:i:s');

        foreach ($updates as $slug => $fields) {
            $assignments = [];
            foreach ($fields as $field => $value) {
                $assignments[] = "{$field} = '" . $this->sqlEscape($value) . "'";
            }
            $assignments[] = "modified = '{$now}'";
            $assignments[] = 'modified_by = 1';

            $this->execute(
                'UPDATE email_templates SET '
                . implode(', ', $assignments)
                . " WHERE slug = '" . $this->sqlEscape($slug) . "'",
            );
        }
    }
}
