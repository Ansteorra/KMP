<?php

declare(strict_types=1);

namespace App\Mailer;

use App\KMP\StaticHelpers;
use App\Model\Table\AppSettingsTable;
use Cake\Log\Log;
use Cake\Mailer\Mailer;

class KMPMailer extends Mailer
{
    use TemplateAwareMailerTrait;

    protected AppSettingsTable $appSettings;

    public function __construct()
    {
        parent::__construct();
        $this->appSettings = $this->getTableLocator()->get('AppSettings');
    }

    public function resetPassword($to, $url): void
    {
        $sendFrom = StaticHelpers::getAppSetting('Email.SystemEmailFromAddress');
        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject('Reset password')
            ->setViewVars([
                'email' => $to,
                'passwordResetUrl' => $url,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature'),
            ]);
    }

    public function mobileCard($to, $url): void
    {
        $sendFrom = StaticHelpers::getAppSetting('Email.SystemEmailFromAddress');
        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject('Your Mobile Card URL')
            ->setViewVars([
                'email' => $to,
                'mobileCardUrl' => $url,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature'),
            ]);
    }

    public function newRegistration($to, $url, $sca_name): void
    {
        $sendFrom = StaticHelpers::getAppSetting('Email.SystemEmailFromAddress');
        $portalName = StaticHelpers::getAppSetting('KMP.LongSiteTitle');
        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject("Welcome $sca_name to $portalName")
            ->setViewVars([
                'email' => $to,
                'passwordResetUrl' => $url,
                'portalName' => $portalName,
                'memberScaName' => $sca_name,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature'),
            ]);
    }

    public function notifySecretaryOfNewMember($to, $url, $sca_name, $membershipCardPresent): void
    {
        $sendFrom = StaticHelpers::getAppSetting('Email.SystemEmailFromAddress');
        $to = StaticHelpers::getAppSetting('Members.NewMemberSecretaryEmail');
        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject('New Member Registration')
            ->setViewVars([
                'memberViewUrl' => $url,
                'memberScaName' => $sca_name,
                'memberCardPresent' => $membershipCardPresent ? "uploaded" : "not uploaded",
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature'),
            ]);
    }

    public function notifySecretaryOfNewMinorMember($to, $url, $sca_name, $membershipCardPresent): void
    {
        $sendFrom = StaticHelpers::getAppSetting('Email.SystemEmailFromAddress');
        $to = StaticHelpers::getAppSetting('Members.NewMinorSecretaryEmail');
        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject('New Minor Member Registration')
            ->setViewVars([
                'memberViewUrl' => $url,
                'memberScaName' => $sca_name,
                'memberCardPresent' => $membershipCardPresent ? "uploaded" : "not uploaded",
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature'),
            ]);
    }

    public function notifyOfWarrant(
        string $to,
        string $memberScaName,
        string $warrantName,
        string $warrantStart,
        string $warrantExpires,
    ): void {
        $sendFrom = StaticHelpers::getAppSetting('Email.SystemEmailFromAddress');

        $this->setTo($to)
            ->setFrom($sendFrom)
            ->setSubject("Warrant Issued: $warrantName")
            ->setViewVars([
                'memberScaName' => $memberScaName,
                'warrantName' => $warrantName,
                'warrantExpires' => $warrantExpires,
                'warrantStart' => $warrantStart,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature'),
            ]);
    }
}