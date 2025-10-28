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
 * $this->set('mobileBackUrl', '/back/to/somewhere'); // Optional
 * $this->set('mobileHeaderColor', '#ffc107'); // Optional
 * $this->set('showRefreshBtn', false); // Optional, default false
 * $this->set('watermarkImage', 'data:image/...'); // Optional watermark
 * ```
 * 
 * @var \App\View\AppView $this
 * @var string $mobileTitle Page title for the mobile view
 * @var string|null $mobileBackUrl Optional back button URL  
 * @var string|null $mobileHeaderColor Optional header background color (default: #ffc107)
 * @var bool $showRefreshBtn Whether to show the refresh button (default: false)
 * @var string|null $watermarkImage Optional watermark image data URI
 */

use Cake\Routing\Asset;
use App\Services\ViewCellRegistry;

// Set defaults
$mobileTitle = $mobileTitle ?? 'Mobile App';
$mobileBackUrl = $mobileBackUrl ?? null;
$mobileHeaderColor = $mobileHeaderColor ?? '#ffc107'; // KMP yellow
$showRefreshBtn = $showRefreshBtn ?? false; // Don't show refresh by default (only for card)
$watermarkImage = $watermarkImage ?? null;

// Get service worker and URLs if authenticated
$currentUser = $this->request->getAttribute('identity');
$swUrl = Asset::url("sw.js");
$cardUrlForManifest = null;
if ($currentUser && $currentUser->mobile_card_token) {
    $cardUrlForManifest = $this->Url->build([
        'controller' => 'Members',
        'action' => 'viewMobileCard',
        $currentUser->mobile_card_token,
        'plugin' => null
    ]);
}
?>
<!DOCTYPE html>
<html>

<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrfToken" content="<?= $this->request->getAttribute('csrfToken') ?>">
    <title>
        <?= $this->KMP->getAppSetting("KMP.ShortSiteTitle") ?>: <?= h($mobileTitle) ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="<?= h($mobileHeaderColor) ?>">

    <?php if ($cardUrlForManifest): ?>
        <link rel="manifest"
            href="<?= $this->Url->build(['controller' => 'Members', 'action' => 'card.webmanifest', 'plugin' => null, $currentUser->mobile_card_token]) ?>" />
    <?php endif; ?>

    <!-- CSS -->
    <?= $this->AssetMix->css('app') ?>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        json {
            display: none;
        }

        .viewMobileCard {
            background-color: <?= h($mobileHeaderColor) ?>;
        }

        .cardbox {
            background-color: rgb(255 255 255 / 85%) !important;
        }

        .card-body dl,
        table.card-body-table tbody tr td,
        table.card-body-table tbody tr th {
            background-color: rgb(255 255 255 / 60%) !important;
        }

        <?php if ($watermarkImage): ?>.card-body::after {
            content: "";
            background-image: url('<?= h($watermarkImage) ?>');
            background-size: 21.4rem 20rem;
            background-repeat: no-repeat;
            background-position: center;
            opacity: 1;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            position: absolute;
            z-index: -1;
            display: inline-block;
        }

        <?php endif;
        ?>

        /* Status and Menu Pills - Consistent Styling */
        .mobile-menu-pill-container {
            /* position: fixed;
        top: 8px;
        left: 12px;*/
            z-index: 1000;
        }

        .mobile-menu-pill,
        [data-member-mobile-card-pwa-target="status"] {
            padding: 6px 16px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            white-space: nowrap;
            transition: all 0.3s ease;
            min-height: 32px;
        }

        .mobile-menu-pill i {
            font-size: 16px;
            margin-right: 6px;
        }

        .mobile-menu-pill:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
        }

        .mobile-menu-pill.menu-active {
            background-color: #0d6efd !important;
            color: white !important;
        }

        .mobile-menu-items {
            position: absolute;
            top: 48px;
            left: 0;
            min-width: 280px;
            max-width: 320px;
            background: white;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            animation: menuSlideDown 0.3s ease-out;

            z-index: 1000;
        }

        .mobile-menu-items.menu-opening {
            animation: menuSlideDown 0.3s ease-out;
        }

        .mobile-menu-items.menu-closing {
            animation: menuSlideUp 0.3s ease-in;
        }

        @keyframes menuSlideDown {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.9);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes menuSlideUp {
            from {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

            to {
                opacity: 0;
                transform: translateY(-20px) scale(0.9);
            }
        }

        .mobile-menu-item {
            text-decoration: none;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .mobile-menu-item:hover {
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .mobile-menu-item:last-child {
            margin-bottom: 0 !important;
        }

        .mobile-menu-item i {
            font-size: 20px;
        }

        .mobile-menu-item .badge {
            font-size: 12px;
            padding: 4px 8px;
        }
    </style>

    <!-- JavaScript -->
    <?= $this->AssetMix->script('manifest') ?>
    <?= $this->AssetMix->script('core') ?>
    <?= $this->AssetMix->script('controllers') ?>
    <?= $this->AssetMix->script('index') ?>
    <?= $this->fetch('script') ?>
</head>

<body class="viewMobileCard">
    <?= $this->Flash->render() ?>
    <div data-controller="member-mobile-card-pwa<?= isset($cardUrl) ? ' member-mobile-card-profile' : '' ?>" <?php if (isset($cardUrl)): ?>
        data-member-mobile-card-profile-url-value="<?= h($cardUrl) ?>"
        data-member-mobile-card-profile-pwa-ready-value="false" <?php endif; ?>
        data-member-mobile-card-pwa-sw-url-value="<?= $swUrl ?>" data-member-mobile-card-pwa-pwa-ready-value="false">
        <div class="row">
            <?php
            // Mobile Menu - Plugin-registered action items
            $mobileMenuItems = [];
            if (isset($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU]) && !empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU])) {
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

            // Add core "Auth Card" menu item if not on viewMobileCard page
            $currentController = $this->request->getParam('controller');
            $currentAction = $this->request->getParam('action');
            $currentPlugin = $this->request->getParam('plugin');

            if (!($currentController === 'Members' && $currentAction === 'viewMobileCard' && $currentPlugin === null)) {
                // Build Auth Card URL with mobile_card_token if available
                $authCardUrl = ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null];
                if ($currentUser && $currentUser->mobile_card_token) {
                    $authCardUrl[] = $currentUser->mobile_card_token;
                }

                $mobileMenuItems[] = [
                    'label' => 'Auth Card',
                    'icon' => 'bi-person-vcard',
                    'url' => $this->Url->build($authCardUrl),
                    'order' => -10,  // Negative order to place it first
                    'color' => 'info',
                    'badge' => null
                ];
            }

            // Filter out current page from menu items
            $currentUrl = $this->Url->build([
                'controller' => $currentController,
                'action' => $currentAction,
                'plugin' => $currentPlugin
            ]);

            $mobileMenuItems = array_filter($mobileMenuItems, function ($item) use ($currentUrl) {
                // Normalize URLs for comparison (remove trailing slashes, query params, etc.)
                $itemUrl = parse_url($item['url'], PHP_URL_PATH);
                $pageUrl = parse_url($currentUrl, PHP_URL_PATH);
                return rtrim($itemUrl, '/') !== rtrim($pageUrl, '/');
            });

            // Re-index array after filtering
            $mobileMenuItems = array_values($mobileMenuItems);

            // Add "Switch to Desktop" as the last menu item
            $mobileMenuItems[] = [
                'label' => 'Switch to Desktop',
                'icon' => 'bi-display',
                'url' => $this->Url->build(['controller' => 'App', 'action' => 'switchView', 'plugin' => null, '?' => ['mode' => 'desktop']]),
                'order' => 9999,  // High order to place it last
                'color' => 'secondary',
                'badge' => null
            ];

            if (!empty($mobileMenuItems)) :
            ?>

                <div scope="col" class="col-6 text-start mx-3 my-2">
                    <div class="mobile-menu-pill-container" data-controller="member-mobile-card-menu"
                        data-member-mobile-card-menu-menu-items-value='<?= h(json_encode($mobileMenuItems)) ?>'>
                        <button class="badge mobile-menu-pill btn btn-light rounded-pill shadow text-black"
                            data-member-mobile-card-menu-target="fab"
                            data-action="click->member-mobile-card-menu#toggleMenu" aria-label="Open menu" type="button">
                            <i class="bi bi-list" aria-hidden="true"></i>
                            <span>Menu</span>
                        </button>
                        <div class="mobile-menu-items" data-member-mobile-card-menu-target="menu" hidden>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div scope="col" class="col text-end mx-3 my-2">
                <span data-member-mobile-card-pwa-target="status"
                    class="badge rounded-pill text-center bg-danger">Offline</span>
            </div>
        </div>
        <!-- Page Content -->
        <?= $this->fetch('content') ?>

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

        <!-- Footer -->
        <div class="row text-center">
            <?= $this->element('copyrightFooter', []) ?>
        </div>

        <!-- PWA Cache List -->
        <json data-member-mobile-card-pwa-target="urlCache">
            <?php
            $cacheList = [];
            $cacheList[] = $swUrl;
            $cacheList[] = $this->KMP->getMixScriptUrl('manifest', $this->Url);
            $cacheList[] = $this->KMP->getMixScriptUrl('core', $this->Url);
            $cacheList[] = $this->KMP->getMixScriptUrl('controllers', $this->Url);
            $cacheList[] = $this->KMP->getMixScriptUrl('index', $this->Url);
            $cacheList[] = $this->KMP->getMixStyleUrl('app', $this->Url);
            $cacheList[] = Asset::imageUrl("favicon.ico");
            $cacheList[] = $this->request->getPath();
            if ($cardUrlForManifest) {
                $cacheList[] = $this->Url->build(['controller' => 'Members', 'action' => 'viewMobileCardJson', 'plugin' => null, $currentUser->mobile_card_token]);
            }
            echo json_encode($cacheList); ?>
        </json>
    </div>

</body>

</html>


</html>