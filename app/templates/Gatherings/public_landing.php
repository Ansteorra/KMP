<?php

/**
 * Public Landing Page for Gathering
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $scheduleByDate
 * @var int $durationDays
 */

use Cake\I18n\Date;

// Set page title
$this->assign('title', h($gathering->name));
?>

<!-- Add custom fonts and CSS for public landing page styling -->
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

body {
    font-family: var(--font-sans);
    background: var(--stone-50);
    margin: 0;
    padding: 0;
}

/* Hero Banner with Medieval Styling */
.hero-banner {
    position: relative;
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-royal) 100%);
    color: var(--stone-50);
    padding: var(--space-2xl) var(--space-md);
    margin-bottom: var(--space-xl);
    border-bottom: 4px solid var(--medieval-gold);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.hero-banner::before {
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

.hero-banner-ornament {
    position: absolute;
    font-size: 2.5rem;
    color: var(--medieval-gold);
    opacity: 0.4;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.hero-banner-ornament-left {
    top: 1rem;
    left: 1rem;
}

.hero-banner-ornament-right {
    top: 1rem;
    right: 1rem;
}

.hero-banner-content {
    position: relative;
    text-align: center;
    max-width: 900px;
    margin: 0 auto;
}

.event-type-badge {
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

.event-title {
    font-family: var(--font-serif);
    font-size: clamp(1.75rem, 5vw, 2.75rem);
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: var(--space-md);
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.event-quick-meta {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-md);
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 500;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    background: rgba(255, 255, 255, 0.1);
    padding: var(--space-sm) var(--space-md);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.meta-item i {
    font-size: 1rem;
    color: var(--medieval-gold);
}

/* Container */
.event-container {
    max-width: 1100px;
    margin: 0 auto var(--space-xl) auto;
    padding: 0 var(--space-md);
}

/* Info Cards - Medieval Style */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-lg);
}

.info-card-medieval {
    background: linear-gradient(to bottom, var(--stone-50) 0%, var(--stone-100) 100%);
    border: 2px solid var(--stone-300);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.info-card-medieval:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.card-header-medieval {
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

.card-header-medieval i {
    font-size: 1rem;
    color: var(--medieval-gold);
}

.card-body-medieval {
    padding: var(--space-md);
    color: var(--stone-800);
    font-size: 0.875rem;
    line-height: 1.6;
}

.steward-entry,
.staff-entry {
    margin-bottom: var(--space-md);
}

.steward-entry:last-child,
.staff-entry:last-child {
    margin-bottom: 0;
}

.contact-detail {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    margin-top: var(--space-xs);
    font-size: 0.813rem;
    color: var(--stone-600);
}

.contact-detail i {
    color: var(--medieval-crimson);
}

.contact-detail a {
    color: var(--medieval-crimson);
    text-decoration: none;
}

.contact-detail a:hover {
    text-decoration: underline;
}

.contact-note {
    margin-top: var(--space-xs);
    font-size: 0.75rem;
    font-style: italic;
    color: var(--stone-600);
}

.staff-contacts {
    display: inline-flex;
    gap: var(--space-sm);
    margin-left: var(--space-sm);
}

.staff-contacts a {
    color: var(--medieval-crimson);
    font-size: 0.875rem;
}

/* Status Cards */
.status-happening .card-header-medieval {
    background: linear-gradient(135deg, var(--medieval-forest) 0%, #1e4620 100%);
}

.status-badge-active {
    background: var(--medieval-forest);
    color: white;
    padding: var(--space-sm) var(--space-md);
    border-radius: 6px;
    font-weight: 700;
    text-align: center;
}

.countdown-display {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--medieval-crimson);
    text-align: center;
}

.activity-count {
    font-size: 2rem;
    font-weight: 700;
    color: var(--medieval-crimson);
    text-align: center;
}

/* Section Titles */
.section-medieval {
    background: linear-gradient(to bottom, var(--stone-50) 0%, white 100%);
    border: 2px solid var(--stone-300);
    border-radius: 8px;
    padding: var(--space-xl) var(--space-lg);
    margin-bottom: var(--space-xl);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.section-title-medieval {
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

.title-ornament {
    color: var(--medieval-gold);
    font-size: 1rem;
}

/* Content Scroll */
.content-scroll {
    max-height: 500px;
    overflow-y: auto;
    padding-right: var(--space-sm);
    line-height: 1.8;
    color: var(--stone-700);
}

.content-scroll::-webkit-scrollbar {
    width: 8px;
}

.content-scroll::-webkit-scrollbar-track {
    background: var(--stone-200);
    border-radius: 4px;
}

.content-scroll::-webkit-scrollbar-thumb {
    background: var(--medieval-gold);
    border-radius: 4px;
}

/* Schedule */
.schedule-day-medieval {
    margin-bottom: var(--space-xl);
}

.schedule-day-header {
    font-family: var(--font-serif);
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--medieval-crimson);
    margin-bottom: var(--space-md);
    padding-bottom: var(--space-sm);
    border-bottom: 2px solid var(--medieval-gold);
}

.schedule-events {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.schedule-event-item {
    display: flex;
    gap: var(--space-md);
    background: white;
    border-left: 4px solid var(--medieval-gold);
    padding: var(--space-md);
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.schedule-time-badge {
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

.schedule-time-badge i {
    display: block;
    margin-bottom: 0.25rem;
}

.schedule-event-content h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--stone-800);
    margin-bottom: var(--space-xs);
}

.schedule-event-desc {
    font-size: 0.875rem;
    color: var(--stone-600);
    line-height: 1.6;
    margin-bottom: var(--space-xs);
}

.activity-tag {
    display: inline-block;
    background: var(--stone-200);
    color: var(--stone-700);
    padding: 0.25rem 0.625rem;
    border-radius: 12px;
    font-size: 0.688rem;
    font-weight: 600;
}

.activity-tag i {
    font-size: 0.625rem;
}

/* Activities Compact */
.activities-compact {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.activity-item-compact {
    display: flex;
    gap: var(--space-md);
    background: white;
    border-left: 4px solid var(--medieval-gold);
    padding: var(--space-md);
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.activity-icon-compact {
    flex-shrink: 0;
    font-size: 1.5rem;
    color: var(--medieval-gold);
}

.activity-details-compact h4 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--stone-800);
    margin-bottom: var(--space-xs);
}

.activity-details-compact p {
    font-size: 0.875rem;
    color: var(--stone-600);
    line-height: 1.6;
    margin: 0;
}

/* Location */
.location-display {
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

.location-icon {
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

.location-address {
    text-align: center;
    font-size: 1rem;
    color: var(--stone-700);
    line-height: 1.8;
    margin: 0;
}

.location-actions {
    display: flex;
    gap: var(--space-md);
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: var(--space-lg);
}

.map-container {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Medieval Buttons */
.btn-medieval {
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

.btn-medieval-primary {
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-crimson-dark) 100%);
    color: white;
    border-color: var(--medieval-gold);
}

.btn-medieval-primary:hover {
    background: linear-gradient(135deg, var(--medieval-crimson-dark) 0%, var(--medieval-crimson) 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.btn-medieval-secondary {
    background: var(--stone-200);
    color: var(--stone-800);
    border-color: var(--stone-400);
}

.btn-medieval-secondary:hover {
    background: var(--stone-300);
}

.btn-medieval-outline {
    background: transparent;
    color: var(--stone-50);
    border-color: rgba(255, 255, 255, 0.5);
}

.btn-medieval-outline:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: white;
}

.btn-medieval-cta {
    background: linear-gradient(135deg, var(--medieval-gold) 0%, var(--medieval-gold-dark) 100%);
    color: var(--medieval-ink);
    border-color: white;
    font-size: 1rem;
    padding: var(--space-md) var(--space-xl);
}

.btn-medieval-cta:hover {
    background: linear-gradient(135deg, var(--medieval-gold-dark) 0%, var(--medieval-gold) 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
}

/* Dropdown Menu */
.dropdown-menu-medieval {
    background: var(--stone-50);
    border: 2px solid var(--stone-300);
    border-radius: 6px;
    padding: 0.5rem 0;
}

.dropdown-menu-medieval .dropdown-item {
    color: var(--stone-800);
    padding: 0.5rem 1rem;
}

.dropdown-menu-medieval .dropdown-item:hover {
    background: var(--stone-200);
    color: var(--medieval-crimson);
}

/* CTA Section */
.cta-medieval {
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-royal) 100%);
    color: var(--stone-50);
    padding: var(--space-2xl) var(--space-lg);
    border-radius: 8px;
    text-align: center;
    position: relative;
    border: 3px solid var(--medieval-gold);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.cta-ornament-top,
.cta-ornament-bottom {
    color: var(--medieval-gold);
    font-size: 1.25rem;
    letter-spacing: 0.5rem;
    opacity: 0.6;
}

.cta-ornament-top {
    margin-bottom: var(--space-md);
}

.cta-ornament-bottom {
    margin-top: var(--space-md);
}

.cta-medieval h2 {
    font-family: var(--font-serif);
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 700;
    margin-bottom: var(--space-md);
}

.cta-medieval p {
    font-size: 1rem;
    margin-bottom: var(--space-lg);
    opacity: 0.95;
}

.cta-buttons {
    display: flex;
    gap: var(--space-md);
    justify-content: center;
    flex-wrap: wrap;
}

/* Footer */
.footer {
    background: var(--stone-900);
    color: var(--stone-400);
    padding: var(--space-2xl) var(--space-lg);
    text-align: center;
    margin-top: var(--space-2xl);
}

.footer p {
    font-size: 0.875rem;
    margin: 0;
}

.footer .container {
    max-width: 1100px;
    margin: 0 auto;
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

.fade-in {
    animation: fadeIn 0.5s ease-out;
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-banner {
        padding: var(--space-xl) var(--space-md);
    }

    .hero-banner-ornament {
        font-size: 1.5rem;
    }

    .event-quick-meta {
        flex-direction: column;
        gap: var(--space-sm);
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    .schedule-event-item {
        flex-direction: column;
    }

    .schedule-time-badge {
        width: 100%;
    }

    .location-actions {
        flex-direction: column;
    }

    .btn-medieval {
        width: 100%;
        justify-content: center;
    }

    .cta-buttons {
        flex-direction: column;
    }
}
</style>

<?php
// Show mobile back button when coming from mobile app
$fromMobile = $this->request->getQuery('from') === 'mobile';
if ($fromMobile): ?>
<div class="mobile-back-bar" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: linear-gradient(135deg, var(--medieval-crimson) 0%, var(--medieval-crimson-dark) 100%);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
">
    <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'mobileCalendar']) ?>" 
       style="
           color: white;
           text-decoration: none;
           display: flex;
           align-items: center;
           font-family: var(--font-sans);
           font-weight: 500;
       ">
        <i class="bi bi-arrow-left me-2" style="font-size: 1.25rem;"></i>
        Back to Events
    </a>
</div>
<div style="height: 52px;"></div>
<?php endif; ?>

<?= $this->element('gatherings/public_content', [
    'gathering' => $gathering,
    'scheduleByDate' => $scheduleByDate,
    'durationDays' => $durationDays,
    'user' => $user ?? null,
    'userAttendance' => $userAttendance ?? null,
    'kingdomAttendances' => $kingdomAttendances ?? []
]) ?>

<footer class="footer">
    <div class="container">
        <p>
            Hosted by <?= h($gathering->branch->name) ?>
        </p>
        <p style="margin-top: var(--space-md); font-size: 0.75rem; opacity: 0.7;">
            © <?= date('Y') ?> Kingdom Management Portal
        </p>
    </div>
</footer>
