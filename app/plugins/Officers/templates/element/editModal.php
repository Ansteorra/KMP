<?php

echo $this->Form->create(null, [
    "id" => "edit_officer__form",
    "url" => [
        "controller" => "Officers",
        "action" => "edit",
    ],
    "data-turbo" => "true",
    "data-controller" => "turbo-modal officers-edit-officer page-context",
    "data-action" => implode(" ", [
        'submit->officers-edit-officer#validateForm',
        "submit->turbo-modal#submitAsTurboStream",
        "turbo:submit-start->turbo-modal#closeModalBeforeSubmit",
    ]),
    "data-officers-edit-officer-outlet-btn-outlet" => ".edit-btn",
]);
echo $this->Form->hidden('page_context_url', ['value' => '']);
echo $this->Modal->create("Edit Officer", [
    "id" => "editOfficerModal",
    "close" => true,
    "form" => true,
    "size" => "modal-lg",
]);
?>
<div class="d-none mb-3" data-turbo-modal-feedback></div>
<fieldset class="border rounded-3 bg-white shadow-sm p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-person-badge text-primary me-1" aria-hidden="true"></i>
        <?= __("Officer Details") ?>
    </legend>
    <?php
    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "edit-officer-id",
        "data-officers-edit-officer-target" => "id",
    ]); ?>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="mb-3 form-group text" data-officers-edit-officer-target="deputyDescBlock">
                <label class="form-label" for="edit_officer__deputy_description">
                    <?= __("Deputy Description") ?>
                </label>
                <input type="text" name="deputy_description" id="edit_officer__deputy_description"
                    class="form-control" maxlength="255"
                    data-officers-edit-officer-target="deputyDesc">
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="mb-3 form-group email">
                <label class="form-label" for="edit_officer__email_address">
                    <?= __('Office Email Address') ?>
                </label>
                <input type="email" name="email_address" id="edit_officer__email_address"
                    class="form-control" value="" maxlength="255"
                    aria-describedby="edit_officer__email_address_help"
                    data-officers-edit-officer-target="emailAddress">
                <div id="edit_officer__email_address_help" class="form-text">
                    <?= __('Optional contact address for this office assignment.') ?>
                </div>
            </div>
        </div>
    </div>
</fieldset>
<fieldset class="border rounded-3 bg-white shadow-sm p-3 mt-3">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
        <i class="bi bi-calendar-range text-primary me-1" aria-hidden="true"></i>
        <?= __('Officer Term') ?>
    </legend>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="mb-3 form-group date">
                <label class="form-label" for="edit_officer__start_on">
                    <?= __('Start Date') ?>
                    <span class="text-danger"><?= __('(required)') ?></span>
                </label>
                <input type="date" name="start_on" id="edit_officer__start_on" class="form-control" value="" required
                    aria-describedby="edit_officer__term_dates_error"
                    data-officers-edit-officer-target="startOn"
                    data-action="input->officers-edit-officer#termDatesChanged
                        change->officers-edit-officer#termDatesChanged">
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="mb-3 form-group date">
                <label class="form-label" for="edit_officer__expires_on">
                    <?= __('End Date') ?>
                    <span class="text-body-secondary"><?= __('(optional)') ?></span>
                </label>
                <input type="date" name="expires_on" id="edit_officer__expires_on" class="form-control" value=""
                    aria-describedby="edit_officer__term_dates_error"
                    data-officers-edit-officer-target="expiresOn"
                    data-action="input->officers-edit-officer#termDatesChanged
                        change->officers-edit-officer#termDatesChanged">
            </div>
        </div>
    </div>
    <div id="edit_officer__term_dates_error" class="alert alert-danger py-2 d-none" role="alert"
        data-officers-edit-officer-target="termDatesError">
        <?= __('The end date must be on or after the start date.') ?>
    </div>
    <div class="mb-3 form-group text">
        <label class="form-label" for="edit_officer__term_note">
            <?= __('Term Change Note') ?>
            <span class="text-danger d-none" data-officers-edit-officer-target="termNoteRequired">
                <?= __('(required)') ?>
            </span>
        </label>
        <textarea name="term_note" id="edit_officer__term_note" class="form-control" rows="3"
            aria-describedby="edit_officer__term_note_help" aria-required="false"
            data-officers-edit-officer-target="termNote"></textarea>
        <div id="edit_officer__term_note_help" class="form-text">
            <?= __('Required when changing the start or end date.') ?>
        </div>
    </div>
    <section aria-labelledby="edit_officer__term_notes_heading">
        <h3 id="edit_officer__term_notes_heading" class="h6">
            <?= __('Existing Term Notes') ?>
        </h3>
        <p class="mb-0 text-body-secondary" data-officers-edit-officer-target="termNotesEmpty">
            <?= __('No term notes have been recorded.') ?>
        </p>
        <ul class="list-group d-none" data-officers-edit-officer-target="termNotesList"></ul>
    </section>
    <p class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"
        data-officers-edit-officer-target="status"></p>
</fieldset>
<?php

echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>
