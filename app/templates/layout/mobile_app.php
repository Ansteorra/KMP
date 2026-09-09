<?php

/**
 * KMP Mobile App Layout Template
 * 
 * Provides a consistent mobile-optimized layout for PWA features based exactly on
 * view_mobile_card.php structure (lines 1-215 and 244-end).
 * 
 * This layout wraps mobile pages with the same PWA infrastructure, menu system,
 * and styling as the mobile card, ensuring a consistent experience across all
 * mobile features.
 * 
 * Usage in Controllers:
 * ```php
 * $this->viewBuilder()->setLayout('mobile_app');
 * $this->set('mobileTitle', 'Page Title');
 * $this->set('mobileSection', 'events'); // Section for color theming
 * $this->set('mobileBackUrl', '/back/to/somewhere'); // Optional
 * $this->set('mobileHeaderColor', '#ffc107'); // Optional
 * $this->set('showRefreshBtn', false); // Optional, default false
 * $this->set('watermarkImage', 'data:image/...'); // Optional watermark
 * ```
 * 
 * @var \App\View\AppView $this
 * @var string $mobileTitle Page title for the mobile view
 * @var string|null $mobileSection Section identifier for color theming (auth-card, events, rsvps, approvals, request, waivers)
 * @var string|null $mobileBackUrl Optional back button URL  
 * @var string|null $mobileHeaderColor Optional header background color (default: #ffc107)
 * @var bool $showRefreshBtn Whether to show the refresh button (default: false)
 * @var string|null $watermarkImage Optional watermark image data URI
 */

use Cake\Routing\Asset;
use App\Services\ViewCellRegistry;

$publicOfflineShell = $publicOfflineShell ?? false;

// Set defaults
$mobileTitle = $mobileTitle ?? 'Mobile App';
$mobileSection = $mobileSection ?? null; // Section for color theming
$mobileBackUrl = $mobileBackUrl ?? null;
$mobileIcon = $mobileIcon ?? null; // Section icon (e.g., 'bi-calendar-event')
$mobileHeaderColor = $mobileHeaderColor ?? '#ffc107'; // KMP yellow
$showRefreshBtn = $showRefreshBtn ?? false; // Don't show refresh by default (only for card)
$watermarkImage = $watermarkImage ?? null;

// Get auth card accent color from app settings (used for section branding)
$authCardAccentColor = $this->KMP->getAppSetting('Member.MobileCard.ThemeColor', '#8b5cf6');

// Get service worker and URLs if authenticated
$currentUser = $publicOfflineShell ? null : $this->request->getAttribute('identity');
$swUrl = Asset::url("sw.js");
$cardUrlForManifest = $currentUser ? $this->Url->build([
    'controller' => 'Members',
    'action' => 'viewMobileCard',
    'plugin' => null
]) : null;
?>
<!DOCTYPE html>
<html lang="<?= h(\Cake\Core\Configure::read('App.language') ?: 'en') ?>">

<head>
    <?= $this->Html->charset() ?>
    <meta name="kmp-short-site-title" content="<?= h($this->KMP->getAppSetting('KMP.ShortSiteTitle')) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (!$publicOfflineShell) : ?>
    <meta name="csrf-token" content="<?= $this->request->getAttribute('csrfToken') ?>">
    <?php else : ?>
    <meta name="kmp-offline-shell" content="1">
    <?php endif; ?>
    <title>
        <?= h($this->KMP->getAppSetting('KMP.ShortSiteTitle')) ?>: <?= h($mobileTitle) ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#2c1810">

    <?php if ($cardUrlForManifest): ?>
    <link rel="manifest"
        href="<?= $this->Url->build(['controller' => 'Members', 'action' => 'card.webmanifest', 'plugin' => null]) ?>" />
    <?php endif; ?>

    <!-- CSS -->
    <?= $this->Vite->css('app') ?>

    <?= $this->element('mobile_styles', compact('authCardAccentColor', 'watermarkImage')) ?>

    <!-- JavaScript -->
    <?= $this->Vite->script('controllers') ?>
    <?= $this->Vite->script('index') ?>
    <?= $this->fetch('script') ?>
    <?php if (!$publicOfflineShell) : ?>
    <?= $this->element('offline_session') ?>
    <?php endif; ?>
</head>

