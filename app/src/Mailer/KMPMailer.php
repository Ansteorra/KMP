<?php

namespace App\Mailer;

use Cake\Mailer\Mailer;
use Cake\Routing\Router;

class KMPMailer extends Mailer
{
    public function welcome($user)
    {
        $this->setTo($user->email)
            ->setSubject(sprintf("Welcome %s", $user->name))
            ->viewBuilder()
            ->setTemplate("welcome_mail"); // By default template with same name as method name is used.
    }

    public function resetPassword($member)
    {
        $url = Router::url([
            "controller" => "Members",
            "action" => "resetPassword",
            "_full" => true,
            $member->password_token,
        ]);
        $this->setTo($member->email_address)
            ->setFrom("donotreply@webminister.ansteorra.org")
            ->setSubject("Reset password")
            ->setViewVars([
                "email" => $member->email_address,
                "passwordResetUrl" => $url,
            ]);
    }

    public function notifyApprover(
        string $to,
        string $approvalToken,
        string $memberScaName,
        string $approverScaName,
        string $authorizationTypeName,
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
                "authorizationTypeName" => $authorizationTypeName,
            ]);
    }

    public function notifyRequester(
        string $to,
        string $status,
        string $memberScaName,
        int $memberId,
        string $ApproverScaName,
        string $nextApproverScaName,
        string $authorizationTypeName,
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
                "authorizationTypeName" => $authorizationTypeName,
                "memberCardUrl" => $url,
                "nextApproverScaName" => $nextApproverScaName,
            ]);
    }
}
