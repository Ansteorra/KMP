<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \App\Model\Entity\GatheringAttendance|null $userAttendance
 * @var bool $showPublicView
 */

// Get the authenticated user
$user = $this->request->getAttribute('identity');
?>
<?php
$this->extend('/layout/TwitterBootstrap/view_record');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': View Gathering - ' . $gathering->name;
$this->KMP->endBlock();

// Add custom fonts and CSS for public landing page styling
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap"
    rel="stylesheet">

<style>
:root {
    /* Medieval-Inspired Color Palette */
    --medieval-gold: #D4AF37;
    --medieval-gold-dark: #B8941F;
    --medieval-crimson: #8B0000;
    --medieval-crimson-dark: #660000;
    --medieval-royal: #4B0082;
    --medieval-forest: #2C5F2D;
    --medieval-parchment: #F4E8D0;
    --medieval-ink: #2B2B2B;

    /* Neutrals */
    --stone-900: #1C1917;
    --stone-800: #292524;
    --stone-700: #44403C;
    --stone-600: #57534E;
    --stone-500: #78716C;
    --stone-400: #A8A29E;
    --stone-300: #D6D3D1;
    --stone-200: #E7E5E4;
    --stone-100: #F5F5F4;
    --stone-50: #FAFAF9;

    /* Typography */
    --font-serif: 'Playfair Display', Georgia, serif;
    --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;

    /* Spacing */
    --space-xs: 0.375rem;
    --space-sm: 0.625rem;
    --space-md: 1rem;
    --space-lg: 1.5rem;
    --space-xl: 2rem;
    --space-2xl: 2.5rem;
    --space-3xl: 3rem;
}

/* Scoped styles for gathering public view */
.table-responsive:has(.gathering-public-content) {
    overflow: visible !important;
}

.table-responsive:has(.gathering-public-content) table {
    display: block !important;
    border: none !important;
}

.table-responsive:has(.gathering-public-content) tbody {
    display: block !important;
}

/* Hero Banner with Medieval Styling */
.gathering-public-content .hero-banner {
    position: relative;
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-royal) 100%);
    color: var(--stone-50);
    padding: var(--space-2xl) var(--space-md);
    margin: 0 -15px var(--space-xl) -15px;
    border-bottom: 4px solid var(--medieval-gold);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.gathering-public-content .hero-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image:
        repeating-linear-gradient(90deg, rgba(212, 175, 55, 0.03) 0px, transparent 1px, transparent 20px),
        repeating-linear-gradient(0deg, rgba(212, 175, 55, 0.03) 0px, transparent 1px, transparent 20px);
    pointer-events: none;
}

.gathering-public-content .hero-banner-ornament {
    position: absolute;
    font-size: 2.5rem;
    color: var(--medieval-gold);
    opacity: 0.4;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.gathering-public-content .hero-banner-ornament-left {
    top: 1rem;
    left: 1rem;
}

.gathering-public-content .hero-banner-ornament-right {
    top: 1rem;
    right: 1rem;
}

.gathering-public-content .hero-banner-content {
    position: relative;
    text-align: center;
    max-width: 900px;
    margin: 0 auto;
}

.gathering-public-content .event-type-badge {
    display: inline-block;
    background: rgba(212, 175, 55, 0.2);
    border: 2px solid var(--medieval-gold);
    color: var(--medieval-gold);
    padding: var(--space-xs) var(--space-lg);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: var(--space-md);
}

.gathering-public-content .event-title {
    font-family: var(--font-serif);
    font-size: clamp(1.75rem, 5vw, 2.75rem);
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: var(--space-md);
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.gathering-public-content .event-quick-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-md);
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 500;
}

.gathering-public-content .meta-item {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    background: rgba(255, 255, 255, 0.1);
    padding: var(--space-sm) var(--space-md);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.gathering-public-content .meta-item i {
    font-size: 1rem;
    color: var(--medieval-gold);
}

/* Container */
.gathering-public-content .event-container {
    max-width: 1100px;
    margin: 0 auto var(--space-xl) auto;
    padding: 0 var(--space-md);
}

/* Info Cards - Medieval Style */
.gathering-public-content .info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-lg);
}

