<?php
declare(strict_types=1);

use App\Services\NavigationContextService;
use App\Services\TurboConfig;
use App\Services\UiUpdateRegistry;

$turboEnabled = TurboConfig::enabled() && !TurboConfig::boundary($this->getRequest());
$contextSignature = $turboEnabled ? (new NavigationContextService())->signature($this->getRequest()) : '';
?>
<meta name="kmp-turbo-enabled" content="<?= $turboEnabled ? 'true' : 'false' ?>">
<?php if ($turboEnabled) : ?>
    <meta name="kmp-native-navigation" content="<?= h(json_encode(TurboConfig::nativeNavigation())) ?>">
    <meta name="kmp-navigation-context" content="<?= h($contextSignature) ?>">
    <meta name="kmp-navigation-context-url" content="<?= h($this->Url->build('/navigation/context')) ?>">
    <meta name="turbo-refresh-method" content="morph">
    <meta name="turbo-refresh-scroll" content="preserve">
    <meta name="view-transition" content="same-origin">
    <?php if (TurboConfig::liveEnabled() && UiUpdateRegistry::supports($this->getRequest())) : ?>
        <meta name="kmp-live-subscriptions-url" content="<?= h($this->Url->build('/live/subscriptions')) ?>">
        <meta name="kmp-ui-refresh-url" content="<?= h($this->Url->build('/ui/refresh')) ?>">
    <?php endif; ?>
<?php else : ?>
    <meta name="turbo-visit-control" content="reload">
<?php endif; ?>
