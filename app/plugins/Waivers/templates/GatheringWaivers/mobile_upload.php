<?php

/**
 * Mobile Waiver Upload Template
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\WaiverType[] $requiredWaiverTypes
 * @var array $waiverTypesData
 * @var array $uploadLimits
 * @var array $waiverStatusSummary
 */
?>

<style>
/* iOS Safari fix for fixed-bottom navigation */
.wizard-navigation-mobile.fixed-bottom {
    /* Use sticky positioning as fallback for iOS Safari */
    position: -webkit-sticky;
    position: sticky;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1030;
    /* Safe area inset for iOS devices with home indicator */
    padding-bottom: max(12px, env(safe-area-inset-bottom)) !important;
}

/* For iOS Safari, we need to ensure the parent container allows sticky to work */
.mobile-waiver-wizard {
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 200px);
    min-height: calc(100dvh - 200px); /* Dynamic viewport height for iOS */
}

.wizard-container-mobile {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    /* Add padding at bottom to prevent content being hidden behind nav */
    padding-bottom: 80px;
}

/* Ensure body doesn't have overflow issues on iOS */
@supports (-webkit-touch-callout: none) {
    /* iOS-specific styles */
    .wizard-navigation-mobile.fixed-bottom {
        position: fixed;
        bottom: 0;
        /* Transform hack to force GPU layer and fix iOS Safari rendering */
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
    }
    
    body.viewMobileCard {
        /* Prevent rubber-banding that can cause fixed elements to move */
        overscroll-behavior: none;
        -webkit-overflow-scrolling: touch;
    }
}
</style>

<!-- Waiver Upload Wizard (Mobile Optimized) -->
<div class="card cardbox mx-3 mt-3" data-section="waivers">
    <div class="card-body">
        <div class="mobile-waiver-wizard" data-controller="waiver-upload-wizard"
            data-waiver-upload-wizard-gathering-id-value="<?= $gathering->id ?>"
            data-waiver-upload-wizard-gathering-public-id-value="<?= h($gathering->public_id) ?>"
            data-waiver-upload-wizard-total-steps-value="3"
            data-waiver-upload-wizard-max-file-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
            data-waiver-upload-wizard-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>"
            data-waiver-upload-wizard-attest-url-value="<?= $this->Url->build(['plugin' => 'Waivers', 'controller' => 'GatheringWaivers', 'action' => 'attest']) ?>"
            data-waiver-upload-wizard-gathering-view-url-value="<?= $this->Url->build(['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id, '?' => ['tab' => 'gathering-waivers']]) ?>"
            data-waiver-upload-wizard-mobile-select-url-value="<?= $this->Url->build(['plugin' => 'Waivers', 'controller' => 'GatheringWaivers', 'action' => 'mobileSelectGathering']) ?>">

            <!-- Gathering Info Header -->
            <div class="card mb-4 waiver-gathering-info">
                <div class="card-body p-3">
                    <h5 class="card-title mb-2">
                        <i class="bi bi-calendar-event me-2"></i>
                        <?= h($gathering->name) ?>
                    </h5>
                    <p class="card-text small mb-1">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= $this->Timezone->format($gathering->start_date, $gathering, 'M j, Y') ?>
                        <?php if ($this->Timezone->format($gathering->start_date, $gathering, 'Y-m-d') !== $this->Timezone->format($gathering->end_date, $gathering, 'Y-m-d')): ?>
                            - <?= $this->Timezone->format($gathering->end_date, $gathering, 'M j, Y') ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($gathering->location)): ?>
                        <p class="card-text text-muted small mb-0">
                            <i class="bi bi-geo-alt me-1"></i><?= h($gathering->location) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="progress" style="height: 8px; border-radius: 4px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                        data-waiver-upload-wizard-target="progressBar" style="width: 33%; background: linear-gradient(90deg, var(--mobile-accent, #3b82f6), #60a5fa);" aria-valuenow="33"
                        aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
            </div>

            <!-- Step Indicators (Mobile Compact) -->
            <div class="wizard-steps-mobile mb-4">
                <div class="d-flex justify-content-between text-center">
                    <div class="wizard-step-mobile" data-waiver-upload-wizard-target="stepIndicator" data-step="1">
                        <div class="step-circle">
                            <span class="badge rounded-pill" style="background: var(--mobile-accent, #3b82f6);">1</span>
                        </div>
                        <small class="d-block mt-1"><?= __('Waiver Type') ?></small>
                    </div>
                    <div class="wizard-step-mobile" data-waiver-upload-wizard-target="stepIndicator" data-step="2">
                        <div class="step-circle">
                            <span class="badge rounded-pill bg-secondary">2</span>
                        </div>
                        <small class="d-block mt-1"><?= __('Upload') ?></small>
                    </div>
                    <div class="wizard-step-mobile" data-waiver-upload-wizard-target="stepIndicator" data-step="3">
                        <div class="step-circle">
                            <span class="badge rounded-pill bg-secondary">3</span>
                        </div>
                        <small class="d-block mt-1"><?= __('Review') ?></small>
                    </div>
                </div>
            </div>

            <!-- Wizard Container -->
            <div class="wizard-container-mobile">
                <?php echo $this->element('GatheringWaivers/mobile_wizard_steps', [
                    'waiverTypesData' => $waiverTypesData,
                    'gathering' => $gathering,
                    'uploadLimits' => $uploadLimits,
                    'waiverStatusSummary' => $waiverStatusSummary
                ]); ?>
            </div>

            <!-- Mobile Navigation Buttons (Sticky Bottom) -->
            <div class="wizard-navigation-mobile fixed-bottom bg-white border-top p-3" style="box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.08);">
                <div class="d-flex justify-content-between gap-2">
                    <button type="button" class="btn btn-outline-secondary"
                        data-waiver-upload-wizard-target="prevButton"
                        data-action="click->waiver-upload-wizard#prevStep">
                        <i class="bi bi-arrow-left me-1"></i><?= __('Back') ?>
                    </button>

                    <?= $this->Html->link(
                        __('Cancel'),
                        ['action' => 'mobileSelectGathering'],
                        ['class' => 'btn btn-outline-danger']
                    ) ?>

                    <button type="button" class="btn btn-primary" data-waiver-upload-wizard-target="nextButton"
                        data-action="click->waiver-upload-wizard#nextStep">
                        <?= __('Next') ?><i class="bi bi-arrow-right ms-1"></i>
                    </button>

                    <button type="button" class="btn btn-success d-none" data-waiver-upload-wizard-target="submitButton"
                        data-action="click->waiver-upload-wizard#submitForm">
                        <i class="bi bi-upload me-1"></i><span data-waiver-upload-wizard-target="submitButtonText"><?= __('Upload') ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Add bottom padding to account for fixed bottom navigation */
.mobile-waiver-wizard {
    padding-bottom: 90px;
}

/* Waiver gathering info card - consistent section branding */
.waiver-gathering-info {
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.04);
    border-left: 4px solid var(--section-waivers, #ec4899);
}

/* Fixed bottom navigation - medieval styling */
.wizard-navigation-mobile {
    background: var(--mobile-card-bg, #fffef9) !important;
    border-top: 2px solid var(--section-waivers, #8b2252) !important;
}
</style>
