<?php

namespace App\Mailer;

use Cake\Mailer\Mailer;
use Cake\Routing\Router;
use App\Model\Table\AppSettingsTable;

class KMPMailer extends Mailer
{
    protected AppSettingsTable $appSettings;
    public function __construct()
    {
        parent::__construct();
        $this->appSettings = $this->getTableLocator()->get("AppSettings");
    }

    public function resetPassword($member)
    {

        $sendFrom = $this->appSettings->getAppSetting("System Email From", "donotreply@webminister.ansteorra.org");
        $url = Router::url([
            "controller" => "Members",
            "action" => "resetPassword",
            "_full" => true,
            $member->password_token,
        ]);
        $this->setTo($member->email_address)
            ->setFrom($sendFrom)
            ->setSubject("Reset password")
            ->setViewVars([
                "email" => $member->email_address,
                "passwordResetUrl" => $url,
                "siteAdminSignature" => $this->appSettings->getAppSetting("Site Admin Signature", "Webminister"),
            ]);
    }

    public function mobileCard($member)
    {

        $sendFrom = $this->appSettings->getAppSetting("System Email From", "donotreply@webminister.ansteorra.org");
        $url = Router::url([
            "controller" => "Members",
            "action" => "ViewMobileCard",
            "_full" => true,
            $member->mobile_card_token,
        ]);
        $this->setTo($member->email_address)
            ->setFrom($sendFrom)
            ->setSubject("Your Mobile Card URL")
            ->setViewVars([
                "email" => $member->email_address,
                "mobileCardUrl" => $url,
                "siteAdminSignature" => $this->appSettings->getAppSetting("Email.SiteAdminSignature", "Webminister"),
            ]);
    }

    public function newRegistration($member)
    {
        $url = Router::url([
            "controller" => "Members",
            "action" => "resetPassword",
            "_full" => true,
            $member->password_token,
        ]);
        $portalName = $this->appSettings->getAppSetting("Portal Name", "Ansteorran Management Portal");
        $this->setTo($member->email_address)
            ->setFrom("donotreply@webminister.ansteorra.org")
            ->setSubject("Welcome " . $member->sca_name . " to " . $portalName)
            ->setViewVars([
                "email" => $member->email_address,
                "passwordResetUrl" => $url,
                "memberScaName" => $member->sca_name,
                "siteAdminSignature" => $this->appSettings->getAppSetting("Email.SiteAdminSignature", "Webminister"),
            ]);
    }

    public function notifySecretaryOfNewMember($member)
    {
        $to = $this->appSettings->getAppSetting("New Member Contact", "webminister@marshal.ansteorra.org");
        $url = Router::url([
            "controller" => "Members",
            "action" => "view",
            "_full" => true,
            $member->id,
        ]);
        $this->setTo($to)
            ->setFrom("donotreply@webminister.ansteorra.org")
            ->setSubject("New Member Registration")
            ->setViewVars([
                "memberViewUrl" => $url,
                "memberScaName" => $member->sca_name,
                "memberCardPresent" => strlen($member->membership_card_path) > 0,
                "siteAdminSignature" => $this->appSettings->getAppSetting("Email.SiteAdminSignature", "Webminister"),
            ]);
    }

    public function notifySecretaryOfNewMinorMember($member)
    {
        $to = $this->appSettings->getAppSetting("New Member Contact", "webminister@marshal.ansteorra.org");
        $url = Router::url([
            "controller" => "Members",
            "action" => "view",
            "_full" => true,
            $member->id,
        ]);
        $this->setTo($to)
            ->setFrom("donotreply@webminister.ansteorra.org")
            ->setSubject("New Minor Member Registration")
            ->setViewVars([
                "memberViewUrl" => $url,
                "memberScaName" => $member->sca_name,
                "memberCardPresent" => strlen($member->membership_card_path) > 0,
                "siteAdminSignature" => $this->appSettings->getAppSetting("Email.SiteAdminSignature", "Webminister"),
            ]);
    }

    public function notifyApprover(
        string $to,
        string $approvalToken,
        string $memberScaName,
        string $approverScaName,
        string $activityName,
    ) {
        $url = Router::url([
            "controller" => "AuthorizationApprovals",
            "action" => "myQueue",
            "_full" => true,
            $approvalToken,
        ]);
        $this->setTo($to)
            ->setSubject("Authorization Approval Request")
            ->setViewVars([
                "authorizationResponseUrl" => $url,
                "memberScaName" => $memberScaName,
                "approverScaName" => $approverScaName,
                "activityName" => $activityName,
                "siteAdminSignature" => $this->appSettings->getAppSetting("Email.SiteAdminSignature", "Webminister"),
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
        $url = Router::url([
            "controller" => "Members",
            "action" => "viewCard",
            "_full" => true,
            $memberId,
        ]);

        $this->setTo($to)
            ->setSubject("Update on Authorization Request")
            ->setViewVars([
                "memberScaName" => $memberScaName,
                "approverScaName" => $ApproverScaName,
                "status" => $status,
                "activityName" => $activityName,
                "memberCardUrl" => $url,
                "nextApproverScaName" => $nextApproverScaName,
                "siteAdminSignature" => $this->appSettings->getAppSetting("Email.SiteAdminSignature", "Webminister"),
            ]);
    }
}
