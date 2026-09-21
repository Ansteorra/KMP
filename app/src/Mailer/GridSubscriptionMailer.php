<?php
declare(strict_types=1);

namespace App\Mailer;

use App\KMP\StaticHelpers;
use Cake\Mailer\Mailer;

class GridSubscriptionMailer extends Mailer
{
    /** All row values are already display text; templates escape them again for HTML. */
    public function summary(string $email, string $name, array $report): void
    {
        $subjectName = !empty($report['sample']) ? __('Sample: {0}', $name) : $name;
        $this->setTo($email)
            ->setFrom(StaticHelpers::getAppSetting('Email.SystemEmailFromAddress', 'site@test.com', null, true))
            ->setSubject(StaticHelpers::getAppSetting('KMP.ShortSiteTitle') . ': ' . $subjectName)
            ->setEmailFormat('both')->setViewVars(compact('name', 'report'));
        $this->viewBuilder()->setTemplate('grid_subscription');
    }
}
