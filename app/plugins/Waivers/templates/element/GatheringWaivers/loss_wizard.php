<?php
declare(strict_types=1);

/** Shared loss-report fields and review for desktop and mobile wizards. */
if (empty($canRecordLost)) {
    return;
}
$notesId = !empty($mobile) ? 'lost-notes-mobile' : 'lost-notes';
?>
<?php if (!empty($isReview)) : ?>
<div class="d-none alert alert-warning" data-waiver-upload-wizard-target="reviewLostSection"
    aria-hidden="true" hidden>
    <h4 class="h5"><?= __('Waivers Lost') ?></h4>
    <p>
        <?= __(
            'These waivers were required and reported lost. '
                . 'Collection will remain open until closed as usual.',
        ) ?>
    </p>
    <strong><?= __('Notes:') ?></strong>
    <p class="mb-0" style="white-space: pre-wrap;" data-waiver-upload-wizard-target="reviewLostNotes"></p>
</div>
<?php else : ?>
<div class="d-none" data-waiver-upload-wizard-target="lostSection" aria-hidden="true" hidden>
    <p class="alert alert-warning">
        <?= __(
            'Record a loss reported to the Waiver Deputy by email. This does not close waiver collection.',
        ) ?>
    </p>
    <label for="<?= h($notesId) ?>" class="form-label"><?= __('Notes (Required)') ?></label>
    <p id="<?= h($notesId) ?>-help" class="form-text">
        <?= __(
            'Include who reported the loss, the date reported, and an email reference if useful. '
                . 'Keep the reporting email as the supporting paper trail.',
        ) ?>
    </p>
    <textarea id="<?= h($notesId) ?>" class="form-control" rows="4" maxlength="10000" required disabled
        aria-describedby="<?= h($notesId) ?>-help <?= h($notesId) ?>-error"
        data-waiver-upload-wizard-target="lostNotes"
        data-action="input->waiver-upload-wizard#clearLostNotesError"></textarea>
    <p id="<?= h($notesId) ?>-error" class="text-danger d-none" role="alert"
        data-waiver-upload-wizard-target="lostNotesError"></p>
</div>
<?php endif; ?>