.gathering-public-content .info-card-medieval {
    background: linear-gradient(to bottom, var(--stone-50) 0%, var(--stone-100) 100%);
    border: 2px solid var(--stone-300);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.gathering-public-content .info-card-medieval:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.gathering-public-content .card-header-medieval {
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-crimson-dark) 100%);
    color: var(--stone-50);
    padding: var(--space-sm) var(--space-md);
    font-weight: 700;
    font-size: 0.813rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    border-bottom: 2px solid var(--medieval-gold);
}

.gathering-public-content .card-header-medieval i {
    font-size: 1rem;
    color: var(--medieval-gold);
}

.gathering-public-content .card-body-medieval {
    padding: var(--space-md);
    color: var(--stone-800);
    font-size: 0.875rem;
    line-height: 1.6;
}

.gathering-public-content .steward-entry,
.gathering-public-content .staff-entry {
    margin-bottom: var(--space-md);
}

.gathering-public-content .steward-entry:last-child,
.gathering-public-content .staff-entry:last-child {
    margin-bottom: 0;
}

.gathering-public-content .contact-detail {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    margin-top: var(--space-xs);
    font-size: 0.813rem;
    color: var(--stone-600);
}

.gathering-public-content .contact-detail i {
    color: var(--medieval-crimson);
}

.gathering-public-content .contact-detail a {
    color: var(--medieval-crimson);
    text-decoration: none;
}

.gathering-public-content .contact-detail a:hover {
    text-decoration: underline;
}

.gathering-public-content .contact-note {
    margin-top: var(--space-xs);
    font-size: 0.75rem;
    font-style: italic;
    color: var(--stone-600);
}

.gathering-public-content .staff-contacts {
    display: inline-flex;
    gap: var(--space-sm);
    margin-left: var(--space-sm);
}

.gathering-public-content .staff-contacts a {
    color: var(--medieval-crimson);
    font-size: 0.875rem;
}

/* Status Cards */
.gathering-public-content .status-happening .card-header-medieval {
    background: linear-gradient(135deg, var(--medieval-forest) 0%, #1e4620 100%);
}

.gathering-public-content .status-badge-active {
    background: var(--medieval-forest);
    color: white;
    padding: var(--space-sm) var(--space-md);
    border-radius: 6px;
    font-weight: 700;
    text-align: center;
}

.gathering-public-content .countdown-display {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--medieval-crimson);
    text-align: center;
}

.gathering-public-content .activity-count {
    font-size: 2rem;
    font-weight: 700;
    color: var(--medieval-crimson);
    text-align: center;
}

/* Section Titles */
.gathering-public-content .section-medieval {
    background: linear-gradient(to bottom, var(--stone-50) 0%, white 100%);
    border: 2px solid var(--stone-300);
    border-radius: 8px;
    padding: var(--space-xl) var(--space-lg);
    margin-bottom: var(--space-xl);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.gathering-public-content .section-title-medieval {
    font-family: var(--font-serif);
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 700;
    color: var(--medieval-crimson);
    text-align: center;
    margin-bottom: var(--space-lg);
    padding-bottom: var(--space-md);
    border-bottom: 3px solid var(--medieval-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-md);
}

.gathering-public-content .title-ornament {
    color: var(--medieval-gold);
    font-size: 1rem;
}

/* Content Scroll */
.gathering-public-content .content-scroll {
    max-height: 500px;
    overflow-y: auto;
    padding-right: var(--space-sm);
    line-height: 1.8;
    color: var(--stone-700);
}

.gathering-public-content .content-scroll::-webkit-scrollbar {
    width: 8px;
}

.gathering-public-content .content-scroll::-webkit-scrollbar-track {
    background: var(--stone-200);
    border-radius: 4px;
}

.gathering-public-content .content-scroll::-webkit-scrollbar-thumb {
    background: var(--medieval-gold);
    border-radius: 4px;
}

/* Schedule */
.gathering-public-content .schedule-day-medieval {
    margin-bottom: var(--space-xl);
}

.gathering-public-content .schedule-day-header {
    font-family: var(--font-serif);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--medieval-crimson);
    margin-bottom: var(--space-md);
    padding-bottom: var(--space-sm);
    border-bottom: 2px solid var(--medieval-gold);
}

