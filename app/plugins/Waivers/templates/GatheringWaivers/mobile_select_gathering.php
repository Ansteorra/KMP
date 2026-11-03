<?php
declare(strict_types=1);

/**
 * Mobile Gathering Selection Template
 *
 * @var \App\View\AppView $this
 * @var array<\App\Model\Entity\Gathering> $authorizedGatherings
 */
?>

<div class="mobile-gathering-select px-3 pt-3" data-section="waivers">
    <!-- Info Alert -->
    <div class="alert alert-info mb-4" role="alert">
        <kmp-icon name="info-circle" class="me-2" aria-hidden="true"></kmp-icon>
        <?= __('Choose the gathering for which you want to upload waivers.') ?>
    </div>

    <?php if (empty($authorizedGatherings)) : ?>
        <!-- No Gatherings Available -->
        <div class="alert alert-warning" role="alert">
            <kmp-icon name="alert-triangle" class="me-2" aria-hidden="true"></kmp-icon>
            <?= __('No open gatherings requiring waivers were found that you have permission to upload waivers for.') ?>
        </div>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-secondary btn-lg" data-controller="history-back"
                data-action="history-back#go">
                <kmp-icon name="arrow-left" class="me-2" aria-hidden="true"></kmp-icon><?= __('Go Back') ?>
            </button>
        </div>
    <?php else : ?>
        <!-- Gatherings List -->
        <div class="list-group mb-4">
            <?php foreach ($authorizedGatherings as $gathering) : ?>
                <?php
                // Determine card styling based on status
                $cardClass = '';
                $borderColor = 'var(--section-waivers, #ec4899)';

                if ($gathering->is_waiver_complete) {
                    $cardClass = 'status-complete';
                    $borderColor = '#10b981'; // Green
                } elseif ($gathering->is_ready_to_close) {
                    $cardClass = 'status-ready';
                    $borderColor = '#0dcaf0'; // Cyan
                } elseif ($gathering->is_ended) {
                    $cardClass = 'status-ended';
                    $borderColor = '#dc3545'; // Red
                } elseif ($gathering->is_ongoing) {
                    $cardClass = 'status-ongoing';
                    $borderColor = '#ffc107'; // Yellow
                }
                ?>
                <a href="<?= $this->Url->build(['action' => 'mobileUpload', '?' => ['gathering_id' => $gathering->id]]) ?>"
                    class="list-group-item list-group-item-action <?= $cardClass ?>"
                    style="border-left-color: <?= $borderColor ?>;">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <kmp-icon name="calendar-event" class="text-primary me-1" aria-hidden="true"></kmp-icon>
                                <?= h($gathering->name) ?>
                            </h5>
                            
                            <!-- Status Badges -->
                            <div class="mb-2">
                                <?php if ($gathering->is_waiver_complete) : ?>
                                    <span class="badge bg-success">
                                        <kmp-icon name="circle-check" class="me-1" aria-hidden="true"></kmp-icon><?= __('Complete') ?>
                                    </span>
                                <?php else : ?>
                                    <span class="badge bg-warning">
                                        <kmp-icon name="alert-circle" class="me-1" aria-hidden="true"></kmp-icon><?= __n('{0} Missing', '{0} Missing', $gathering->missing_waiver_count, $gathering->missing_waiver_count) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($gathering->is_ready_to_close) : ?>
                                    <span class="badge bg-info">
                                        <kmp-icon name="checkbox" class="me-1" aria-hidden="true"></kmp-icon><?= __('Ready to Close') ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($gathering->is_ended) : ?>
                                    <span class="badge bg-danger">
                                        <kmp-icon name="calendar-x" class="me-1" aria-hidden="true"></kmp-icon><?= __('Ended') ?>
                                    </span>
                                <?php elseif ($gathering->is_ongoing) : ?>
                                    <span class="badge bg-warning">
                                        <kmp-icon name="alarm" class="me-1" aria-hidden="true"></kmp-icon><?= __('In Progress') ?>
                                    </span>
                                <?php elseif ($gathering->is_upcoming) : ?>
                                    <span class="badge bg-info">
                                        <kmp-icon name="clock" class="me-1" aria-hidden="true"></kmp-icon><?= __('Upcoming') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Missing Waivers List -->
                            <?php if (!empty($gathering->missing_waiver_names)) : ?>
                                <p class="mb-1 small text-danger">
                                    <kmp-icon name="file-x" class="me-1" aria-hidden="true"></kmp-icon>
                                    <?= __('Missing: {0}', implode(', ', $gathering->missing_waiver_names)) ?>
                                </p>
                            <?php endif; ?>
                            
                            <p class="mb-1 text-muted small">
                                <kmp-icon name="map-pin" class="me-1" aria-hidden="true"></kmp-icon><?= h($gathering->branch->name ?? 'Unknown Branch') ?>
                            </p>
                            <p class="mb-0 small">
                                <kmp-icon name="calendar-month" class="me-1" aria-hidden="true"></kmp-icon>
                                <?= $this->Timezone->format($gathering->start_date, $gathering, 'M j, Y') ?>
                                <?php if ($this->Timezone->format($gathering->start_date, $gathering, 'Y-m-d') !== $this->Timezone->format($gathering->end_date, $gathering, 'Y-m-d')) : ?>
                                    - <?= $this->Timezone->format($gathering->end_date, $gathering, 'M j, Y') ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($gathering->location)) : ?>
                                <p class="mb-0 text-muted small">
                                    <kmp-icon name="map" class="me-1" aria-hidden="true"></kmp-icon><?= h($gathering->location) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="ms-2">
                            <kmp-icon name="chevron-right" class="text-muted" aria-hidden="true"></kmp-icon>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Info Message -->
        <div class="alert alert-light small" role="alert">
            <kmp-icon name="calendar-week" class="me-2" aria-hidden="true"></kmp-icon>
            <?= __('Showing open gatherings requiring waivers, ordered by end date, oldest first.') ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .mobile-gathering-select .list-group-item {
        border-radius: 4px;
        margin-bottom: 10px;
        border: 1px solid rgba(139, 105, 20, 0.1);
        border-left: 5px solid var(--section-waivers, #8b2252);
        transition: all 0.2s ease;
        background: var(--mobile-card-bg, #fffef9);
        box-shadow: 0 2px 8px rgba(44, 24, 16, 0.06);
        padding: 12px 14px;
    }

    .mobile-gathering-select .list-group-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(44, 24, 16, 0.12);
    }

    .mobile-gathering-select .list-group-item:active {
        transform: translateY(0);
    }

    .mobile-gathering-select h5 {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--mobile-text-primary, #2c1810);
        font-family: var(--font-display, 'Cinzel', serif);
    }

    .mobile-gathering-select .text-muted {
        font-size: 15px;
        color: var(--mobile-text-secondary, #4a3728) !important;
    }

    /* Status-based background tints */
    .mobile-gathering-select .list-group-item.status-complete {
        background: linear-gradient(135deg, rgba(30, 111, 80, 0.08) 0%, #fffef9 100%);
    }

    .mobile-gathering-select .list-group-item.status-ready {
        background: linear-gradient(135deg, rgba(26, 95, 95, 0.08) 0%, #fffef9 100%);
    }

    .mobile-gathering-select .list-group-item.status-ended {
        background: linear-gradient(135deg, rgba(139, 34, 82, 0.08) 0%, #fffef9 100%);
    }

    .mobile-gathering-select .list-group-item.status-ongoing {
        background: linear-gradient(135deg, rgba(139, 105, 20, 0.08) 0%, #fffef9 100%);
    }

    .mobile-gathering-select .badge {
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.4em 0.7em;
        font-family: var(--font-display, 'Cinzel', serif);
        letter-spacing: 0.03em;
        border-radius: 4px;
    }

    /* Ensure badges have readable text on medieval gradient backgrounds */
    .mobile-gathering-select .badge.bg-success {
        background: linear-gradient(180deg, var(--mobile-success, #1e6f50), color-mix(in srgb, var(--mobile-success, #1e6f50) 70%, black)) !important;
        color: var(--medieval-parchment, #f4efe4) !important;
    }

    .mobile-gathering-select .badge.bg-warning {
        background: linear-gradient(180deg, var(--medieval-gold, #c9a227), var(--medieval-bronze, #8b6914)) !important;
        color: var(--medieval-ink, #2c1810) !important;
    }

    .mobile-gathering-select .badge.bg-info {
        background: linear-gradient(180deg, var(--mobile-info, #1a5f5f), color-mix(in srgb, var(--mobile-info, #1a5f5f) 70%, black)) !important;
        color: var(--medieval-parchment, #f4efe4) !important;
    }

    .mobile-gathering-select .badge.bg-danger {
        background: linear-gradient(180deg, var(--mobile-danger, #8b2252), color-mix(in srgb, var(--mobile-danger, #8b2252) 70%, black)) !important;
        color: var(--medieval-parchment, #f4efe4) !important;
    }
</style>
