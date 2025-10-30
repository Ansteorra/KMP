<?php

/**
 * Mobile Waiver Upload Template
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var \Waivers\Model\Entity\WaiverType[] $requiredWaiverTypes
 * @var array $activitiesData
 * @var array $waiverTypesData
 * @var array $uploadLimits
 */
?>

<!-- Waiver Upload Wizard (Mobile Optimized) -->
<div class="card cardbox mx-3">
    <div class="card-body">
        <h3 class="card-title text-center display-6">
            <?= __('Upload Waivers') ?>
        </h3>
        <div class="mobile-waiver-wizard" data-controller="waiver-upload-wizard"
            data-waiver-upload-wizard-gathering-id-value="<?= $gathering->id ?>"
            data-waiver-upload-wizard-total-steps-value="4"
            data-waiver-upload-wizard-max-file-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
            data-waiver-upload-wizard-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>">

            <!-- Gathering Info Header -->
            <div class="card mb-3 border-info">
                <div class="card-body p-3">
                    <h5 class="card-title mb-2">
                        <i class="bi bi-calendar-event text-info"></i>
                        <?= h($gathering->name) ?>
                    </h5>
                    <p class="card-text small mb-1">
                        <i class="bi bi-calendar3"></i>
                        <?= h($gathering->start_date->format('M j, Y')) ?>
                        <?php if ($gathering->start_date->format('Y-m-d') !== $gathering->end_date->format('Y-m-d')): ?>
                        - <?= h($gathering->end_date->format('M j, Y')) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($gathering->location)): ?>
                    <p class="card-text text-muted small mb-0">
                        <i class="bi bi-geo-alt"></i> <?= h($gathering->location) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-3">
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar"
                        data-waiver-upload-wizard-target="progressBar" style="width: 25%;" aria-valuenow="25"
                        aria-valuemin="0" aria-valuemax="100">
                    </div>
                </div>
            </div>

            <!-- Step Indicators (Mobile Compact) -->
            <div class="wizard-steps-mobile mb-3">
                <div class="d-flex justify-content-between text-center">
                    <div class="wizard-step-mobile" data-waiver-upload-wizard-target="stepIndicator" data-step="1">
                        <div class="step-circle">
                            <span class="badge rounded-pill bg-info">1</span>
                        </div>
                        <small class="d-block mt-1"><?= __('Activities') ?></small>
                    </div>
                    <div class="wizard-step-mobile" data-waiver-upload-wizard-target="stepIndicator" data-step="2">
                        <div class="step-circle">
                            <span class="badge rounded-pill bg-secondary">2</span>
                        </div>
                        <small class="d-block mt-1"><?= __('Type') ?></small>
                    </div>
                    <div class="wizard-step-mobile" data-waiver-upload-wizard-target="stepIndicator" data-step="3">
                        <div class="step-circle">
                            <span class="badge rounded-pill bg-secondary">3</span>
                        </div>
                        <small class="d-block mt-1"><?= __('Photos') ?></small>
                    </div>
                    <div class="wizard-step-mobile" data-waiver-upload-wizard-target="stepIndicator" data-step="4">
                        <div class="step-circle">
                            <span class="badge rounded-pill bg-secondary">4</span>
                        </div>
                        <small class="d-block mt-1"><?= __('Review') ?></small>
                    </div>
                </div>
            </div>

            <!-- Wizard Container -->
            <div class="wizard-container-mobile">
                <?php echo $this->element('GatheringWaivers/mobile_wizard_steps', [
                    'activitiesData' => $activitiesData,
                    'waiverTypesData' => $waiverTypesData,
                    'gathering' => $gathering,
                    'uploadLimits' => $uploadLimits
                ]); ?>
            </div>

            <!-- Mobile Navigation Buttons (Sticky Bottom) -->
            <div class="wizard-navigation-mobile fixed-bottom bg-white border-top p-3">
                <div class="d-flex justify-content-between gap-2">
                    <button type="button" class="btn btn-outline-secondary"
                        data-waiver-upload-wizard-target="prevButton"
                        data-action="click->waiver-upload-wizard#prevStep">
                        <i class="bi bi-arrow-left"></i> <?= __('Back') ?>
                    </button>

                    <?= $this->Html->link(
                        __('Cancel'),
                        ['action' => 'mobileSelectGathering'],
                        ['class' => 'btn btn-outline-danger']
                    ) ?>

                    <button type="button" class="btn btn-info" data-waiver-upload-wizard-target="nextButton"
                        data-action="click->waiver-upload-wizard#nextStep">
                        <?= __('Next') ?> <i class="bi bi-arrow-right"></i>
                    </button>

                    <button type="button" class="btn btn-success d-none" data-waiver-upload-wizard-target="submitButton"
                        data-action="click->waiver-upload-wizard#submitForm">
                        <i class="bi bi-upload"></i> <?= __('Upload') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</style>