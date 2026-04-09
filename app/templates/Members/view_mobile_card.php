<?php

/**
 * Mobile Card View - Using mobile_app Layout
 * 
 * This template shows the member's mobile activity authorization card.
 * All the PWA infrastructure, menu, and styling is provided by the mobile_app layout.
 * This view only contains the card-specific content.
 * 
 * The member-mobile-card-profile controller is initialized in the layout.
 */

use Cake\I18n\Date;

$now = Date::now();
$uploadLimits = $this->KMP->getUploadLimits();
?>

<div class="card cardbox mx-3 mt-3" data-section="auth-card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="fs-5 text-muted"><?= h($message_variables["kingdom"]) ?> Activity Authorization</span>
            <button type="button" class="btn btn-link btn-sm text-decoration-none mobile-card-photo-manage-btn"
                hidden
                data-member-mobile-card-profile-target="photoManageButton"
                data-bs-toggle="modal" data-bs-target="#mobileCardPhotoUploadModal">
                <i class="bi bi-camera me-1"></i><?= __('Photo') ?>
            </button>
        </div>
        <div class="text-center py-4" data-member-mobile-card-profile-target="loading">
            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading your card...</p>
        </div>
        <div class="text-center mb-3" hidden data-member-mobile-card-profile-target="profilePhotoContainer">
            <button type="button" class="btn p-0 border-0 bg-transparent mobile-card-photo-trigger"
                data-bs-toggle="modal" data-bs-target="#mobileCardPhotoZoomModal"
                aria-label="<?= __('Zoom profile photo') ?>">
                <img src="" alt="<?= __('Profile photo') ?>"
                    class="img-thumbnail rounded-circle mobile-card-photo-thumb"
                    data-member-mobile-card-profile-target="profilePhoto">
            </button>
            <div class="small text-muted mt-2"><?= __('Tap photo to zoom') ?></div>
        </div>
        <dl class="row mb-0" hidden data-member-mobile-card-profile-target="memberDetails">
            <dt class="col-5 text-end py-2">Legal Name</dt>
            <dd class="col-7 py-2 fw-medium" data-member-mobile-card-profile-target="name"></dd>
            <dt class="col-5 text-end py-2">Society Name</dt>
            <dd class="col-7 py-2 fw-medium" data-member-mobile-card-profile-target="scaName"></dd>
            <dt class="col-5 text-end py-2">Branch</dt>
            <dd class="col-7 py-2" data-member-mobile-card-profile-target="branchName"></dd>
            <dt class="col-5 text-end py-2">Membership</dt>
            <dd class="col-7 py-2" data-member-mobile-card-profile-target="membershipInfo"></dd>
            <dt class="col-5 text-end py-2">Background Check</dt>
            <dd class="col-7 py-2" data-member-mobile-card-profile-target="backgroundCheck"></dd>
            <dt class="col-5 text-end py-2 text-muted small">Last Refresh</dt>
            <dd class="col-7 py-2 text-muted small" data-member-mobile-card-profile-target="lastUpdate"></dd>
        </dl>
    </div>
</div>
<div id="pluginCards" class="mt-3" data-member-mobile-card-profile-target="cardSet"></div>

<?= $this->Form->create(null, [
    'url' => ['controller' => 'Members', 'action' => 'mobileCardUploadProfilePhoto'],
    'type' => 'file',
    'data-controller' => 'face-photo-validator',
    'data-face-photo-validator-model-base-url-value' => $this->Url->build('/models/face-api'),
    'data-face-photo-validator-min-width-value' => 320,
    'data-face-photo-validator-min-height-value' => 320,
    'data-face-photo-validator-min-primary-face-ratio-value' => 0.08,
]) ?>
<div class="modal fade" id="mobileCardPhotoUploadModal" tabindex="-1" aria-labelledby="mobileCardPhotoUploadModalLabel"
    data-member-mobile-card-profile-target="photoUploadModal"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mobileCardPhotoUploadModalLabel"><?= __('Upload Profile Photo') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="card" data-controller="image-preview file-size-validator"
                    data-image-preview-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
                    data-image-preview-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
                    data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
                    data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">
                    <div data-file-size-validator-target="warning" class="d-none m-2"></div>
                    <div data-face-photo-validator-target="warning" class="d-none m-2"></div>
                    <div class="card-body text-center">
                        <svg class="bi bi-person-square text-secondary text-center" width="180" height="180"
                            fill="currentColor" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"
                            data-image-preview-target="loading">
                            <path d="M9 11c0 1-1 1-1 1s-1 0-1-1 1-2 1-2 1 1 1 2" />
                            <path
                                d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm10.5 3a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M4.5 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M8 8a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m-5 5.5a3.5 3.5 0 0 1 7 0z" />
                        </svg>
                        <img hidden alt="<?= __('Profile photo preview') ?>" class="w-100"
                            data-image-preview-target="preview">
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2" data-controller="mobile-photo-source">
                            <button type="button" class="btn btn-primary"
                                data-action="click->mobile-photo-source#chooseCamera">
                                <i class="bi bi-camera-fill me-1"></i><?= __('Take Photo') ?>
                            </button>
                            <button type="button" class="btn btn-outline-secondary"
                                data-action="click->mobile-photo-source#chooseGallery">
                                <i class="bi bi-images me-1"></i><?= __('Choose from Gallery') ?>
                            </button>
                            <input type="file" name="profile_photo" class="d-none" accept="image/*"
                                data-mobile-photo-source-target="fileInput" data-image-preview-target="file"
                                data-file-size-validator-target="fileInput" data-face-photo-validator-target="fileInput"
                                data-action="change->image-preview#preview change->file-size-validator#validateFiles change->face-photo-validator#validateFile">
                        </div>
                        <small
                            class="text-muted d-block mt-1"><?= __('Use a clear, front-facing photo without mask or helmet.') ?></small>
                        <small
                            class="text-muted d-block"><?= __('Max size: {0}', h($uploadLimits['formatted'])) ?></small>
                        <small class="text-muted d-block mt-1">
                            <?= __('Visible on your profile and mobile auth card. Others can only see it if they are an officer with view members permissions.') ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"><?= __('Close') ?></button>
                <?= $this->Form->button(__('Save Photo'), [
                    'class' => 'btn btn-primary',
                    'data-face-photo-validator-target' => 'submitButton',
                    'disabled' => true,
                ]) ?>
            </div>
        </div>
    </div>
</div>
<?= $this->Form->end() ?>

<div class="modal fade mobile-photo-zoom-modal" id="mobileCardPhotoZoomModal" tabindex="-1"
    aria-labelledby="mobileCardPhotoZoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-sm-down">
        <div class="modal-content mobile-photo-zoom-content">
            <div class="modal-header mobile-photo-zoom-header">
                <h5 class="modal-title" id="mobileCardPhotoZoomModalLabel"><?= __('Profile Photo') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body mobile-photo-zoom-body">
                <div data-controller="image-zoom" class="mobile-photo-zoom-stage">
                    <img src="" alt="<?= __('Profile photo zoom') ?>" class="mobile-photo-zoom-image"
                        data-image-zoom-target="image" data-member-mobile-card-profile-target="zoomPhoto">
                </div>
                <small
                    class="mobile-photo-zoom-hint d-block mt-2"><?= __('Pinch/scroll to zoom · Drag to pan · Double-tap/click to reset') ?></small>
            </div>
        </div>
    </div>
</div>
