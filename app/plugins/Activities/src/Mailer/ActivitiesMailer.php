<?php

namespace Activities\Mailer;

use Cake\Mailer\Mailer;
use Cake\Routing\Router;
use App\KMP\StaticHelpers;

class ActivitiesMailer extends Mailer
{
    public function __construct()
    {
        parent::__construct();
    }

    public function notifyApprover(
        string $to,
        string $approvalToken,
        string $memberScaName,
        string $approverScaName,
        string $activityName,
    ) {
        $sendFrom = StaticHelpers::getAppSetting("Email.SystemEmailFromAddress");
        $url = Router::url([
            "controller" => "AuthorizationApprovals",
            "action" => "myQueue",
            "plugin" => 'Activities',
            "_full" => true,
            $approvalToken,
        ]);
        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject("Authorization Approval Request")
            ->setViewVars([
                "authorizationResponseUrl" => $url,
                "memberScaName" => $memberScaName,
                "approverScaName" => $approverScaName,
                "activityName" => $activityName,
                "siteAdminSignature" => StaticHelpers::getAppSetting("Email.SiteAdminSignature"),
            ]);
    }

    public function notifyRequester(
        string $to,
        string $status,
        string $memberScaName,
        int $memberId,
        string $ApproverScaName,
        string $nextApproverScaName,
        string $activityName,
    ) {
        $sendFrom = StaticHelpers::getAppSetting("Email.SystemEmailFromAddress");
        $url = Router::url([
            "controller" => "Members",
            "action" => "viewCard",
            "plugin" => null,
            "_full" => true,
            $memberId,
        ]);

        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject("Update on Authorization Request")
            ->setViewVars([
                "memberScaName" => $memberScaName,
                "approverScaName" => $ApproverScaName,
                "status" => $status,
                "activityName" => $activityName,
                "memberCardUrl" => $url,
                "nextApproverScaName" => $nextApproverScaName,
                "siteAdminSignature" => StaticHelpers::getAppSetting("Email.SiteAdminSignature"),
            ]);
    }
}