.gathering-public-content .schedule-events {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.gathering-public-content .schedule-event-item {
    display: flex;
    gap: var(--space-md);
    background: white;
    border-left: 4px solid var(--medieval-gold);
    padding: var(--space-md);
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.gathering-public-content .schedule-time-badge {
    flex-shrink: 0;
    background: var(--medieval-crimson);
    color: white;
    padding: var(--space-sm) var(--space-md);
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    text-align: center;
    min-width: 100px;
    height: fit-content;
}

.gathering-public-content .schedule-time-badge i {
    display: block;
    margin-bottom: 0.25rem;
}

.gathering-public-content .schedule-event-content h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--stone-800);
    margin-bottom: var(--space-xs);
}

.gathering-public-content .schedule-event-desc {
    font-size: 0.875rem;
    color: var(--stone-600);
    line-height: 1.6;
    margin-bottom: var(--space-xs);
}

.gathering-public-content .activity-tag {
    display: inline-block;
    background: var(--stone-200);
    color: var(--stone-700);
    padding: 0.25rem 0.625rem;
    border-radius: 12px;
    font-size: 0.688rem;
    font-weight: 600;
}

.gathering-public-content .activity-tag i {
    font-size: 0.625rem;
}

/* Activities Compact */
.gathering-public-content .activities-compact {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.gathering-public-content .activity-item-compact {
    display: flex;
    gap: var(--space-md);
    background: white;
    border-left: 4px solid var(--medieval-gold);
    padding: var(--space-md);
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.gathering-public-content .activity-icon-compact {
    flex-shrink: 0;
    font-size: 1.5rem;
    color: var(--medieval-gold);
}

.gathering-public-content .activity-details-compact h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--stone-800);
    margin-bottom: var(--space-xs);
}

.gathering-public-content .activity-details-compact p {
    font-size: 0.875rem;
    color: var(--stone-600);
    line-height: 1.6;
    margin: 0;
}

/* Location */
.gathering-public-content .location-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-md);
    background: white;
    padding: var(--space-lg);
    border-radius: 8px;
    margin-bottom: var(--space-lg);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.gathering-public-content .location-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-crimson-dark) 100%);
    color: var(--medieval-gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.gathering-public-content .location-address {
    text-align: center;
    font-size: 1rem;
    color: var(--stone-700);
    line-height: 1.8;
    margin: 0;
}

.gathering-public-content .location-actions {
    display: flex;
    gap: var(--space-md);
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: var(--space-lg);
}

.gathering-public-content .map-container {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Medieval Buttons */
.gathering-public-content .btn-medieval {
    display: inline-flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) var(--space-lg);
    border-radius: 6px;
    font-weight: 700;
    font-size: 0.875rem;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    border: 2px solid transparent;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.gathering-public-content .btn-medieval-primary {
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-crimson-dark) 100%);
    color: white;
    border-color: var(--medieval-gold);
}

