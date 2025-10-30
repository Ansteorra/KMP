<?php

/**
 * @var \App\View\AppView $this
 * @var array $activitiesData
 * @var array $waiverTypesData
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<!-- Step 1: Select Activities -->
<div data-waiver-upload-wizard-target="step" data-step-number="1">
    <h3 class="mb-4">
        <i class="bi bi-activity text-primary"></i>
        <?= __('Step 1: Select Activities') ?>
    </h3>

    <p class="lead"><?= __('Which activities is this waiver for?') ?></p>

    <?php if (!empty($activitiesData)): ?>
        <div class="activities-grid">
            <?php foreach ($activitiesData as $activity): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input"
                                type="checkbox"
                                value="<?= $activity['id'] ?>"
                                id="activity-<?= $activity['id'] ?>"
                                data-name="<?= h($activity['name']) ?>"
                                data-waiver-types="<?= h(json_encode($activity['waiver_types'])) ?>"
                                data-waiver-upload-wizard-target="activityCheckbox"
                                data-action="change->waiver-upload-wizard#toggleActivity">
                            <label class="form-check-label" for="activity-<?= $activity['id'] ?>">
                                <strong><?= h($activity['name']) ?></strong>
                                <?php if (!empty($activity['description'])): ?>
                                    <br>
                                    <small class="text-muted"><?= h($activity['description']) ?></small>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <?= __('No activities found for this gathering. Please add activities before uploading waivers.') ?>
        </div>
    <?php endif; ?>
</div>

<!-- Step 2: Select Waiver Type -->
<div data-waiver-upload-wizard-target="step" data-step-number="2" class="d-none">
    <h3 class="mb-4">
        <i class="bi bi-file-earmark-text text-primary"></i>
        <?= __('Step 2: Select Waiver Type') ?>
    </h3>

    <p class="lead"><?= __('What type of waiver are you uploading?') ?></p>

    <?php if (!empty($waiverTypesData)): ?>
        <div data-waiver-upload-wizard-target="waiverTypeSelect">
            <?php foreach ($waiverTypesData as $waiverType): ?>
                <div class="card mb-3"
                    data-waiver-upload-wizard-target="waiverTypeOption"
                    data-waiver-type-id="<?= $waiverType['id'] ?>">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input"
                                type="radio"
                                name="waiver_type"
                                value="<?= $waiverType['id'] ?>"
                                id="waiver-type-<?= $waiverType['id'] ?>"
                                data-name="<?= h($waiverType['name']) ?>"
                                data-action="change->waiver-upload-wizard#selectWaiverType">
                            <label class="form-check-label w-100" for="waiver-type-<?= $waiverType['id'] ?>">
                                <strong><?= h($waiverType['name']) ?></strong>
                                <?php if (!empty($waiverType['description'])): ?>
                                    <br>
                                    <small class="text-muted"><?= h($waiverType['description']) ?></small>
                                <?php endif; ?>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <?= __('Waiver types will be filtered based on your activity selection in Step 1.') ?>
        </div>
    <?php endif; ?>
</div>

<!-- Step 3: Add Pages -->
<div data-waiver-upload-wizard-target="step" data-step-number="3" class="d-none">
    <h3 class="mb-4">
        <i class="bi bi-file-earmark-image text-primary"></i>
        <?= __('Step 3: Add Waiver Pages') ?>
    </h3>

    <p class="lead"><?= __('Add one or more pages to your waiver document') ?></p>

    <!-- File size validation wrapper -->
    <div data-controller="file-size-validator"
        data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
        data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
        data-file-size-validator-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>">

        <!-- Warning message container -->
        <div data-file-size-validator-target="warning" class="d-none mb-3"></div>

        <div class="text-center mb-4">
            <button type="button"
                class="btn btn-lg btn-primary"
                data-action="click->waiver-upload-wizard#triggerFileInput">
                <i class="bi bi-plus-circle"></i>
                <?= __('Add Page') ?>
            </button>

            <!-- Hidden file input -->
            <input type="file"
                accept="image/jpeg,image/jpg,image/png,image/tiff"
                multiple
                capture="environment"
                class="d-none"
                data-waiver-upload-wizard-target="fileInput"
                data-file-size-validator-target="fileInput"
                data-action="change->waiver-upload-wizard#handleFileSelect change->file-size-validator#validateFiles">
        </div>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong><?= __('Tips:') ?></strong>
            <ul class="mb-0 mt-2">
                <li><?= __('Take clear, well-lit photos') ?></li>
                <li><?= __('Supported formats: JPEG, PNG, TIFF') ?></li>
                <li><?= __('Maximum file size per image: {0}', h($uploadLimits['formatted'])) ?></li>
                <li><?= __('Recommended total size: {0}', h($uploadLimits['formatted'])) ?></li>
                <li><?= __('Images will be converted to black & white PDF') ?></li>
            </ul>
        </div>
    </div>

    <!-- Pages Preview -->
    <div class="row" data-waiver-upload-wizard-target="pagesPreview">
        <!-- Dynamically populated by controller -->
    </div>
</div>

<!-- Step 4: Review -->
<div data-waiver-upload-wizard-target="step" data-step-number="4" class="d-none">
    <h3 class="mb-4">
        <i class="bi bi-check-circle text-success"></i>
        <?= __('Step 4: Review & Submit') ?>
    </h3>

    <p class="lead"><?= __('Please review your waiver details before submitting') ?></p>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><?= __('Activities') ?></h5>
        </div>
        <div class="card-body">
            <ul data-waiver-upload-wizard-target="reviewActivities">
                <!-- Populated by controller -->
            </ul>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><?= __('Waiver Type') ?></h5>
        </div>
        <div class="card-body">
            <strong data-waiver-upload-wizard-target="reviewWaiverType"></strong>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">
                <?= __('Pages') ?>
                <span class="badge bg-primary ms-2" data-waiver-upload-wizard-target="reviewPageCount"></span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row" data-waiver-upload-wizard-target="reviewPagesList">
                <!-- Populated by controller -->
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><?= __('Additional Notes (Optional)') ?></h5>
        </div>
        <div class="card-body">
            <textarea class="form-control"
                rows="3"
                placeholder="<?= __('Add any notes about this waiver...') ?>"
                data-waiver-upload-wizard-target="notesField"></textarea>
        </div>
    </div>
</div>

<!-- Navigation Buttons -->
<div class="wizard-navigation mt-4 d-flex justify-content-between">
    <div>
        <button type="button"
            class="btn btn-outline-secondary"
            data-waiver-upload-wizard-target="prevButton"
            data-action="click->waiver-upload-wizard#prevStep">
            <i class="bi bi-arrow-left"></i> <?= __('Previous') ?>
        </button>
    </div>
    <div>
        <?= $this->Html->link(
            __('Cancel'),
            ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->id],
            ['class' => 'btn btn-outline-danger me-2']
        ) ?>
        <button type="button"
            class="btn btn-primary"
            data-waiver-upload-wizard-target="nextButton"
            data-action="click->waiver-upload-wizard#nextStep">
            <?= __('Next') ?> <i class="bi bi-arrow-right"></i>
        </button>
        <button type="button"
            class="btn btn-success d-none"
            data-waiver-upload-wizard-target="submitButton"
            data-action="click->waiver-upload-wizard#submitForm">
            <i class="bi bi-check-circle"></i> <?= __('Upload Waiver') ?>
        </button>
    </div>
</div>
</div>
</div>
</div>
</div>