<?php

namespace Officers\Mailer;

use App\Mailer\TemplateAwareMailerTrait;
use Cake\Mailer\Mailer;
use Cake\Routing\Router;
use App\KMP\StaticHelpers;

class OfficersMailer extends Mailer
{
    use TemplateAwareMailerTrait;

    /**
     * Initialize the OfficersMailer instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function notifyOfHire(
        string $to,
        string $memberScaName,
        string $officeName,
        string $branchName,
        string $hireDate,
        string $endDate,
        bool $requiresWarrant,
    ) {
        $sendFrom = StaticHelpers::getAppSetting("Email.SystemEmailFromAddress");

        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject("Appointment Notification: $officeName")
            ->setViewVars([
                "memberScaName" => $memberScaName,
                "officeName" => $officeName,
                "branchName" => $branchName,
                "hireDate" => $hireDate,
                "endDate" => $endDate,
                "requiresWarrant" => $requiresWarrant,
                "siteAdminSignature" => StaticHelpers::getAppSetting("Email.SiteAdminSignature"),
            ]);
    }
    /**
     * Prepares an email notifying a recipient that a member has been released from an office.
     *
     * Configures recipient, sender, subject, and template variables used by the mail view.
     *
     * @param string $to Recipient email address.
     * @param string $memberScaName Member's SCA name.
     * @param string $officeName Name of the office from which the member is released.
     * @param string $branchName Branch or region associated with the office.
     * @param string $reason Reason for the release.
     * @param string $releaseDate Release date (formatted for display in the email template).
     */
    public function notifyOfRelease(
        string $to,
        string $memberScaName,
        string $officeName,
        string $branchName,
        string $reason,
        string $releaseDate,
    ) {
        $sendFrom = StaticHelpers::getAppSetting("Email.SystemEmailFromAddress");

        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject("Release from Office Notification: $officeName")
            ->setViewVars([
                "memberScaName" => $memberScaName,
                "officeName" => $officeName,
                "branchName" => $branchName,
                "reason" => $reason,
                "releaseDate" => $releaseDate,
                "siteAdminSignature" => StaticHelpers::getAppSetting("Email.SiteAdminSignature"),
            ]);
    }
}