.gathering-public-content .btn-medieval-primary:hover {
    background: linear-gradient(135deg, var(--medieval-crimson-dark) 0%, var(--medieval-crimson) 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.gathering-public-content .btn-medieval-secondary {
    background: var(--stone-200);
    color: var(--stone-800);
    border-color: var(--stone-400);
}

.gathering-public-content .btn-medieval-secondary:hover {
    background: var(--stone-300);
}

.gathering-public-content .btn-medieval-outline {
    background: transparent;
    color: var(--stone-50);
    border-color: rgba(255, 255, 255, 0.5);
}

.gathering-public-content .btn-medieval-outline:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: white;
}

.gathering-public-content .btn-medieval-cta {
    background: linear-gradient(135deg, var(--medieval-gold) 0%, var(--medieval-gold-dark) 100%);
    color: var(--medieval-ink);
    border-color: white;
    font-size: 1rem;
    padding: var(--space-md) var(--space-xl);
}

.gathering-public-content .btn-medieval-cta:hover {
    background: linear-gradient(135deg, var(--medieval-gold-dark) 0%, var(--medieval-gold) 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
}

/* Dropdown Menu */
.gathering-public-content .dropdown-menu-medieval {
    background: var(--stone-50);
    border: 2px solid var(--stone-300);
    border-radius: 6px;
    padding: 0.5rem 0;
}

.gathering-public-content .dropdown-menu-medieval .dropdown-item {
    color: var(--stone-800);
    padding: 0.5rem 1rem;
}

.gathering-public-content .dropdown-menu-medieval .dropdown-item:hover {
    background: var(--stone-200);
    color: var(--medieval-crimson);
}

/* CTA Section */
.gathering-public-content .cta-medieval {
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-royal) 100%);
    color: var(--stone-50);
    padding: var(--space-2xl) var(--space-lg);
    border-radius: 8px;
    text-align: center;
    position: relative;
    border: 3px solid var(--medieval-gold);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.gathering-public-content .cta-ornament-top,
.gathering-public-content .cta-ornament-bottom {
    color: var(--medieval-gold);
    font-size: 1.25rem;
    letter-spacing: 0.5rem;
    opacity: 0.6;
}

.gathering-public-content .cta-ornament-top {
    margin-bottom: var(--space-md);
}

.gathering-public-content .cta-ornament-bottom {
    margin-top: var(--space-md);
}

.gathering-public-content .cta-medieval h2 {
    font-family: var(--font-serif);
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 700;
    margin-bottom: var(--space-md);
}

.gathering-public-content .cta-medieval p {
    font-size: 1rem;
    margin-bottom: var(--space-lg);
    opacity: 0.95;
}

.gathering-public-content .cta-buttons {
    display: flex;
    gap: var(--space-md);
    justify-content: center;
    flex-wrap: wrap;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.gathering-public-content .fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .gathering-public-content .hero-banner {
        padding: var(--space-xl) var(--space-md);
    }

    .gathering-public-content .hero-banner-ornament {
        font-size: 1.5rem;
    }

    .gathering-public-content .event-quick-meta {
        flex-direction: column;
        gap: var(--space-sm);
    }

    .gathering-public-content .info-grid {
        grid-template-columns: 1fr;
    }

    .gathering-public-content .schedule-event-item {
        flex-direction: column;
    }

    .gathering-public-content .schedule-time-badge {
        width: 100%;
    }

    .gathering-public-content .location-actions {
        flex-direction: column;
    }

    .gathering-public-content .btn-medieval {
        width: 100%;
        justify-content: center;
    }

    .gathering-public-content .cta-buttons {
        flex-direction: column;
    }
}
</style>

<?php

echo $this->KMP->startBlock('pageTitle') ?>
<?= h($gathering->name) ?>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordActions') ?>
<!-- Build public landing URL -->
<?php
$publicLandingUrl = $this->Url->build([
    'controller' => 'Gatherings',
    'action' => 'public-landing',
    $gathering->public_id
], ['fullBase' => true]);
?>

<a href="<?= $publicLandingUrl ?>" target="_blank" class="btn btn-outline-primary btn-sm">
    <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
</a>
<a href="<?= $this->Url->build(['action' => 'index']) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left"></i> Back to List
</a>
<?php $this->KMP->endBlock() ?>

<?= $this->KMP->startBlock('recordDetails') ?>

<tr>
    <td colspan="100" style="padding: 0; border: none;">
        <div class="gathering-public-content">

            <!-- Render the public landing page content inline -->
            <?= $this->element('gatherings/public_content', [
                'gathering' => $gathering,
                'user' => $user ?? null,
                'userAttendance' => $userAttendance ?? null,
                'kingdomAttendances' => $kingdomAttendances ?? []
            ]) ?>



        </div>
    </td>
</tr>
<?php $this->KMP->endBlock() ?>
