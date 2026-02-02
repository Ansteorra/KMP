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
    <meta name="csrf-token" content="<?= $this->request->getAttribute('csrfToken') ?>">
    <title>
        <?= $this->KMP->getAppSetting("KMP.ShortSiteTitle") ?>: <?= h($mobileTitle) ?>
    </title>
    <?= $this->Html->meta('icon') ?>

    <!-- PWA Meta Tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#2c1810">

    <?php if ($cardUrlForManifest): ?>
    <link rel="manifest"
        href="<?= $this->Url->build(['controller' => 'Members', 'action' => 'card.webmanifest', 'plugin' => null, $currentUser->mobile_card_token]) ?>" />
    <?php endif; ?>

    <!-- Medieval Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Crimson+Pro:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

    <!-- CSS -->
    <?= $this->AssetMix->css('app') ?>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="/bootstrap_u_i/font/bootstrap-icons.min.css">

    <style>
    /* ============================================
           KMP Mobile PWA - Medieval Realm Theme
           "Where Honour Meets the Modern Age"
           ============================================ */

    /* iOS Safari viewport fixes */
    html, body {
        height: 100%;
        height: 100dvh; /* Dynamic viewport height for iOS */
    }

    /* CSS Custom Properties - Medieval Palette */
    :root {
        /* Core Medieval Colors */
        --medieval-parchment: #f4efe4;
        --medieval-parchment-dark: #e8dfd0;
        --medieval-ink: #2c1810;
        --medieval-ink-light: #4a3728;
        --medieval-gold: #c9a227;
        --medieval-gold-light: #dab84d;
        --medieval-bronze: #8b6914;
        --medieval-leather: #654321;
        --medieval-stone: #6b7280;
        --medieval-stone-dark: #4b5563;
        
        /* Rich Heraldic Section Colors */
        --section-auth-card: <?=h($authCardAccentColor) ?>;
        --section-events: #1e6f50;      /* Forest Green */
        --section-rsvps: #1e4976;       /* Royal Blue */
        --section-approvals: #8b6914;   /* Heraldic Gold */
        --section-request: #1a5f5f;     /* Teal */
        --section-waivers: #8b2252;     /* Burgundy */

        /* Theme Variables */
        --mobile-header-bg: linear-gradient(180deg, #2c1810 0%, #3d261a 100%);
        --mobile-header-text: #f4efe4;
        --mobile-body-bg: var(--medieval-parchment);
        --mobile-card-bg: #fffef9;
        --mobile-card-shadow: 0 4px 20px rgba(44, 24, 16, 0.12);
        --mobile-card-border-radius: 4px;
        --mobile-text-primary: var(--medieval-ink);
        --mobile-text-secondary: var(--medieval-ink-light);
        --mobile-text-muted: #6b5c4f;
        --mobile-accent: var(--medieval-gold);
        --mobile-success: #1e6f50;
        --mobile-danger: #8b2252;
        --mobile-warning: #8b6914;
        --mobile-info: #1a5f5f;
        
        /* Medieval Typography */
        --font-display: 'Cinzel', serif;
        --font-body: 'Crimson Pro', Georgia, serif;
        
        /* Accessibility - Larger Base Sizes */
        --base-font-size: 17px;
        --heading-font-size: 1.25rem;
        --small-font-size: 14px;
        --label-font-size: 15px;
    }

    json {
        display: none;
    }

    /* ============================================
           Body - Aged Parchment Background
           ============================================ */
    .viewMobileCard {
        background: var(--mobile-body-bg) !important;
        background-image: 
            /* Subtle noise texture */
            url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E"),
            /* Parchment gradient */
            radial-gradient(ellipse at top, #f8f4eb 0%, #f4efe4 50%, #e8dfd0 100%) !important;
        background-attachment: fixed !important;
        min-height: 100vh;
        min-height: 100dvh; /* Dynamic viewport height for iOS Safari */
        font-family: var(--font-body);
        font-size: var(--base-font-size);
        line-height: 1.5;
        /* iOS Safari: Prevent rubber-banding issues with fixed elements */
        overscroll-behavior: none;
    }

    /* ============================================
           Section Branding - Heraldic Banner Style
           ============================================ */

    /* Base branded card - shield/banner accent */
    .cardbox[data-section] {
        border-left: 5px solid var(--section-color, var(--mobile-accent)) !important;
        border-top: 1px solid rgba(44, 24, 16, 0.1);
        position: relative;
    }

    /* Decorative corner flourish for section cards */
    .cardbox[data-section]::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--section-color, var(--mobile-accent)) 0%, transparent 50%);
        opacity: 0.1;
        pointer-events: none;
    }

    .cardbox[data-section] .card-title {
        font-family: var(--font-display);
        letter-spacing: 0.02em;
    }

    .cardbox[data-section] .card-title .section-icon {
        color: var(--section-color, var(--mobile-accent));
    }

    /* Section Color Definitions */
    .cardbox[data-section="auth-card"] { --section-color: var(--section-auth-card); }
    .cardbox[data-section="events"] { --section-color: var(--section-events); }
    .cardbox[data-section="rsvps"] { --section-color: var(--section-rsvps); }
    .cardbox[data-section="approvals"] { --section-color: var(--section-approvals); }
    .cardbox[data-section="request"] { --section-color: var(--section-request); }
    .cardbox[data-section="waivers"] { --section-color: var(--section-waivers); }

    /* ============================================
           Cards - Aged Scroll/Document Style
           Reduced padding, larger fonts for accessibility
           ============================================ */
    .cardbox {
        background-color: var(--mobile-card-bg) !important;
        border-radius: var(--mobile-card-border-radius) !important;
        box-shadow: 
            var(--mobile-card-shadow),
            inset 0 1px 0 rgba(255, 255, 255, 0.8) !important;
        border: 1px solid rgba(44, 24, 16, 0.08) !important;
        /* Subtle aged paper effect */
        background-image: linear-gradient(180deg, 
            rgba(255, 255, 255, 0.5) 0%, 
            transparent 20%, 
            transparent 80%, 
            rgba(139, 105, 20, 0.02) 100%);
    }

    .cardbox .card-body {
        position: relative;
        padding: 12px 14px;
    }

    .cardbox .card-title {
        color: var(--mobile-text-primary);
        font-weight: 600;
        font-family: var(--font-display);
        font-size: var(--heading-font-size);
        letter-spacing: 0.03em;
        margin-bottom: 8px;
    }

    .cardbox .card-header {
        background: linear-gradient(180deg, 
            rgba(139, 105, 20, 0.06) 0%, 
            rgba(139, 105, 20, 0.02) 100%);
        border-bottom: 1px solid rgba(139, 105, 20, 0.12);
        font-family: var(--font-display);
        padding: 10px 14px;
        font-size: var(--label-font-size);
    }

    /* Definition Lists in Cards - Larger text */
    .card-body dl {
        background-color: transparent !important;
        margin-bottom: 8px;
    }

    .card-body dl dt {
        color: var(--mobile-text-secondary);
        font-weight: 600;
        font-family: var(--font-display);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 2px;
    }

    .card-body dl dd {
        color: var(--mobile-text-primary);
        font-weight: 400;
        font-family: var(--font-body);
        font-size: var(--base-font-size);
        margin-bottom: 6px;
    }

    table.card-body-table tbody tr td,
    table.card-body-table tbody tr th {
        background-color: transparent !important;
        font-family: var(--font-body);
        font-size: var(--base-font-size);
        padding: 8px 10px;
    }

    /* Nested cards - inherit section color */
    [data-section] .card:not(.cardbox),
    [data-section] .member-info-card {
        border-radius: 4px;
        border: 1px solid rgba(44, 24, 16, 0.08);
        border-left: 4px solid var(--section-color, var(--mobile-accent));
        box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
        background: var(--mobile-card-bg);
    }

    [data-section] .card:not(.cardbox) .card-header,
    [data-section] .member-info-card .card-header {
        background: linear-gradient(180deg, 
            rgba(139, 105, 20, 0.04) 0%, 
            rgba(139, 105, 20, 0.01) 100%);
        border-bottom: 1px solid rgba(139, 105, 20, 0.08);
    }

    <?php if ($watermarkImage): ?>.cardbox .card-body::after {
        content: "";
        background-image: url('<?= h($watermarkImage) ?>');
        background-size: 21.4rem 20rem;
        background-repeat: no-repeat;
        background-position: center;
        opacity: 0.06;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
        position: absolute;
        z-index: 0;
        display: inline-block;
        pointer-events: none;
    }

    .cardbox .card-body>* {
        position: relative;
        z-index: 1;
    }
    <?php endif; ?>

    /* ============================================
           Header Bar - Compact Medieval Style
           ============================================ */
    .mobile-header-bar {
        background: var(--mobile-header-bg);
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 
            0 3px 12px rgba(44, 24, 16, 0.2),
            inset 0 -1px 0 rgba(201, 162, 39, 0.3);
        gap: 8px;
        /* Section-colored bottom border (defaults to gold) */
        border-bottom: 3px solid var(--current-section-color, var(--medieval-gold));
    }

    /* Decorative header flourish - uses section color */
    .mobile-header-bar::after {
        content: '⚜';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--medieval-parchment);
        padding: 0 6px;
        color: var(--current-section-color, var(--medieval-gold));
        font-size: 12px;
        z-index: 1;
    }
    
    /* Section color definitions for body */
    body[data-section="auth-card"] { --current-section-color: var(--section-auth-card); }
    body[data-section="events"] { --current-section-color: var(--section-events); }
    body[data-section="rsvps"] { --current-section-color: var(--section-rsvps); }
    body[data-section="approvals"] { --current-section-color: var(--section-approvals); }
    body[data-section="request"] { --current-section-color: var(--section-request); }
    body[data-section="waivers"] { --current-section-color: var(--section-waivers); }
    
    /* Subtle section-colored background gradient on page */
    body[data-section] {
        background: 
            linear-gradient(180deg, 
                color-mix(in srgb, var(--current-section-color) 8%, var(--medieval-parchment)) 0%,
                var(--medieval-parchment) 150px
            ),
            var(--parchment-texture);
    }

    /* Page Title in Header - Compact */
    .mobile-page-title {
        flex: 1;
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--mobile-header-text);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        display: flex;
        align-items: center;
        gap: 6px;
        font-family: var(--font-display);
        letter-spacing: 0.02em;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }

    .mobile-page-title i {
        font-size: 1rem;
        opacity: 0.9;
        color: var(--medieval-gold-light);
        flex-shrink: 0;
    }

    /* ============================================
           Menu Pill - Compact Style
           ============================================ */
    .mobile-menu-pill-container {
        z-index: 1000;
        flex-shrink: 0;
    }

    .mobile-menu-pill {
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--medieval-gold);
        white-space: nowrap;
        transition: all 0.2s ease;
        min-height: 32px;
        background: linear-gradient(180deg, #3d261a 0%, #2c1810 100%) !important;
        color: var(--medieval-gold-light) !important;
        border-radius: 4px !important;
        box-shadow: 
            0 2px 6px rgba(0, 0, 0, 0.2),
            inset 0 1px 0 rgba(255, 255, 255, 0.05);
        font-family: var(--font-display);
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .mobile-menu-pill i {
        font-size: 14px;
        margin-right: 6px;
        color: var(--medieval-gold);
    }

    .mobile-menu-pill:hover,
    .mobile-menu-pill:focus {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3) !important;
        background: linear-gradient(180deg, #4a3325 0%, #3d261a 100%) !important;
        color: var(--medieval-gold) !important;
        border-color: var(--medieval-gold-light);
    }

    .mobile-menu-pill:active {
        transform: translateY(0);
    }

    .mobile-menu-pill.menu-active {
        background: linear-gradient(180deg, var(--medieval-gold) 0%, var(--medieval-bronze) 100%) !important;
        color: var(--medieval-ink) !important;
        border-color: var(--medieval-gold-light);
    }

    /* ============================================
           Status Indicator - Simple Circle
           ============================================ */
    [data-member-mobile-card-pwa-target="status"] {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        border: 2px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    [data-member-mobile-card-pwa-target="status"].bg-success {
        background: #1e6f50 !important;
        box-shadow: 0 0 6px rgba(30, 111, 80, 0.5);
    }

    [data-member-mobile-card-pwa-target="status"].bg-danger {
        background: #c0392b !important;
        box-shadow: 0 0 6px rgba(192, 57, 43, 0.5);
    }

    /* ============================================
           Mobile Menu Dropdown - Scroll/Banner
           ============================================ */
    .mobile-menu-items {
        position: absolute;
        top: 52px;
        left: 0;
        min-width: 280px;
        max-width: 320px;
        background: var(--mobile-card-bg);
        border-radius: 4px;
        padding: 12px;
        box-shadow: 
            0 10px 40px rgba(44, 24, 16, 0.25),
            inset 0 1px 0 rgba(255, 255, 255, 0.5);
        border: 2px solid rgba(139, 105, 20, 0.2);
        z-index: 1000;
        /* Decorative top banner effect */
        background-image: linear-gradient(180deg, 
            rgba(139, 105, 20, 0.08) 0%, 
            transparent 20%);
    }

    .mobile-menu-items::before {
        content: '— NAVIGATE —';
        display: block;
        text-align: center;
        font-family: var(--font-display);
        font-size: 10px;
        letter-spacing: 0.15em;
        color: var(--medieval-stone);
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(139, 105, 20, 0.15);
    }

    .mobile-menu-items.menu-opening {
        animation: menuSlideDown 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .mobile-menu-items.menu-closing {
        animation: menuSlideUp 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes menuSlideDown {
        from {
            opacity: 0;
            transform: translateY(-12px) scale(0.95);
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
            transform: translateY(-12px) scale(0.95);
        }
    }

    .mobile-menu-item {
        text-decoration: none;
        border-radius: 4px;
        padding: 14px 16px;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        transition: all 0.15s ease;
        color: var(--mobile-card-bg);
        background: transparent;
        font-family: var(--font-display);
        letter-spacing: 0.03em;
    }

    .mobile-menu-item:hover {
        transform: none;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.2);
    }

    .mobile-menu-item:active {
        transform: scale(0.98);
    }

    .mobile-menu-item:last-child {
        margin-bottom: 0 !important;
    }

    .mobile-menu-item i {
        font-size: 18px;
        margin-right: 12px;
        width: 24px;
        text-align: center;
    }

    .mobile-menu-item .badge {
        font-size: 10px;
        padding: 4px 8px;
        margin-left: auto;
        font-family: var(--font-display);
        letter-spacing: 0.05em;
    }

    /* ============================================
           Menu Item Section Colors - Heraldic Banner Style
           ============================================ */
        
    /* Base styling for all section menu buttons */
    .mobile-menu-item[class*="btn-"] {
        color: var(--medieval-parchment) !important;
        transition: all 0.15s ease;
        border-radius: 4px;
        font-family: var(--font-display);
        letter-spacing: 0.03em;
        border: 1px solid rgba(0, 0, 0, 0.1);
        box-shadow: 
            inset 0 1px 0 rgba(255, 255, 255, 0.15),
            0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .mobile-menu-item[class*="btn-"] i {
        color: var(--medieval-parchment) !important;
    }

    /* Section-specific menu button colors - deep heraldic tones */
    .mobile-menu-item.btn-events {
        --btn-base: var(--section-events);
        --btn-dark: color-mix(in srgb, var(--section-events) 75%, black);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-events:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--section-events) 60%, black));
    }

    .mobile-menu-item.btn-rsvps {
        --btn-base: var(--section-rsvps);
        --btn-dark: color-mix(in srgb, var(--section-rsvps) 75%, black);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-rsvps:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--section-rsvps) 60%, black));
    }

    .mobile-menu-item.btn-request {
        --btn-base: var(--section-request);
        --btn-dark: color-mix(in srgb, var(--section-request) 75%, black);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-request:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--section-request) 60%, black));
    }

    .mobile-menu-item.btn-approvals {
        --btn-base: var(--section-approvals);
        --btn-dark: color-mix(in srgb, var(--section-approvals) 75%, black);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-approvals:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--section-approvals) 60%, black));
    }

    .mobile-menu-item.btn-waivers {
        --btn-base: var(--section-waivers);
        --btn-dark: color-mix(in srgb, var(--section-waivers) 75%, black);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-waivers:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--section-waivers) 60%, black));
    }

    .mobile-menu-item.btn-auth-card,
    .mobile-menu-item.btn-info {
        --btn-base: var(--section-auth-card);
        --btn-dark: color-mix(in srgb, var(--section-auth-card) 75%, black);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-auth-card:hover,
    .mobile-menu-item.btn-info:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--section-auth-card) 60%, black));
    }

    /* Secondary (Switch to Desktop) - Stone */
    .mobile-menu-item.btn-secondary {
        --btn-base: var(--medieval-stone);
        --btn-dark: var(--medieval-stone-dark);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-secondary:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--medieval-stone) 60%, black));
    }

    /* Fallback for legacy Bootstrap colors - Gold accent */
    .mobile-menu-item.btn-primary {
        --btn-base: var(--medieval-gold);
        --btn-dark: var(--medieval-bronze);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
        color: var(--medieval-ink) !important;
    }
    .mobile-menu-item.btn-primary i {
        color: var(--medieval-ink) !important;
    }
    .mobile-menu-item.btn-primary:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--medieval-bronze) 70%, black));
    }

    .mobile-menu-item.btn-success {
        --btn-base: var(--mobile-success);
        --btn-dark: color-mix(in srgb, var(--mobile-success) 75%, black);
        background: linear-gradient(180deg, var(--btn-base), var(--btn-dark));
    }
    .mobile-menu-item.btn-success:hover {
        background: linear-gradient(180deg, var(--btn-dark), color-mix(in srgb, var(--mobile-success) 60%, black));
    }

    /* ============================================
           Offline Overlay - Dungeon Style
           ============================================ */
    .mobile-offline-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(44, 24, 16, 0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 20px;
        backdrop-filter: blur(8px);
    }

    .mobile-offline-content {
        background: var(--mobile-card-bg);
        border-radius: 4px;
        padding: 40px 32px;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        border: 2px solid rgba(139, 105, 20, 0.3);
    }

    .mobile-offline-icon {
        font-size: 72px;
        color: var(--mobile-danger);
        margin-bottom: 20px;
    }

    .mobile-offline-title {
        font-size: 24px;
        font-weight: 700;
        color: var(--mobile-text-primary);
        margin-bottom: 12px;
        font-family: var(--font-display);
    }

    .mobile-offline-message {
        font-size: 16px;
        color: var(--mobile-text-secondary);
        margin-bottom: 28px;
        line-height: 1.6;
        font-family: var(--font-body);
    }

    .mobile-offline-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* ============================================
           Form Elements - Larger text, tighter padding
           ============================================ */
    .viewMobileCard .form-control,
    .viewMobileCard .form-select {
        background-color: #fffef9;
        border: 2px solid rgba(139, 105, 20, 0.2);
        border-radius: 4px;
        padding: 10px 12px;
        font-size: var(--base-font-size);
        color: var(--mobile-text-primary);
        transition: all 0.2s ease;
        font-family: var(--font-body);
    }

    .viewMobileCard .form-control:focus,
    .viewMobileCard .form-select:focus {
        border-color: var(--medieval-gold);
        box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.15);
        outline: none;
    }

    .viewMobileCard .form-control::placeholder {
        color: var(--mobile-text-muted);
    }

    .viewMobileCard .form-label {
        font-weight: 600;
        color: var(--mobile-text-primary);
        margin-bottom: 4px;
        font-family: var(--font-display);
        font-size: var(--label-font-size);
        letter-spacing: 0.02em;
    }

    .viewMobileCard .form-text {
        color: var(--mobile-text-secondary);
        font-size: var(--small-font-size);
        font-family: var(--font-body);
    }

    .viewMobileCard .form-check-label {
        font-size: var(--base-font-size);
    }

    /* ============================================
           Buttons - Larger text, tighter padding
           ============================================ */
    .viewMobileCard .btn {
        border-radius: 4px;
        font-weight: 600;
        padding: 10px 18px;
        font-size: var(--base-font-size);
        transition: all 0.2s ease;
        border: 1px solid rgba(0, 0, 0, 0.1);
        font-family: var(--font-display);
        letter-spacing: 0.03em;
        box-shadow: 
            inset 0 1px 0 rgba(255, 255, 255, 0.15),
            0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .viewMobileCard .btn-sm {
        padding: 6px 12px;
        font-size: var(--small-font-size);
    }

    .viewMobileCard .btn-lg {
        padding: 12px 22px;
        font-size: 18px;
    }

    .viewMobileCard .btn-primary {
        background: linear-gradient(180deg, var(--section-rsvps), color-mix(in srgb, var(--section-rsvps) 70%, black));
        color: var(--medieval-parchment);
    }

    .viewMobileCard .btn-primary:hover {
        background: linear-gradient(180deg, color-mix(in srgb, var(--section-rsvps) 85%, black), color-mix(in srgb, var(--section-rsvps) 60%, black));
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 73, 118, 0.3);
    }

    .viewMobileCard .btn-success {
        background: linear-gradient(180deg, var(--mobile-success), color-mix(in srgb, var(--mobile-success) 70%, black));
        color: var(--medieval-parchment);
    }

    .viewMobileCard .btn-success:hover {
        background: linear-gradient(180deg, color-mix(in srgb, var(--mobile-success) 85%, black), color-mix(in srgb, var(--mobile-success) 60%, black));
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 111, 80, 0.3);
    }

    .viewMobileCard .btn-danger {
        background: linear-gradient(180deg, var(--mobile-danger), color-mix(in srgb, var(--mobile-danger) 70%, black));
        color: var(--medieval-parchment);
    }

    .viewMobileCard .btn-danger:hover {
        background: linear-gradient(180deg, color-mix(in srgb, var(--mobile-danger) 85%, black), color-mix(in srgb, var(--mobile-danger) 60%, black));
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(139, 34, 82, 0.3);
    }

    .viewMobileCard .btn-secondary {
        background: linear-gradient(180deg, var(--medieval-stone), var(--medieval-stone-dark));
        color: var(--medieval-parchment);
    }

    .viewMobileCard .btn-secondary:hover {
        background: linear-gradient(180deg, var(--medieval-stone-dark), color-mix(in srgb, var(--medieval-stone) 60%, black));
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(75, 85, 99, 0.3);
    }

    .viewMobileCard .btn-warning {
        background: linear-gradient(180deg, var(--medieval-gold), var(--medieval-bronze));
        color: var(--medieval-ink);
    }

    .viewMobileCard .btn-warning:hover {
        background: linear-gradient(180deg, var(--medieval-bronze), color-mix(in srgb, var(--medieval-bronze) 70%, black));
        transform: translateY(-1px);
    }

    .viewMobileCard .btn-outline-primary {
        border: 2px solid var(--section-rsvps);
        color: var(--section-rsvps);
        background: transparent;
    }

    .viewMobileCard .btn-outline-primary:hover {
        background: var(--section-rsvps);
        color: var(--medieval-parchment);
    }

    .viewMobileCard .btn-outline-secondary {
        border: 2px solid rgba(139, 105, 20, 0.3);
        color: var(--mobile-text-secondary);
        background: transparent;
    }

    .viewMobileCard .btn-outline-secondary:hover {
        background: rgba(139, 105, 20, 0.08);
        border-color: rgba(139, 105, 20, 0.5);
        color: var(--mobile-text-primary);
    }

    /* ============================================
           Alerts - Scroll/Banner Style
           ============================================ */
    .viewMobileCard .alert {
        border-radius: 4px;
        border: 1px solid;
        padding: 16px 20px;
        font-family: var(--font-body);
    }

    .viewMobileCard .alert-info {
        background: linear-gradient(180deg, #e8f4f8, #d1e9f0);
        color: #1a5f5f;
        border-color: rgba(26, 95, 95, 0.2);
    }

    .viewMobileCard .alert-success {
        background: linear-gradient(180deg, #e8f5e9, #d1ebd5);
        color: #1e6f50;
        border-color: rgba(30, 111, 80, 0.2);
    }

    .viewMobileCard .alert-warning {
        background: linear-gradient(180deg, #fef7e8, #fcefc8);
        color: #8b6914;
        border-color: rgba(139, 105, 20, 0.2);
    }

    .viewMobileCard .alert-danger {
        background: linear-gradient(180deg, #fce8ee, #f8d0dc);
        color: #8b2252;
        border-color: rgba(139, 34, 82, 0.2);
    }

    /* ============================================
           Cards within Cards - Nested Document Style
           ============================================ */
    .viewMobileCard .cardbox .card {
        border-radius: 4px;
        border: 1px solid rgba(139, 105, 20, 0.12);
        box-shadow: none;
    }

    .viewMobileCard .cardbox .card-header {
        background: linear-gradient(180deg, rgba(139, 105, 20, 0.06), rgba(139, 105, 20, 0.02));
        border-bottom: 1px solid rgba(139, 105, 20, 0.12);
        font-weight: 600;
        padding: 12px 16px;
        border-radius: 4px 4px 0 0;
        font-family: var(--font-display);
        letter-spacing: 0.02em;
    }

    /* ============================================
           Tabs - Compact Manuscript Style
           ============================================ */
    .viewMobileCard .nav-tabs {
        border-bottom: 2px solid rgba(139, 105, 20, 0.2);
        gap: 2px;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none; /* Firefox */
    }

    .viewMobileCard .nav-tabs::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }

    .viewMobileCard .nav-tabs .nav-link {
        border: none;
        border-radius: 4px 4px 0 0;
        padding: 6px 10px;
        font-weight: 500;
        font-size: 13px;
        color: var(--mobile-text-secondary);
        background: transparent;
        font-family: var(--font-display);
        letter-spacing: 0.01em;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .viewMobileCard .nav-tabs .nav-link:hover {
        color: var(--mobile-text-primary);
        background: rgba(139, 105, 20, 0.05);
    }

    .viewMobileCard .nav-tabs .nav-link.active {
        color: var(--medieval-bronze);
        background: var(--mobile-card-bg);
        border-bottom: 3px solid var(--medieval-gold);
        margin-bottom: -2px;
    }

    /* ============================================
           Refresh Button - Seal Style
           ============================================ */
    .viewMobileCard [data-member-mobile-card-pwa-target="refreshBtn"] {
        background: linear-gradient(180deg, #3d261a, #2c1810);
        color: var(--medieval-gold);
        border-radius: 50%;
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        box-shadow: 0 4px 14px rgba(44, 24, 16, 0.4);
        transition: all 0.2s ease;
        border: 2px solid var(--medieval-gold);
    }

    .viewMobileCard [data-member-mobile-card-pwa-target="refreshBtn"]:hover {
        transform: rotate(180deg);
        box-shadow: 0 6px 20px rgba(44, 24, 16, 0.5);
    }

    /* ============================================
           Footer - Aged styling
           ============================================ */
    .viewMobileCard .text-center small,
    .viewMobileCard footer {
        color: var(--mobile-text-muted);
        font-family: var(--font-body);
        font-style: italic;
    }

    /* ============================================
           Spinner - Gold accent
           ============================================ */
    .viewMobileCard .spinner-border {
        color: var(--medieval-gold);
    }

    /* ============================================
           Badge - Wax Seal Style
           ============================================ */
    .viewMobileCard .badge {
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 4px;
        font-family: var(--font-display);
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-size: 0.7rem;
    }

    .viewMobileCard .badge.bg-success {
        background: linear-gradient(180deg, var(--mobile-success), color-mix(in srgb, var(--mobile-success) 70%, black)) !important;
    }

    .viewMobileCard .badge.bg-danger {
        background: linear-gradient(180deg, var(--mobile-danger), color-mix(in srgb, var(--mobile-danger) 70%, black)) !important;
    }

    .viewMobileCard .badge.bg-warning {
        background: linear-gradient(180deg, var(--medieval-gold), var(--medieval-bronze)) !important;
        color: var(--medieval-ink) !important;
    }

    .viewMobileCard .badge.bg-info {
        background: linear-gradient(180deg, var(--mobile-info), color-mix(in srgb, var(--mobile-info) 70%, black)) !important;
    }

    .viewMobileCard .badge.bg-secondary {
        background: linear-gradient(180deg, var(--medieval-stone), var(--medieval-stone-dark)) !important;
    }

    /* ============================================
           Decorative Elements
           ============================================ */
    /* Divider with flourish */
    .viewMobileCard hr {
        border: 0;
        height: 1px;
        background: linear-gradient(
            90deg, 
            transparent, 
            rgba(139, 105, 20, 0.3) 20%, 
            rgba(139, 105, 20, 0.3) 80%, 
            transparent
        );
        margin: 1.5rem 0;
    }

    /* Link styling */
    .viewMobileCard a:not(.btn):not(.nav-link):not(.mobile-menu-item) {
        color: var(--section-rsvps);
        text-decoration: none;
        border-bottom: 1px dotted var(--section-rsvps);
    }

    .viewMobileCard a:not(.btn):not(.nav-link):not(.mobile-menu-item):hover {
        color: var(--medieval-bronze);
        border-bottom-color: var(--medieval-bronze);
    }

    /* List styling */
    .viewMobileCard .list-group-item {
        background: var(--mobile-card-bg);
        border-color: rgba(139, 105, 20, 0.12);
        font-family: var(--font-body);
    }

    .viewMobileCard .list-group-item:first-child {
        border-radius: 4px 4px 0 0;
    }

    .viewMobileCard .list-group-item:last-child {
        border-radius: 0 0 4px 4px;
    }
    
    /* Utility: Add padding for pages with fixed bottom navigation */
    .has-fixed-bottom-nav {
        padding-bottom: 90px !important;
    }
    
    /* Style for fixed bottom navigation bars */
    .fixed-bottom {
        background: var(--mobile-card-bg, #fffef9) !important;
        border-top: 2px solid var(--current-section-color, var(--medieval-gold)) !important;
        box-shadow: 0 -4px 12px rgba(44, 24, 16, 0.1) !important;
    }
    </style>

    <!-- JavaScript -->
    <?= $this->AssetMix->script('manifest') ?>
    <?= $this->AssetMix->script('core') ?>
    <?= $this->AssetMix->script('controllers') ?>
    <?= $this->AssetMix->script('index') ?>
    <?= $this->fetch('script') ?>
</head>

<body class="viewMobileCard"<?php if ($mobileSection): ?> data-section="<?= h($mobileSection) ?>"<?php endif; ?>>
    <?= $this->Flash->render() ?>
    <?php
    // Determine if this is the auth card page and build auth card URL
    $currentController = $this->request->getParam('controller');
    $currentAction = $this->request->getParam('action');
    $currentPlugin = $this->request->getParam('plugin');
    $isAuthCard = ($currentController === 'Members' && $currentAction === 'viewMobileCard' && $currentPlugin === null);

    // My RSVPs also works offline (uses cached data)
    $isMyRsvps = ($currentController === 'GatheringAttendances' && $currentAction === 'myRsvps' && $currentPlugin === null);

    // Pages that don't need the offline overlay
    $skipOfflineOverlay = $isAuthCard || $isMyRsvps;

    $authCardUrl = ['controller' => 'Members', 'action' => 'viewMobileCard', 'plugin' => null];
    if ($currentUser && $currentUser->mobile_card_token) {
        $authCardUrl[] = $currentUser->mobile_card_token;
    }
    $authCardUrlBuilt = $this->Url->build($authCardUrl);
    ?>
    <div data-controller="member-mobile-card-pwa<?= isset($cardUrl) ? ' member-mobile-card-profile' : '' ?><?= !$skipOfflineOverlay ? ' mobile-offline-overlay' : '' ?>"
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
            if (!$isAuthCard) {
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
                class="bg-danger" title="Offline"></span>
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