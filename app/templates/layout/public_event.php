<?php

/**
 * Public Event Landing Page Layout
 * 
 * A minimal, beautiful layout for public gathering pages
 * No authentication required - optimized for mobile and desktop
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= $this->fetch('title', 'Event') ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-sans);
            line-height: 1.6;
            color: var(--color-gray-800);
            background: var(--color-gray-50);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Hero Section */
        .hero {
            position: relative;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            color: var(--color-white);
            padding: var(--space-3xl) var(--space-lg);
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .hero::before {
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

        .hero-content {
            position: relative;
            max-width: 900px;
            text-align: center;
            z-index: 1;
        }

        .event-badge {
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

        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 5vw, 4rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: var(--space-lg);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-lg);
            justify-content: center;
            font-size: 1.125rem;
            margin-top: var(--space-xl);
        }

        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-lg);
        }

        .hero-meta-item i {
            font-size: 1.5rem;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--space-lg);
        }

        /* Section */
        .section {
            padding: var(--space-3xl) 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: var(--space-2xl);
        }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 700;
            color: var(--color-gray-900);
            margin-bottom: var(--space-md);
        }

        .section-subtitle {
            font-size: 1.125rem;
            color: var(--color-gray-600);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Cards */
        .card {
            background: var(--color-white);
            border-radius: var(--radius-xl);
            padding: var(--space-xl);
            box-shadow: var(--shadow-md);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        /* Quick Info Grid */
        .quick-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--space-lg);
            margin: var(--space-2xl) 0;
        }

        .info-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            box-shadow: var(--shadow-md);
            display: flex;
            gap: var(--space-lg);
            align-items: flex-start;
        }

        .info-icon {
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

        .info-content h3 {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--color-gray-600);
            margin-bottom: var(--space-xs);
        }

        .info-content p {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--color-gray-900);
        }

        /* Schedule Timeline */
        .schedule-day {
            margin-bottom: var(--space-2xl);
        }

        .schedule-day-header {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-gray-900);
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 3px solid var(--color-primary);
        }

        .schedule-timeline {
            position: relative;
            padding-left: var(--space-2xl);
        }

        .schedule-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--color-gray-300);
        }

        .schedule-item {
            position: relative;
            margin-bottom: var(--space-xl);
            padding-left: var(--space-lg);
        }

        .schedule-item::before {
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

        .schedule-time {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--color-primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: var(--space-xs);
        }

        .schedule-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--color-gray-900);
            margin-bottom: var(--space-xs);
        }

        .schedule-description {
            color: var(--color-gray-600);
            line-height: 1.6;
        }

        .schedule-activity-tag {
            display: inline-block;
            background: var(--color-gray-100);
            color: var(--color-gray-700);
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: var(--space-sm);
        }

        /* Activities Grid */
        .activities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--space-lg);
        }

        .activity-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--color-primary);
            transition: all 0.2s;
        }

        .activity-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateX(4px);
        }

        .activity-name {
            font-weight: 600;
            color: var(--color-gray-900);
            margin-bottom: var(--space-xs);
        }

        .activity-description {
            font-size: 0.875rem;
            color: var(--color-gray-600);
        }

        /* Map */
        #map {
            height: 400px;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            margin-top: var(--space-xl);
        }

        .location-address {
            background: var(--color-white);
            padding: var(--space-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-top: var(--space-lg);
            text-align: center;
        }

        .location-address i {
            font-size: 2rem;
            color: var(--color-primary);
            margin-bottom: var(--space-md);
        }

        .location-address p {
            font-size: 1.125rem;
            color: var(--color-gray-800);
            line-height: 1.8;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
            color: var(--color-white);
            padding: var(--space-3xl) var(--space-lg);
            text-align: center;
            border-radius: var(--radius-2xl);
            margin: var(--space-2xl) 0;
        }

        .cta-section h2 {
            font-family: var(--font-display);
            font-size: clamp(1.75rem, 4vw, 2.5rem);
            font-weight: 700;
            margin-bottom: var(--space-md);
        }

        .cta-section p {
            font-size: 1.125rem;
            margin-bottom: var(--space-xl);
            opacity: 0.95;
        }

        .btn {
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

        .btn-primary {
            background: var(--color-white);
            color: var(--color-primary);
            box-shadow: var(--shadow-lg);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .btn-outline {
            background: transparent;
            color: var(--color-white);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }

        .btn-outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--color-white);
        }

        /* Footer */
        .footer {
            background: var(--color-gray-900);
            color: var(--color-gray-400);
            padding: var(--space-2xl) var(--space-lg);
            text-align: center;
        }

        .footer p {
            font-size: 0.875rem;
        }

        /* Description Content */
        .description-content {
            background: var(--color-white);
            padding: var(--space-2xl);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            line-height: 1.8;
            color: var(--color-gray-700);
        }

        .description-content p {
            margin-bottom: var(--space-md);
        }

        .description-content p:last-child {
            margin-bottom: 0;
        }

        .btn-group {
            position: relative;
            display: inline-flex;
            vertical-align: middle;
        }

        .dropdown-toggle::after {
            display: inline-block;
            margin-left: 0.255em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
            font-size: 1rem;
            color: var(--color-gray-800);
            text-align: left;
            list-style: none;
            background-color: var(--color-white);
            background-clip: padding-box;
            border: 1px solid var(--color-gray-300);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: var(--color-gray-800);
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            transition: background-color 0.2s, color 0.2s;
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            color: var(--color-gray-900);
            background-color: var(--color-gray-100);
        }

        .dropdown-item i {
            margin-right: 0.5rem;
            width: 1.25rem;
            text-align: center;
        }

        .dropdown-divider {
            height: 0;
            margin: 0.5rem 0;
            overflow: hidden;
            border-top: 1px solid var(--color-gray-200);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero {
                min-height: 50vh;
                padding: var(--space-2xl) var(--space-md);
            }

            .hero-meta {
                flex-direction: column;
                gap: var(--space-md);
            }

            .section {
                padding: var(--space-xl) 0;
            }

            .quick-info {
                grid-template-columns: 1fr;
            }

            .activities-grid {
                grid-template-columns: 1fr;
            }

            .schedule-timeline {
                padding-left: var(--space-lg);
            }

            .info-card {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Loading Animation */
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

        .fade-in {
            animation: fadeIn 0.6s ease-out;
        }
    </style>

    <?= $this->fetch('css') ?>
</head>

<body>
    <?= $this->Flash->render() ?>
    <?= $this->fetch('content') ?>

    <!-- Bootstrap JS for dropdowns and interactive components -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?= $this->fetch('script') ?>
</body>

</html>