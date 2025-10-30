<?php

namespace Officers\Mailer;

use App\Mailer\TemplateAwareMailerTrait;
use Cake\Mailer\Mailer;
use Cake\Routing\Router;
use App\KMP\StaticHelpers;

class OfficersMailer extends Mailer
{
    use TemplateAwareMailerTrait;

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
