<?php

/**
 * Mobile Waiver Upload Wizard Steps
 *
 * @var \App\View\AppView $this
 * @var array $activitiesData
 * @var array $waiverTypesData
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $uploadLimits
 */
?>

<!-- Step 1: Select Activities -->
<div data-waiver-upload-wizard-target="step" data-step-number="1">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-1-circle"></i>
                <?= __('Select Activities') ?>
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3"><?= __('Which activities is this waiver for?') ?></p>

            <?php if (!empty($activitiesData)): ?>
                <div class="list-group">
                    <?php foreach ($activitiesData as $activity): ?>
                        <div class="list-group-item">
                            <div class="form-check">
                                <input class="form-check-input form-check-input-lg"
                                    type="checkbox"
                                    value="<?= $activity['id'] ?>"
                                    id="activity-<?= $activity['id'] ?>"
                                    data-name="<?= h($activity['name']) ?>"
                                    data-waiver-types="<?= h(json_encode($activity['waiver_types'])) ?>"
                                    data-waiver-upload-wizard-target="activityCheckbox"
                                    data-action="change->waiver-upload-wizard#toggleActivity">
                                <label class="form-check-label w-100" for="activity-<?= $activity['id'] ?>">
                                    <strong><?= h($activity['name']) ?></strong>
                                    <?php if (!empty($activity['description'])): ?>
                                        <br>
                                        <small class="text-muted"><?= h($activity['description']) ?></small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= __('No activities found for this gathering.') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Step 2: Select Waiver Type -->
<div data-waiver-upload-wizard-target="step" data-step-number="2" class="d-none">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-2-circle"></i>
                <?= __('Select Waiver Type') ?>
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3"><?= __('What type of waiver are you uploading?') ?></p>

            <?php if (!empty($waiverTypesData)): ?>
                <div class="list-group" data-waiver-upload-wizard-target="waiverTypeSelect">
                    <?php foreach ($waiverTypesData as $waiverType): ?>
                        <div class="list-group-item"
                            data-waiver-upload-wizard-target="waiverTypeOption"
                            data-waiver-type-id="<?= $waiverType['id'] ?>">
                            <div class="form-check">
                                <input class="form-check-input form-check-input-lg"
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
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <?= __('Waiver types will be filtered based on your activity selection.') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Step 3: Capture/Upload Photos -->
<div data-waiver-upload-wizard-target="step" data-step-number="3" class="d-none">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-3-circle"></i>
                <?= __('Add Waiver Photos') ?>
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3"><?= __('Take photos or select images of the waiver pages.') ?></p>

            <!-- File size validation wrapper -->
            <div data-controller="file-size-validator"
                data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
                data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
                data-file-size-validator-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>">

                <!-- Warning message container -->
                <div data-file-size-validator-target="warning" class="d-none mb-3"></div>

                <!-- Camera/Upload Button -->
                <div class="d-grid gap-2 mb-3">
                    <button type="button"
                        class="btn btn-lg btn-info"
                        data-action="click->waiver-upload-wizard#triggerFileInput">
                        <i class="bi bi-camera"></i>
                        <?= __('Take Photo / Add Image') ?>
                    </button>
                </div>

                <!-- Hidden file input with camera capture -->
                <input type="file"
                    accept="image/jpeg,image/jpg,image/png,image/tiff"
                    multiple
                    capture="environment"
                    class="d-none"
                    data-waiver-upload-wizard-target="fileInput"
                    data-file-size-validator-target="fileInput"
                    data-action="change->waiver-upload-wizard#handleFileSelect change->file-size-validator#validateFiles">

                <div class="alert alert-info small">
                    <i class="bi bi-info-circle"></i>
                    <strong><?= __('Tips:') ?></strong>
                    <ul class="mb-0 mt-2 small">
                        <li><?= __('Take clear, well-lit photos') ?></li>
                        <li><?= __('Max size per image: {0}', h($uploadLimits['formatted'])) ?></li>
                        <li><?= __('Photos will be converted to B&W PDF') ?></li>
                    </ul>
                </div>
            </div>

            <!-- Pages Preview -->
            <div class="row g-2" data-waiver-upload-wizard-target="pagesPreview">
                <!-- Dynamically populated by controller -->
            </div>
        </div>
    </div>
</div>

<!-- Step 4: Review & Submit -->
<div data-waiver-upload-wizard-target="step" data-step-number="4" class="d-none">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-4-circle"></i>
                <?= __('Review & Submit') ?>
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3"><?= __('Please review before submitting') ?></p>

            <!-- Activities -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="bi bi-activity"></i> <?= __('Activities') ?>
                </h6>
                <ul class="list-unstyled ps-3" data-waiver-upload-wizard-target="reviewActivities">
                    <!-- Populated by controller -->
                </ul>
            </div>

            <!-- Waiver Type -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="bi bi-file-earmark-text"></i> <?= __('Waiver Type') ?>
                </h6>
                <p class="ps-3 mb-0">
                    <strong data-waiver-upload-wizard-target="reviewWaiverType"></strong>
                </p>
            </div>

            <!-- Pages -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="bi bi-images"></i>
                    <?= __('Pages') ?>
                    <span class="badge bg-info ms-2" data-waiver-upload-wizard-target="reviewPageCount"></span>
                </h6>
                <div class="row g-2 ps-3" data-waiver-upload-wizard-target="reviewPagesList">
                    <!-- Populated by controller -->
                </div>
            </div>

            <!-- Notes -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="bi bi-sticky"></i> <?= __('Notes (Optional)') ?>
                </h6>
                <textarea class="form-control"
                    rows="3"
                    placeholder="<?= __('Add any notes about this waiver...') ?>"
                    data-waiver-upload-wizard-target="notesField"></textarea>
            </div>
        </div>
    </div>
</div>