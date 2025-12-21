<?php

/**
 * @var \App\View\AppView $this
 * @var array $waiverTypesData
 * @var \App\Model\Entity\Gathering $gathering
 */
?>
<!-- Step 1: Select Waiver Type -->
<div data-waiver-upload-wizard-target="step" data-step-number="1">
    <h3 class="mb-4">
        <i class="bi bi-file-earmark-text text-primary"></i>
        <?= __('Step 1: Select Waiver Type') ?>
    </h3>

    <p class="lead"><?= __('What type of waiver are you uploading for this gathering?') ?></p>

    <?php if (!empty($waiverTypesData)): ?>
        <div data-waiver-upload-wizard-target="waiverTypeSelect">
            <?php foreach ($waiverTypesData as $waiverType): ?>
                <div class="card mb-3" data-waiver-upload-wizard-target="waiverTypeOption"
                    data-waiver-type-id="<?= $waiverType['id'] ?>">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="waiver_type" value="<?= $waiverType['id'] ?>"
                                id="waiver-type-<?= $waiverType['id'] ?>" data-name="<?= h($waiverType['name']) ?>"
                                data-exemption-reasons="<?= h(json_encode($waiverType['exemption_reasons'] ?? [])) ?>"
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
            <?= __('No waiver types have been configured for this gathering.') ?>
        </div>
    <?php endif; ?>
</div>

<!-- Step 2: Add Pages or Attest -->
<div data-waiver-upload-wizard-target="step" data-step-number="2" class="d-none">
    <h3 class="mb-4">
        <i class="bi bi-file-earmark-image text-primary"></i>
        <?= __('Step 2: Add Waiver Pages') ?>
    </h3>

    <p class="lead" data-waiver-upload-wizard-target="step3Lead">
        <?= __('Add one or more pages to your waiver document') ?></p>

    <!-- Mode Toggle (hidden by default, shown by JS if exemption reasons available) -->
    <div class="btn-group w-100 mb-4 d-none" role="group" aria-label="Upload or Attest mode"
        data-waiver-upload-wizard-target="modeToggle">
        <input type="radio" class="btn-check" name="upload-mode" id="mode-upload" autocomplete="off" checked
            data-action="change->waiver-upload-wizard#setModeUpload">
        <label class="btn btn-outline-primary" for="mode-upload">
            <i class="bi bi-cloud-upload"></i> <?= __('Submit Waivers') ?>
        </label>

        <input type="radio" class="btn-check" name="upload-mode" id="mode-attest" autocomplete="off"
            data-action="change->waiver-upload-wizard#setModeAttest">
        <label class="btn btn-outline-primary" for="mode-attest">
            <i class="bi bi-shield-check"></i> <?= __('Attest Not Needed') ?>
        </label>
    </div>

    <!-- Upload Mode Section -->
    <div data-waiver-upload-wizard-target="uploadSection">
        <!-- File size validation wrapper -->
        <div data-controller="file-size-validator"
            data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
            data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
            data-file-size-validator-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>">

            <!-- Warning message container -->
            <div data-file-size-validator-target="warning" class="d-none mb-3"></div>

            <div class="text-center mb-4">
                <button type="button" class="btn btn-lg btn-primary"
                    data-action="click->waiver-upload-wizard#triggerFileInput">
                    <i class="bi bi-plus-circle"></i>
                    <?= __('Add Page') ?>
                </button>

                <!-- Hidden file input -->
                <input type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/bmp,image/webp" multiple
                    capture="environment" class="d-none" data-waiver-upload-wizard-target="fileInput"
                    data-file-size-validator-target="fileInput"
                    data-action="change->waiver-upload-wizard#handleFileSelect change->file-size-validator#validateFiles">
            </div>

            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                <strong><?= __('Tips:') ?></strong>
                <ul class="mb-0 mt-2">
                    <li><?= __('Take clear, well-lit photos') ?></li>
                    <li><?= __('Supported formats: JPEG, PNG, GIF, BMP, WEBP') ?></li>
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

    <!-- Attestation Mode Section -->
    <div data-waiver-upload-wizard-target="attestSection" class="d-none">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <?= __('You are attesting that a waiver is not needed for this gathering. Please provide a reason.') ?>
        </div>

        <!-- Reason Selection -->
        <div class="mb-3">
            <label class="form-label fw-bold"><?= __('Why is this waiver not needed?') ?></label>
            <div data-waiver-upload-wizard-target="attestReasonList">
                <!-- Will be populated by JavaScript based on selected waiver type -->
            </div>
        </div>

        <!-- Optional Notes -->
        <div class="mb-3">
            <label for="attestNotes" class="form-label"><?= __('Additional Notes (Optional)') ?></label>
            <textarea class="form-control" id="attestNotes" rows="3" data-waiver-upload-wizard-target="attestNotes"
                placeholder="<?= __('Any additional context or explanation...') ?>"></textarea>
        </div>
    </div>
</div>

<!-- Step 3: Review -->
<div data-waiver-upload-wizard-target="step" data-step-number="3" class="d-none">
    <h3 class="mb-4">
        <i class="bi bi-check-circle text-success"></i>
        <?= __('Step 3: Review & Submit') ?>
    </h3>

    <p class="lead"><?= __('Please review your details before submitting') ?></p>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0"><?= __('Waiver Type') ?></h5>
        </div>
        <div class="card-body">
            <strong data-waiver-upload-wizard-target="reviewWaiverType"></strong>
        </div>
    </div>

    <!-- Upload Mode Review -->
    <div data-waiver-upload-wizard-target="reviewUploadSection">
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
                <textarea class="form-control" rows="3" placeholder="<?= __('Add any notes about this waiver...') ?>"
                    data-waiver-upload-wizard-target="notesField"></textarea>
            </div>
        </div>
    </div>

    <!-- Attestation Mode Review -->
    <div data-waiver-upload-wizard-target="reviewAttestSection" class="d-none">
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-shield-check"></i> <?= __('Attestation: Waiver Not Needed') ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong><?= __('Reason:') ?></strong>
                    <p class="mb-0" data-waiver-upload-wizard-target="reviewAttestReason"></p>
                </div>
                <div data-waiver-upload-wizard-target="reviewAttestNotesSection" class="d-none">
                    <strong><?= __('Additional Notes:') ?></strong>
                    <p class="mb-0" data-waiver-upload-wizard-target="reviewAttestNotes"></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Buttons -->
<div class="wizard-navigation mt-4 d-flex justify-content-between">
    <div>
        <button type="button" class="btn btn-outline-secondary" data-waiver-upload-wizard-target="prevButton"
            data-action="click->waiver-upload-wizard#prevStep">
            <i class="bi bi-arrow-left"></i> <?= __('Previous') ?>
        </button>
    </div>
    <div>
        <?= $this->Html->link(
            __('Cancel'),
            ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id],
            ['class' => 'btn btn-outline-danger me-2']
        ) ?>
        <button type="button" class="btn btn-primary" data-waiver-upload-wizard-target="nextButton"
            data-action="click->waiver-upload-wizard#nextStep">
            <?= __('Next') ?> <i class="bi bi-arrow-right"></i>
        </button>
        <button type="button" class="btn btn-success d-none"
            data-waiver-upload-wizard-target="submitButton"
            data-action="click->waiver-upload-wizard#submitForm">
            <i class="bi bi-check-circle"></i> <span data-waiver-upload-wizard-target="submitButtonText"><?= __('Submit Waivers') ?></span>
        </button>
    </div>
</div>
</div>
</div>
</div>
</div>