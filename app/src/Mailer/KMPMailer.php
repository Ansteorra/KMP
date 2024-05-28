<?php
namespace App\Mailer;

use Cake\Mailer\Mailer;
use Cake\Routing\Router;

class KMPMailer extends Mailer
{
    public function welcome($user)
    {
        $this
            ->setTo($user->email)
            ->setSubject(sprintf('Welcome %s', $user->name))
            ->viewBuilder()
            ->setTemplate('welcome_mail');  // By default template with same name as method name is used.
    }

    public function resetPassword($member)
    {
        $url = Router::url(
            [
                'controller' => 'Members',
                'action' => 'resetPassword',
                '_full' => true,
                $member->password_token
            ]
        );
        $this
            ->setTo($member->email_address)
            ->setFrom('donotreply@webminister.ansteorra.org')
            ->setSubject('Reset password')
            ->setViewVars(
                [
                    'email' => $member->email_address,
                    'passwordResetUrl' => $url
                ]
            );
    }

    public function notifyApprover($user, $approval)
    {
        $this
            ->setTo($user->email)
            ->setSubject('Notify Approver')
            ->setViewVars(['token' => $approval->token]);
    }

    public function notifyRequester($user, $authorization)
    {
        $this
            ->setTo($user->email)
            ->setSubject('Notify Requester')
            ->setViewVars(['token' => $user->token]);
    }
}
