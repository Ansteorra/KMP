<?php
declare(strict_types=1);

/**
 * Mobile Waiver Upload Template
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array<\Waivers\Model\Entity\WaiverType> $requiredWaiverTypes
 * @var array $waiverTypesData
 * @var array $uploadLimits
 * @var array $waiverStatusSummary
 */
$multipleDays = $this->Timezone->format($gathering->start_date, $gathering, 'Y-m-d')
    !== $this->Timezone->format($gathering->end_date, $gathering, 'Y-m-d');
?>

<div class="card cardbox mx-3 mt-3" data-section="waivers">
    <div class="card-body">
        <div class="card mb-4 waiver-gathering-info">
            <div class="card-body p-3">
                <h5 class="card-title mb-2">
                    <kmp-icon name="calendar-event" class="me-2" aria-hidden="true"></kmp-icon>
                    <?= h($gathering->name) ?>
                </h5>
                <p class="card-text small mb-1">
                    <kmp-icon name="calendar-month" class="me-1" aria-hidden="true"></kmp-icon>
                    <?= $this->Timezone->format($gathering->start_date, $gathering, 'M j, Y') ?>
                    <?php if ($multipleDays) : ?>
                        - <?= $this->Timezone->format($gathering->end_date, $gathering, 'M j, Y') ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($gathering->location)) : ?>
                    <p class="card-text text-muted small mb-0">
                        <kmp-icon name="map-pin" class="me-1" aria-hidden="true"></kmp-icon>
                        <?= h($gathering->location) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?= $this->element('GatheringWaivers/upload_wizard', [
            'mobile' => true,
            'waiverTypesData' => $waiverTypesData,
            'gathering' => $gathering,
            'uploadLimits' => $uploadLimits,
            'waiverStatusSummary' => $waiverStatusSummary,
        ]) ?>
    </div>
</div>

<style>
.waiver-gathering-info {
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    border-left: 4px solid var(--section-waivers, #ec4899);
}
</style>