<body class="viewMobileCard"<?php if ($mobileSection): ?> data-section="<?= h($mobileSection) ?>"<?php endif; ?>>
    <a class="visually-hidden-focusable position-absolute top-0 start-0 z-3 m-2 p-2 bg-body border rounded" href="#main-content">
        <?= __('Skip to main content') ?>
    </a>
    <div id="flash-messages">
        <?= $publicOfflineShell ? '' : $this->Flash->render() ?>
    </div>
    <?php
    // Determine if this is the auth card page and build auth card URL
    $currentController = $this->request->getParam('controller');
    $currentAction = $this->request->getParam('action');
    $currentPlugin = $this->request->getParam('plugin');
    $isAuthCard = ($currentController === 'Members' && $currentAction === 'viewMobileCard' && $currentPlugin === null);

    // My RSVPs also works offline (uses cached data)
    $isMyRsvps = ($currentController === 'GatheringAttendances' && $currentAction === 'myRsvps' && $currentPlugin === null);

    // Pages that don't need the offline overlay
    $isCalendar = $currentController === 'Gatherings' && $currentAction === 'mobileCalendar';
    $isAuthCard = $isAuthCard || ($publicOfflineShell && $mobileSection === 'auth-card');
    $skipOfflineOverlay = $publicOfflineShell || $isAuthCard || $isMyRsvps || $isCalendar;

    $authCardUrl = ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null];
    $authCardUrlBuilt = $this->Url->build($authCardUrl);
    $logoutUrlBuilt = $this->Url->build(['controller' => 'Members', 'action' => 'logout', 'plugin' => null]);
    ?>
    <main id="main-content" tabindex="-1" data-controller="<?= $publicOfflineShell ? 'offline-vault ' : '' ?>member-mobile-card-pwa<?= isset($cardUrl) ? ' member-mobile-card-profile' : '' ?><?= !$skipOfflineOverlay ? ' mobile-offline-overlay' : '' ?>"
        <?php if (isset($cardUrl)): ?> data-member-mobile-card-profile-url-value="<?= h($cardUrl) ?>"
        data-member-mobile-card-profile-pwa-ready-value="false" <?php endif; ?>
        data-member-mobile-card-pwa-sw-url-value="<?= $swUrl ?>" data-member-mobile-card-pwa-pwa-ready-value="false"
        data-member-mobile-card-pwa-auth-card-url-value="<?= h($authCardUrlBuilt) ?>"
        data-member-mobile-card-pwa-is-auth-card-value="<?= $isAuthCard ? 'true' : 'false' ?>"
        <?php if (!$skipOfflineOverlay): ?>
        data-mobile-offline-overlay-auth-card-url-value="<?= h($authCardUrlBuilt) ?>" <?php endif; ?>>
        <div class="mobile-header-bar">
            <?php
            // Mobile Menu - Plugin-registered action items
            $mobileMenuItems = [];
            if (!$publicOfflineShell && isset($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU]) && !empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU])) {
                $mobileMenuItems = $pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU];

                // Convert URL arrays to strings
                foreach ($mobileMenuItems as &$item) {
                    if (is_array($item['url'])) {
                        $item['url'] = $this->Url->build($item['url']);
                    }
                }
                unset($item); // Break reference

                // Convert associative array to numeric array for JSON encoding
                // ViewCellRegistry returns items keyed by order, but JavaScript expects a simple array
                $mobileMenuItems = array_values($mobileMenuItems);
            }

            if ($publicOfflineShell) {
                $mobileMenuItems = [
                    ['label' => 'Auth Card', 'url' => '/members/view-mobile-card', 'icon' => 'bi-person-vcard', 'color' => 'auth-card', 'order' => -10],
                    ['label' => 'My RSVPs', 'url' => '/gathering-attendances/my-rsvps', 'icon' => 'bi-calendar-check', 'color' => 'rsvps', 'order' => 10],
                    ['label' => 'Events', 'url' => '/gatherings/mobile-calendar', 'icon' => 'bi-calendar-event', 'color' => 'events', 'order' => 20],
                    ['label' => 'Sign in', 'url' => '/members/login', 'icon' => 'bi-box-arrow-in-right', 'color' => 'secondary', 'order' => 999],
                ];
            }

            // Add core "Auth Card" menu item if not on viewMobileCard page
            if (!$isAuthCard && !$publicOfflineShell) {
                $mobileMenuItems[] = [
                    'label' => 'Auth Card',
                    'icon' => 'bi-person-vcard',
                    'url' => $this->Url->build($authCardUrl),
                    'order' => -10,  // Negative order to place it first
                    'color' => 'auth-card',  // Section-specific color
                    'badge' => null
                ];
            }

            // Filter out current page from menu items
            $currentUrl = $this->Url->build([
                'controller' => $currentController,
                'action' => $currentAction,
                'plugin' => $currentPlugin
            ]);

            if ($publicOfflineShell) {
                $currentUrl = ['auth-card' => '/members/view-mobile-card', 'rsvps' => '/gathering-attendances/my-rsvps', 'events' => '/gatherings/mobile-calendar'][$mobileSection];
            }
            $mobileMenuItems = array_filter($mobileMenuItems, function ($item) use ($currentUrl) {
                // Normalize URLs for comparison (remove trailing slashes, query params, etc.)
                $itemUrl = parse_url($item['url'], PHP_URL_PATH);
                $pageUrl = parse_url($currentUrl, PHP_URL_PATH);
                return rtrim($itemUrl, '/') !== rtrim($pageUrl, '/');
            });

            // Re-index array after filtering
            $mobileMenuItems = array_values($mobileMenuItems);

            // Add "Switch to Desktop" as the last menu item
            if (!$publicOfflineShell) {
            $mobileMenuItems[] = [
                'label' => 'Switch to Desktop',
                'icon' => 'bi-display',
                'url' => $this->Url->build(['controller' => 'App', 'action' => 'switchView', 'plugin' => null, '?' => ['mode' => 'desktop']]),
                'order' => 9999,  // High order to place it last
                'color' => 'secondary',
                'badge' => null
            ];

            }
            if (!empty($mobileMenuItems)) :
            ?>

            <div class="mobile-menu-pill-container" data-controller="member-mobile-card-menu"
                data-member-mobile-card-menu-menu-items-value='<?= h(json_encode($mobileMenuItems)) ?>'>
                <button class="mobile-menu-pill" data-member-mobile-card-menu-target="fab"
                    data-action="click->member-mobile-card-menu#toggleMenu" aria-label="Open menu" type="button">
                    <i class="bi bi-list" aria-hidden="true"></i>
                    <span>Menu</span>
                </button>
                <div class="mobile-menu-items" data-member-mobile-card-menu-target="menu" hidden>
                </div>
            </div>
            <?php endif; ?>

            <!-- Page Title -->
            <h1 class="mobile-page-title">
                <?php if ($mobileIcon): ?>
                    <i class="bi <?= h($mobileIcon) ?>" aria-hidden="true"></i>
                <?php endif; ?>
                <span><?= h($mobileTitle) ?></span>
            </h1>

            <span data-member-mobile-card-pwa-target="status"
                class="bg-danger" title="Offline" role="status" aria-label="Offline"></span>
        </div>
        <div class="mx-3"><?= $this->element($publicOfflineShell ? 'offline_recovery' : 'offline_access') ?></div>
        <!-- Page Content -->
        <div data-offline-vault-target="content"><?= $this->fetch('content') ?></div>

        <?php if ($showRefreshBtn): ?>
        <!-- Refresh Button (typically only for mobile card) -->
        <div scope="row" class="row ms-3 me-3 mb-5 mt-2">
            <span scope="col" class="col text-center">
                <span data-member-mobile-card-pwa-target="refreshBtn"
                    data-action="click->member-mobile-card-profile#loadCard"
                    class="btn btn-small text-center btn-secondary bi bi-arrow-clockwise"></span>
            </span>
        </div>
        <?php endif; ?>

        <?php if ($publicOfflineShell) : ?>
        <div class="mx-3">
<details class="small my-2" data-offline-vault-target="device" hidden>
    <summary class="py-2">This device</summary>
    <p>Saved information is available for seven days after verification. Waiting RSVPs send when you reconnect and sign in.</p>
    <button type="button" class="btn btn-outline-secondary my-2"
                    data-action="offline-vault#lock">
                    Log out of <?= h($this->KMP->getAppSetting('KMP.ShortSiteTitle')) ?></button>
                <button type="button" class="btn btn-outline-danger mb-2" data-action="offline-vault#forget">Stop trusting this device</button>
</details>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="row text-center">
            <?= $publicOfflineShell ? '' : $this->element('copyrightFooter', []) ?>
        </div>

    </main>

</body>

</html>
