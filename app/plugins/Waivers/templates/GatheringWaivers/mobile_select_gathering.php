<?php

/**
 * Mobile Gathering Selection Template
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering[] $authorizedGatherings
 */
?>

<div class="mobile-gathering-select p-3">
    <!-- Info Alert -->
    <div class="alert alert-info mb-4" role="alert">
        <i class="bi bi-info-circle"></i>
        <strong><?= __('Select Gathering') ?></strong><br>
        <?= __('Choose the gathering for which you want to upload waivers.') ?>
    </div>

    <?php if (empty($authorizedGatherings)): ?>
        <!-- No Gatherings Available -->
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <?= __('No gatherings found that you have permission to upload waivers for in the current date range (starting within 7 days or ended within last 30 days).') ?>
        </div>

        <div class="d-grid gap-2">
            <a href="javascript:history.back()" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-left"></i> <?= __('Go Back') ?>
            </a>
        </div>
    <?php else: ?>
        <!-- Gatherings List -->
        <div class="list-group mb-4">
            <?php foreach ($authorizedGatherings as $gathering): ?>
                <a href="<?= $this->Url->build(['action' => 'mobileUpload', '?' => ['gathering_id' => $gathering->id]]) ?>"
                    class="list-group-item list-group-item-action">
                    <div class="d-flex w-100 justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <i class="bi bi-calendar-event text-primary"></i>
                                <?= h($gathering->name) ?>
                            </h5>
                            <p class="mb-1 text-muted small">
                                <i class="bi bi-geo-alt"></i> <?= h($gathering->branch->name ?? 'Unknown Branch') ?>
                            </p>
                            <p class="mb-0 small">
                                <i class="bi bi-calendar3"></i>
                                <?= h($gathering->start_date->format('M j, Y')) ?>
                                <?php if ($gathering->start_date->format('Y-m-d') !== $gathering->end_date->format('Y-m-d')): ?>
                                    - <?= h($gathering->end_date->format('M j, Y')) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($gathering->location)): ?>
                                <p class="mb-0 text-muted small">
                                    <i class="bi bi-map"></i> <?= h($gathering->location) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="ms-2">
                            <i class="bi bi-chevron-right text-muted"></i>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Info Message -->
        <div class="alert alert-light small" role="alert">
            <i class="bi bi-calendar-range"></i>
            <?= __('Showing gatherings starting within the next 7 days or ending within the last 30 days.') ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .mobile-gathering-select .list-group-item {
        border-radius: 0.5rem;
        margin-bottom: 0.75rem;
        border: 1px solid #dee2e6;
        transition: all 0.2s ease;
    }

    .mobile-gathering-select .list-group-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border-color: #0d6efd;
    }

    .mobile-gathering-select .list-group-item:active {
        transform: translateY(0);
    }

    .mobile-gathering-select h5 {
        font-size: 1.1rem;
        font-weight: 600;
    }
</style>