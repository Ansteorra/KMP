<?php

$canPartialEdit = $user->checkCan("partialEdit", $member);
$uploadLimits = $this->KMP->getUploadLimits();

if ($canPartialEdit) {
    echo $this->Form->create($memberForm, [
        "url" => [
            "controller" => "Members",
            "action" => "uploadProfilePhoto",
        ],
        "id" => "submit_profile_photo",
        "type" => "file",
        "data-controller" => "face-photo-validator",
        "data-face-photo-validator-model-base-url-value" => $this->Url->build('/models/face-api'),
        "data-face-photo-validator-min-width-value" => 320,
        "data-face-photo-validator-min-height-value" => 320,
        "data-face-photo-validator-min-primary-face-ratio-value" => 0.08,
        "data-face-photo-validator-debug-value" => "true",
    ]);
    echo $this->Form->hidden('member_id', ['value' => $member->id]);
}
echo $this->Modal->create(__("Update Profile Photo"), [
    "id" => "profilePhotoModal",
    "close" => true,
]); ?>
<fieldset>
    <?php if ($canPartialEdit): ?>
    <div class="mb-3 form-group">
        <label class="form-label"><?= __("Upload Face Photo") ?></label>
        <div class="card" data-controller="image-preview file-size-validator"
            data-image-preview-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
            data-image-preview-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
            data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
            data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">

            <div data-file-size-validator-target="warning" class="d-none m-2"></div>
            <div data-face-photo-validator-target="warning" class="d-none m-2"></div>

            <div class="card-body text-center">
                <svg class="bi bi-person-square text-secondary text-center" width="200" height="200" fill="currentColor"
                    viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" data-image-preview-target="loading">
                    <path d="M9 11c0 1-1 1-1 1s-1 0-1-1 1-2 1-2 1 1 1 2" />
                    <path
                        d="M2 2a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zm10.5 3a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M4.5 7a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0M8 8a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m-5 5.5a3.5 3.5 0 0 1 7 0z" />
                </svg>
                <img src="#" hidden alt="<?= __('Profile photo preview') ?>" class="w-100"
                    data-image-preview-target="preview">
            </div>
            <div class="card-footer">
                <input type="file" name="profile_photo" class="form-control" accept="image/*"
                    data-image-preview-target="file" data-file-size-validator-target="fileInput"
                    data-face-photo-validator-target="fileInput"
                    data-action="change->image-preview#preview change->file-size-validator#validateFiles change->face-photo-validator#validateFile">
                <small
                    class="text-muted d-block mt-1"><?= __('Use a clear, front-facing photo without mask or helmet.') ?></small>
                <small class="text-muted d-block"><?= __('Max size: {0}', h($uploadLimits['formatted'])) ?></small>
                <small class="text-muted d-block mt-1">
                    <?= __('Visible on your profile and mobile auth card. Others can only see it if you show your card or they are an officer with view members permissions.') ?>
                </small>
            </div>
        </div>
    </div>
    <?php endif ?>
</fieldset>
<?php
$modalButtons = [];
if ($canPartialEdit) {
    $modalButtons[] = $this->Form->button(__("Save Photo"), [
        "class" => "btn btn-primary",
        "id" => "submit-profile-photo",
        "data-face-photo-validator-target" => "submitButton",
        "disabled" => true,
    ]);
}
$modalButtons[] = $this->Form->button(__("Close"), [
    "data-bs-dismiss" => "modal",
    "type" => "button",
]);
echo $this->Modal->end($modalButtons);
if ($canPartialEdit) {
    echo $this->Form->end();
}