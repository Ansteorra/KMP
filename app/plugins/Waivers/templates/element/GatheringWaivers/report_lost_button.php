<?php
declare(strict_types=1);
?>
<button type="button" class="btn btn-sm btn-outline-danger"
    data-bs-toggle="modal" data-bs-target="#report-lost-waiver-modal"
    data-waiver-loss-type-id="<?= h($waiverType->id) ?>"
    data-waiver-loss-type-name="<?= h($waiverType->name) ?>"
    aria-label="<?= h(__('Report/Mark Lost: {0}', $waiverType->name)) ?>">
    <?= __('Report/Mark Lost') ?>
</button>
