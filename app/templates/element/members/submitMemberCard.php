<?php

use App\KMP\StaticHelpers;

$canPartialEdit = $user->checkCan("partialEdit", $member);

// Get PHP upload limits for client-side validation
$uploadLimits = $this->KMP->getUploadLimits();

if ($canPartialEdit) {
    echo $this->Form->create($memberForm, [
        "url" => [
            "controller" => "Members",
            "action" => "submitScaMemberInfo",
        ],
        "id" => "submit_member_card",
        "type" => "file"
    ]);
}
echo $this->Modal->create("Submit your SCA Card ", [
    "id" => "submitMemberCardModal",
    "close" => true,
]); ?>
<fieldset>
    <?php if ($user->checkCan("partialEdit", $member)): ?>
        <div class="mb-3 form-group">
            <label class="form-label">Upload Membership Card</label>
            <div class="card" data-controller="image-preview file-size-validator"
                data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
                data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">

                <!-- Warning message container -->
                <div data-file-size-validator-target="warning" class="d-none m-2"></div>

                <div class="card-body text-center">
                    <svg class="bi bi-card-image text-secondary text-center" width="200" height="200" fill="currentColor"
                        viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" data-image-preview-target="loading">
                        <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0" />
                        <path
                            d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2zm13 1a.5.5 0 0 1 .5.5v6l-3.775-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12v.54L1 12.5v-9a.5.5 0 0 1 .5-.5z" />
                    </svg>
                    <img src="#" hidden alt="Image Preview" class="w-100" data-image-preview-target="preview">
                </div>
                <div class="card-footer">
                    <input type="file" name="member_card" class="form-control" accept="image/*"
                        data-image-preview-target="file" data-file-size-validator-target="fileInput"
                        data-action="change->image-preview#preview change->file-size-validator#validateFiles">
                    <small class="text-muted d-block mt-1">Max size: <?= h($uploadLimits['formatted']) ?></small>
                </div>
            </div>
        </div>
    <?php endif ?>
</fieldset>

<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "submit-member-card-submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]); ?>
<?= $this->Form->end() ?>
<?php if ($memberForm->getErrors()) : ?>
    <div data-controller="modal-opener" data-modal-opener-modal-btn-value="scaCardUploadBtn"></div>
<?php endif; ?>