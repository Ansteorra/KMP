<?php

namespace Activities\Mailer;

use App\Mailer\TemplateAwareMailerTrait;
use Cake\Mailer\Mailer;
use Cake\Routing\Router;
use App\KMP\StaticHelpers;

class ActivitiesMailer extends Mailer
{
    use TemplateAwareMailerTrait;

    /**
     * Initialize the ActivitiesMailer.
     *
     * Ensures the base Mailer is constructed.
     */
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

    /**
     * Prepare and configure an email notifying a requester about an authorization request update.
     *
     * Sets recipient, sender, subject, and view variables used by the mail template (including links and participant names).
     *
     * @param string $to Recipient email address.
     * @param string $status Current status of the authorization request.
     * @param string $memberScaName Display name of the member who requested the authorization.
     * @param int $memberId Identifier of the member (used to build a link to the member card).
     * @param string $ApproverScaName Display name of the approver who processed or updated the request.
     * @param string $nextApproverScaName Display name of the next approver in the workflow, if any.
     * @param string $activityName Name of the activity associated with the authorization request.
     */
    public function notifyRequester(
        string $to,
        string $status,
        string $memberScaName,
        int $memberId,
        string $approverScaName,
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
                "approverScaName" => $approverScaName,
                "status" => $status,
                "activityName" => $activityName,
                "memberCardUrl" => $url,
                "nextApproverScaName" => $nextApproverScaName,
                "siteAdminSignature" => StaticHelpers::getAppSetting("Email.SiteAdminSignature"),
            ]);
    }

    public function notifyApproverOfRetraction(
        string $to,
        string $activityName,
        string $approverScaName,
        string $requesterScaName
    ): void {
        $sendFrom = StaticHelpers::getAppSetting('Email.SystemEmailFromAddress');

        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject("Authorization Request Retracted: $activityName")
            ->setViewVars([
                'requesterScaName' => $requesterScaName,
                'activityName' => $activityName,
                'approverScaName' => $approverScaName,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature'),
            ]);
    }
}
