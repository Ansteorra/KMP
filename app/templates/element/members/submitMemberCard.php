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
        <i class="bi bi-card-image text-primary me-1" aria-hidden="true"></i>
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
                    <svg class="bi bi-card-image text-secondary text-center"
                        width="200" height="200" fill="currentColor"
                        viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" data-image-preview-target="loading">
                        <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0" />
                        <path
                            d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13
                                a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2zm13 1a.5.5 0 0 1 .5.5v6
                                l-3.775-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062
                                L1.002 12v.54L1 12.5v-9a.5.5 0 0 1 .5-.5z" />
                    </svg>
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
