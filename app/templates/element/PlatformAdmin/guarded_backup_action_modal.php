<?php
declare(strict_types=1);

/**
 * @var string $modalId
 */

$titleId = $modalId . '-title';
?>
<div
    class="modal fade"
    id="<?= h($modalId) ?>"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    aria-labelledby="<?= h($titleId) ?>"
    aria-hidden="true"
    data-guarded-action-modal-target="modal"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="<?= h($titleId) ?>" class="modal-title h5" data-guarded-action-modal-target="title">
                    <?= __('Approve backup action') ?>
                </h2>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="<?= __('Close') ?>"
                ></button>
            </div>
            <div data-guarded-action-modal-target="content"></div>
        </div>
    </div>
</div>
