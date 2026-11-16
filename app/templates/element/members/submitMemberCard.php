<?php
declare(strict_types=1);

$canPartialEdit = $user->checkCan('partialEdit', $member);

// Get PHP upload limits for client-side validation
$uploadLimits = $this->KMP->getUploadLimits();
$membershipCardAccept = implode(',', [
    '.jpg',
    '.jpeg',
    '.jpe',
    '.jfif',
    '.pjpeg',
    '.pjp',
    '.png',
    '.gif',
    '.webp',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
]);

if ($canPartialEdit) {
    echo $this->Form->create($memberForm, [
        'url' => [
            'controller' => 'Members',
            'action' => 'submitScaMemberInfo',
        ],
        'id' => 'submit_member_card',
        'type' => 'file',
    ]);
    echo $this->Form->hidden('member_id', ['value' => $member->id]);
}
echo $this->Modal->create('Submit your SCA Card ', [
    'id' => 'submitMemberCardModal',
    'close' => true,
    'form' => true,
    'size' => 'modal-lg',
]); ?>
<fieldset class="border rounded-3 bg-white shadow-sm p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <kmp-icon name="photo" class="text-primary me-1" aria-hidden="true"></kmp-icon>
        <?= __('Membership Card') ?>
    </legend>
    <?php if ($user->checkCan('partialEdit', $member)) : ?>
        <div class="mb-3 form-group">
            <label class="form-label" for="member-card-upload"><?= __('Upload Membership Card') ?></label>
            <div class="card" data-controller="image-preview file-size-validator"
                data-image-preview-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
                data-image-preview-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>"
                data-file-size-validator-max-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
                data-file-size-validator-max-size-formatted-value="<?= h($uploadLimits['formatted']) ?>">

                <!-- Warning message container -->
                <div data-file-size-validator-target="warning" class="d-none m-2"></div>

                <div class="card-body text-center">
                    <kmp-icon name="photo" class="text-secondary text-center" style="--kmp-icon-size:200px" data-image-preview-target="loading" aria-hidden="true"></kmp-icon>
                    <img hidden alt="Image Preview" class="w-100" data-image-preview-target="preview">
                </div>
                <div class="card-footer">
                    <input type="file" name="member_card" id="member-card-upload" class="form-control"
                        accept="<?= h($membershipCardAccept) ?>"
                        aria-describedby="membership-card-upload-help"
                        data-image-preview-target="file" data-file-size-validator-target="fileInput"
                        data-action="change->image-preview#preview change->file-size-validator#validateFiles">
                    <small id="membership-card-upload-help" class="text-muted d-block mt-1">
                        <?= __('JPEG/JFIF, PNG, GIF, or WebP. Max size: {0}', h($uploadLimits['formatted'])) ?>
                    </small>
                </div>
            </div>
        </div>
    <?php endif ?>
</fieldset>

<?php echo $this->Modal->end([
    $this->Form->button('Submit', [
        'class' => 'btn btn-primary',
        'id' => 'submit-member-card-submit',
    ]),
    $this->Form->button('Close', [
        'data-bs-dismiss' => 'modal',
        'type' => 'button',
    ]),
]); ?>
<?= $this->Form->end() ?>
<?php if ($memberForm->getErrors()) : ?>
    <div data-controller="modal-opener" data-modal-opener-modal-btn-value="scaCardUploadBtn"></div>
<?php endif; ?>
