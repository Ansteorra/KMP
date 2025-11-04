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
        /* Modern Color Palette */
        --color-primary: #2563eb;
        --color-primary-dark: #1e40af;
        --color-secondary: #7c3aed;
        --color-accent: #f59e0b;
        --color-success: #10b981;
        --color-danger: #ef4444;

        /* Neutrals */
        --color-dark: #1e293b;
        --color-gray-900: #0f172a;
        --color-gray-800: #1e293b;
        --color-gray-700: #334155;
        --color-gray-600: #475569;
        --color-gray-500: #64748b;
        --color-gray-400: #94a3b8;
        --color-gray-300: #cbd5e1;
        --color-gray-200: #e2e8f0;
        --color-gray-100: #f1f5f9;
        --color-gray-50: #f8fafc;
        --color-white: #ffffff;

        /* Typography */
        --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --font-display: 'Playfair Display', Georgia, serif;

        /* Spacing */
        --space-xs: 0.25rem;
        --space-sm: 0.5rem;
        --space-md: 1rem;
        --space-lg: 1.5rem;
        --space-xl: 2rem;
        --space-2xl: 3rem;
        --space-3xl: 4rem;

        /* Shadows */
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);

        /* Border Radius */
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        --radius-2xl: 1.5rem;
        --radius-full: 9999px;
    }

    /* Scoped styles for gathering public view - hide the default table styling */
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

    .gathering-public-content .hero {
        position: relative;
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
        color: var(--color-white);
        padding: var(--space-3xl) var(--space-lg);
        min-height: 40vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin: 0 -15px;
        border-radius: var(--radius-xl);
    }

    .gathering-public-content .hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background:
            radial-gradient(circle at 20% 50%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
        pointer-events: none;
    }

    .gathering-public-content .hero-content {
        position: relative;
        max-width: 900px;
        text-align: center;
        z-index: 1;
    }

    .gathering-public-content .event-badge {
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        padding: var(--space-sm) var(--space-lg);
        border-radius: var(--radius-full);
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: var(--space-lg);
    }

    .gathering-public-content .hero h1 {
        font-family: var(--font-display);
        font-size: clamp(2rem, 5vw, 3.5rem);
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: var(--space-lg);
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .gathering-public-content .hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-lg);
        justify-content: center;
        font-size: 1.125rem;
        margin-top: var(--space-xl);
    }

    .gathering-public-content .hero-meta-item {
        display: flex;
        align-items: center;
        gap: var(--space-sm);
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        padding: var(--space-md) var(--space-lg);
        border-radius: var(--radius-lg);
    }

    .gathering-public-content .hero-meta-item i {
        font-size: 1.5rem;
    }

    .gathering-public-content .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 var(--space-lg);
    }

    .gathering-public-content .section {
        padding: var(--space-3xl) 0;
    }

    .gathering-public-content .section-header {
        text-align: center;
        margin-bottom: var(--space-2xl);
    }

    .gathering-public-content .section-title {
        font-family: var(--font-display);
        font-size: clamp(1.75rem, 4vw, 2.5rem);
        font-weight: 700;
        color: var(--color-gray-900);
        margin-bottom: var(--space-md);
    }

    .gathering-public-content .section-subtitle {
        font-size: 1.125rem;
        color: var(--color-gray-600);
        max-width: 600px;
        margin: 0 auto;
    }

    .gathering-public-content .quick-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: var(--space-lg);
        margin: var(--space-2xl) 0;
    }

    .gathering-public-content .info-card {
        background: var(--color-white);
        border-radius: var(--radius-lg);
        padding: var(--space-xl);
        box-shadow: var(--shadow-md);
        display: flex;
        gap: var(--space-lg);
        align-items: flex-start;
    }

    .gathering-public-content .info-icon {
        flex-shrink: 0;
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        color: var(--color-white);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .gathering-public-content .info-content h3 {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--color-gray-600);
        margin-bottom: var(--space-xs);
    }

    .gathering-public-content .info-content p {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-gray-900);
    }

    .gathering-public-content .schedule-day {
        margin-bottom: var(--space-2xl);
    }

    .gathering-public-content .schedule-day-header {
        font-family: var(--font-display);
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-gray-900);
        margin-bottom: var(--space-lg);
        padding-bottom: var(--space-md);
        border-bottom: 3px solid var(--color-primary);
    }

    .gathering-public-content .schedule-timeline {
        position: relative;
        padding-left: var(--space-2xl);
    }

    .gathering-public-content .schedule-timeline::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--color-gray-300);
    }

    .gathering-public-content .schedule-item {
        position: relative;
        margin-bottom: var(--space-xl);
        padding-left: var(--space-lg);
    }

    .gathering-public-content .schedule-item::before {
        content: '';
        position: absolute;
        left: calc(-1 * var(--space-2xl) - 6px);
        top: 8px;
        width: 14px;
        height: 14px;
        background: var(--color-primary);
        border: 3px solid var(--color-white);
        border-radius: var(--radius-full);
        box-shadow: 0 0 0 2px var(--color-primary);
    }

    .gathering-public-content .schedule-time {
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--color-primary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: var(--space-xs);
    }

    .gathering-public-content .schedule-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-gray-900);
        margin-bottom: var(--space-xs);
    }

    .gathering-public-content .schedule-description {
        color: var(--color-gray-600);
        line-height: 1.6;
    }

    .gathering-public-content .schedule-activity-tag {
        display: inline-block;
        background: var(--color-gray-100);
        color: var(--color-gray-700);
        padding: var(--space-xs) var(--space-md);
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        margin-top: var(--space-sm);
    }

    .gathering-public-content .activities-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: var(--space-lg);
    }

    .gathering-public-content .activity-card {
        background: var(--color-white);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        box-shadow: var(--shadow-sm);
        border-left: 4px solid var(--color-primary);
        transition: all 0.2s;
    }

    .gathering-public-content .activity-card:hover {
        box-shadow: var(--shadow-md);
        transform: translateX(4px);
    }

    .gathering-public-content .activity-name {
        font-weight: 600;
        color: var(--color-gray-900);
        margin-bottom: var(--space-xs);
    }

    .gathering-public-content .activity-description {
        font-size: 0.875rem;
        color: var(--color-gray-600);
    }

    .gathering-public-content .location-address {
        background: var(--color-white);
        padding: var(--space-xl);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-md);
        margin-top: var(--space-lg);
        text-align: center;
    }

    .gathering-public-content .location-address i {
        font-size: 2rem;
        color: var(--color-primary);
        margin-bottom: var(--space-md);
    }

    .gathering-public-content .location-address p {
        font-size: 1.125rem;
        color: var(--color-gray-800);
        line-height: 1.8;
    }

    .gathering-public-content .cta-section {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
        color: var(--color-white);
        padding: var(--space-3xl) var(--space-lg);
        text-align: center;
        border-radius: var(--radius-2xl);
        margin: var(--space-2xl) 0;
    }

    .gathering-public-content .cta-section h2 {
        font-family: var(--font-display);
        font-size: clamp(1.75rem, 4vw, 2.5rem);
        font-weight: 700;
        margin-bottom: var(--space-md);
    }

    .gathering-public-content .cta-section p {
        font-size: 1.125rem;
        margin-bottom: var(--space-xl);
        opacity: 0.95;
    }

    .gathering-public-content .btn {
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        padding: var(--space-md) var(--space-2xl);
        border-radius: var(--radius-full);
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
        cursor: pointer;
        border: none;
        font-size: 1rem;
    }

    .gathering-public-content .btn-primary {
        background: var(--color-white);
        color: var(--color-primary);
        box-shadow: var(--shadow-lg);
    }

    .gathering-public-content .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-xl);
    }

    .gathering-public-content .btn-outline {
        background: transparent;
        color: var(--color-white);
        border: 2px solid rgba(255, 255, 255, 0.5);
    }

    .gathering-public-content .btn-outline:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--color-white);
    }

    .gathering-public-content .footer {
        background: var(--color-gray-900);
        color: var(--color-gray-400);
        padding: var(--space-2xl) var(--space-lg);
        text-align: center;
        border-radius: var(--radius-lg);
        margin-top: var(--space-2xl);
    }

    .gathering-public-content .footer p {
        font-size: 0.875rem;
    }

    .gathering-public-content .description-content {
        background: var(--color-white);
        padding: var(--space-2xl);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-md);
        line-height: 1.8;
        color: var(--color-gray-700);
    }

    .gathering-public-content .description-content p {
        margin-bottom: var(--space-md);
    }

    .gathering-public-content .description-content p:last-child {
        margin-bottom: 0;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .gathering-public-content .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    @media (max-width: 768px) {
        .gathering-public-content .hero {
            min-height: 35vh;
            padding: var(--space-2xl) var(--space-md);
        }

        .gathering-public-content .hero-meta {
            flex-direction: column;
            gap: var(--space-md);
        }

        .gathering-public-content .section {
            padding: var(--space-xl) 0;
        }

        .gathering-public-content .quick-info {
            grid-template-columns: 1fr;
        }

        .gathering-public-content .activities-grid {
            grid-template-columns: 1fr;
        }

        .gathering-public-content .schedule-timeline {
            padding-left: var(--space-lg);
        }

        .gathering-public-content .info-card {
            flex-direction: column;
            text-align: center;
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
                'userAttendance' => $userAttendance ?? null
            ]) ?>



        </div>
    </td>
</tr>
<?php $this->KMP->endBlock() ?>