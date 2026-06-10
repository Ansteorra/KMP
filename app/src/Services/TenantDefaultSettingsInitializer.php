<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\StaticHelpers;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;
use Cake\Log\Log;

/**
 * Ensures required tenant-scoped application settings exist for the current datasource.
 */
class TenantDefaultSettingsInitializer
{
    public const CURRENT_CONFIG_VERSION = '25.11.06.b';

    /**
     * Create or update required tenant application settings when the config version changes.
     */
    public function initialize(): void
    {
        $configVersion = StaticHelpers::getAppSetting('KMP.configVersion', '0.0.0', null, true);
        if ($configVersion == self::CURRENT_CONFIG_VERSION) {
            return;
        }

        $modelCacheCleared = Cache::clear('_cake_model_');
        if (!$modelCacheCleared) {
            Log::warning('Failed clearing _cake_model_ cache during config version update.');
        } else {
            StaticHelpers::setAppSetting('KMP.configVersion', self::CURRENT_CONFIG_VERSION, null, true);
        }

        StaticHelpers::getAppSetting('KMP.BranchInitRun', '', null, true);
        StaticHelpers::getAppSetting('KMP.KingdomName', 'please_set', null, true);
        StaticHelpers::getAppSetting('KMP.LongSiteTitle', 'Kingdom Management Portal', null, true);
        StaticHelpers::getAppSetting('KMP.ShortSiteTitle', 'KMP', null, true);
        StaticHelpers::getAppSetting('KMP.BannerLogo', 'badge.png', 'image', true);
        StaticHelpers::getAppSetting('KMP.Login.Graphic', 'populace_badge.png', 'image', true);
        StaticHelpers::getAppSetting('KMP.EnablePublicRegistration', 'yes', null, true);
        StaticHelpers::getAppSetting('KMP.DefaultTimezone', 'America/Chicago', null, true);
        StaticHelpers::getAppSetting('App.version', '0.0.0', null, true);

        StaticHelpers::getAppSetting('Member.ViewCard.Graphic', 'auth_card_back.gif', 'image', true);
        StaticHelpers::getAppSetting('Member.ViewCard.HeaderColor', 'gold', null, true);
        StaticHelpers::getAppSetting('Member.ViewCard.Template', 'view_card', null, true);
        StaticHelpers::getAppSetting('Member.ViewMobileCard.Template', 'view_mobile_card', null, true);
        StaticHelpers::getAppSetting('Member.MobileCard.ThemeColor', 'gold', null, true);
        StaticHelpers::getAppSetting('Member.MobileCard.BgColor', 'gold', null, true);

        StaticHelpers::getAppSetting('Members.AccountVerificationContactEmail', 'please_set', null, true);
        StaticHelpers::getAppSetting('Members.AccountDisabledContactEmail', 'please_set', null, true);
        StaticHelpers::getAppSetting('Members.NewMemberSecretaryEmail', 'member@test.com', null, true);
        StaticHelpers::getAppSetting('Members.NewMinorSecretaryEmail', 'minorSet@test.com', null, true);

        StaticHelpers::getAppSetting('Email.SystemEmailFromAddress', 'site@test.com', null, true);
        StaticHelpers::getAppSetting('Email.SiteAdminSignature', 'site', null, true);

        StaticHelpers::getAppSetting('Activity.SecretaryEmail', 'please_set', null, true);
        StaticHelpers::getAppSetting('Activity.SecretaryName', 'please_set', null, true);

        StaticHelpers::getAppSetting('Warrant.LastCheck', DateTime::now()->subDays(1)->toDateString(), null, true);
        StaticHelpers::getAppSetting('KMP.RequireActiveWarrantForSecurity', 'yes', null, true);
        StaticHelpers::getAppSetting('Warrant.RosterApprovalsRequired', '2', null, true);

        StaticHelpers::getAppSetting(
            'KMP.AppSettings.HelpUrl',
            'https://github.com/Ansteorra/KMP/wiki/App-Settings',
            null,
            true,
        );
        StaticHelpers::getAppSetting('Branches.Types', yaml_emit([
            'Kingdom',
            'Principality',
            'Region',
            'Local Group',
            'N/A',
        ]), 'yaml', true);

        StaticHelpers::getAppSetting('Activities.configVersion', '25.01.11.a', null, true);
        StaticHelpers::getAppSetting(
            'Activities.NextStatusCheck',
            DateTime::now()->subDays(1)->toDateString(),
            null,
            true,
        );
        StaticHelpers::getAppSetting('Plugin.Activities.Active', 'yes', null, true);

        StaticHelpers::getAppSetting('Officer.configVersion', '25.01.11.a', null, true);
        StaticHelpers::getAppSetting(
            'Officer.NextStatusCheck',
            DateTime::now()->subDays(1)->toDateString(),
            null,
            true,
        );
        StaticHelpers::getAppSetting('Plugin.Officers.Active', 'yes', null, true);

        StaticHelpers::getAppSetting('Waivers.configVersion', '25.01.11.a', null, true);
        StaticHelpers::getAppSetting('Plugin.Waivers.Active', 'yes', null, true);
        StaticHelpers::getAppSetting('Plugin.Waivers.ShowInNavigation', 'yes', null, true);
        StaticHelpers::getAppSetting('Plugin.Waivers.HelloWorldMessage', 'Hello, World!', null, true);
        StaticHelpers::getAppSetting('Waivers.ComplianceDays', '2', null, true);

        StaticHelpers::getAppSetting('Awards.configVersion', '26.02.22.a', null, true);
        StaticHelpers::getAppSetting('Awards.RecButtonClass', 'btn-warning', null, true);
        StaticHelpers::getAppSetting(
            'Member.AdditionalInfo.CallIntoCourt',
            'select:Never,With Notice,Without Notice|user|public',
            null,
            true,
        );
        StaticHelpers::getAppSetting(
            'Member.AdditionalInfo.CourtAvailability',
            'select:None,Morning,Evening,Any|user|public',
            null,
            true,
        );
        StaticHelpers::getAppSetting(
            'Member.AdditionalInfo.PersonToGiveNoticeTo',
            'text|user|public',
            null,
            true,
        );
        StaticHelpers::getAppSetting('Plugin.Awards.Active', 'yes', null, true);
    }
}
