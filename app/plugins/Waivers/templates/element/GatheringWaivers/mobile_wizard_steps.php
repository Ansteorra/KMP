<?php

/**
 * Mobile Waiver Upload Wizard Steps
 *
 * @var \App\View\AppView $this
 * @var array $waiverTypesData
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $uploadLimits
 * @var array $waiverStatusSummary
 */

$attestedWaiverTypes = [];
foreach (($waiverStatusSummary ?? []) as $summary) {
    if (!empty($summary['attestation_reasons'])) {
        $attestedWaiverTypes[(int)$summary['id']] = $summary['attestation_reasons'];
    }
}
?>

<!-- Step 1: Select Waiver Type -->
<div data-waiver-upload-wizard-target="step" data-step-number="1">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-1-circle"></i>
                <?= __('Select Waiver Type') ?>
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3"><?= __('What type of waiver are you uploading for this gathering?') ?></p>

            <?php if (!empty($waiverStatusSummary)): ?>
                <div class="mb-3 p-2 border rounded bg-light">
                    <div class="d-flex align-items-center mb-2 small text-muted">
                        <i class="bi bi-clipboard-check text-success me-2"></i>
                        <strong class="text-dark"><?= __('Waiver Status') ?></strong>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($waiverStatusSummary as $summary): ?>
                            <?php
                            $hasUploaded = $summary['uploaded_count'] > 0;
                            $hasAttestation = !empty($summary['attestation_reasons']);
                            $statusLabel = $hasUploaded || $hasAttestation ? __('On File') : __('Outstanding');
                            $statusClass = $hasUploaded || $hasAttestation ? 'bg-success' : 'bg-warning text-dark';
                            ?>
                            <li class="list-group-item px-0 py-2 bg-transparent">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="me-2">
                                        <strong class="d-block"><?= h($summary['name']) ?></strong>
                                        <small class="text-muted">
                                            <?php if ($hasUploaded): ?>
                                                <?= __('Uploaded: {0}', $summary['uploaded_count']) ?>
                                            <?php else: ?>
                                                <?= __('Uploaded: none') ?>
                                            <?php endif; ?>
                                            <?php if ($hasAttestation): ?>
                                                <span class="ms-1"><?= __('Attested: {0}', h(implode(', ', $summary['attestation_reasons']))) ?></span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <span class="badge <?= $statusClass ?> align-self-center"><?= $statusLabel ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($waiverTypesData)): ?>
                <div class="list-group" data-waiver-upload-wizard-target="waiverTypeSelect">
                    <?php foreach ($waiverTypesData as $waiverType): ?>
                        <?php
                        $isAttested = isset($attestedWaiverTypes[$waiverType['id']]);
                        $attestationReasons = $attestedWaiverTypes[$waiverType['id']] ?? [];
                        ?>
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
                                    data-exemption-reasons="<?= h(json_encode($waiverType['exemption_reasons'] ?? [])) ?>"
                                    data-attested="<?= $isAttested ? '1' : '0' ?>"
                                    <?= $isAttested ? 'disabled' : '' ?>
                                    data-action="change->waiver-upload-wizard#selectWaiverType">
                                <label class="form-check-label w-100" for="waiver-type-<?= $waiverType['id'] ?>">
                                    <strong><?= h($waiverType['name']) ?></strong>
                                    <?php if (!empty($waiverType['description'])): ?>
                                        <br>
                                        <small class="text-muted"><?= h($waiverType['description']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($isAttested): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="bi bi-shield-check"></i>
                                            <?= __('Attested not needed') ?>
                                            <?php if (!empty($attestationReasons)): ?>
                                                <span class="ms-1">(<?= h(implode(', ', $attestationReasons)) ?>)</span>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </label>
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
    </div>
</div>

<!-- Step 2: Capture/Upload Photos or Attest -->
<div data-waiver-upload-wizard-target="step" data-step-number="2" class="d-none">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">
                <i class="bi bi-2-circle"></i>
                <span data-waiver-upload-wizard-target="step3Lead"><?= __('Add Waiver Photos') ?></span>
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3"><?= __('Take photos or select images of the waiver pages, or attest that a waiver is not needed') ?></p>

            <!-- Mode Toggle (Upload vs Attest) -->
            <div class="btn-group d-grid mb-3 d-none" role="group" aria-label="Upload or Attest mode" data-waiver-upload-wizard-target="modeToggle">
                <input type="radio" class="btn-check" name="upload-mode" id="mode-upload-mobile" value="upload" checked
                    data-action="change->waiver-upload-wizard#setModeUpload">
                <label class="btn btn-outline-info" for="mode-upload-mobile">
                    <i class="bi bi-camera"></i> <?= __('Upload Waiver') ?>
                </label>

                <input type="radio" class="btn-check" name="upload-mode" id="mode-attest-mobile" value="attest"
                    data-action="change->waiver-upload-wizard#setModeAttest">
                <label class="btn btn-outline-warning" for="mode-attest-mobile">
                    <i class="bi bi-file-earmark-x"></i> <?= __('Attest Not Needed') ?>
                </label>
            </div>

            <!-- Upload Section -->
            <div data-waiver-upload-wizard-target="uploadSection">
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
                        accept="image/jpeg,image/jpg,image/png,image/gif,image/bmp,image/webp"
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

            <!-- Attest Section -->
            <div class="d-none" data-waiver-upload-wizard-target="attestSection">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?= __('You are attesting that a waiver is not needed for this gathering. Please provide a reason.') ?>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold"><?= __('Why is this waiver not needed?') ?></label>
                    <div class="list-group" data-waiver-upload-wizard-target="attestReasonList">
                        <!-- Populated dynamically by controller based on selected waiver type -->
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold"><?= __('Additional Notes (Optional)') ?></label>
                    <textarea class="form-control"
                        rows="3"
                        placeholder="<?= __('Any additional context or explanation...') ?>"
                        data-waiver-upload-wizard-target="attestNotes"></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Step 3: Review & Submit -->
<div data-waiver-upload-wizard-target="step" data-step-number="3" class="d-none">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">
                <i class="bi bi-3-circle"></i>
                <?= __('Review & Submit') ?>
            </h5>
        </div>
        <div class="card-body">
            <p class="mb-3"><?= __('Please review before submitting') ?></p>

            <!-- Waiver Type -->
            <div class="mb-3">
                <h6 class="text-muted mb-2">
                    <i class="bi bi-file-earmark-text"></i> <?= __('Waiver Type') ?>
                </h6>
                <p class="ps-3 mb-0">
                    <strong data-waiver-upload-wizard-target="reviewWaiverType"></strong>
                </p>
            </div>

            <!-- Upload Section - Pages and Notes -->
            <div data-waiver-upload-wizard-target="reviewUploadSection">
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

            <!-- Attest Section - Reason and Notes -->
            <div class="d-none" data-waiver-upload-wizard-target="reviewAttestSection">
                <div class="mb-3">
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-file-earmark-x"></i> <?= __('Attestation: Waiver Not Needed') ?>
                    </h6>
                    <div class="ps-3">
                        <strong><?= __('Reason:') ?></strong>
                        <p data-waiver-upload-wizard-target="reviewAttestReason"></p>
                    </div>
                </div>

                <!-- Notes (if provided) -->
                <div class="mb-3 d-none" data-waiver-upload-wizard-target="reviewAttestNotesSection">
                    <h6 class="text-muted mb-2">
                        <i class="bi bi-sticky"></i> <?= __('Additional Notes') ?>
                    </h6>
                    <p class="ps-3" data-waiver-upload-wizard-target="reviewAttestNotes"></p>
                </div>
            </div>
        </div>
    </div>
</div>
