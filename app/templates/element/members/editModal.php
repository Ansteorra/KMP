<?php

use App\KMP\StaticHelpers;

$canEdit = $user->checkCan("edit", $member);
$canPartialEdit = $user->checkCan("partialEdit", $member);

if ($canEdit) {
    echo $this->Form->create($memberForm, [
        "url" => [
            "controller" => "Members",
            "action" => "edit",
            $member->id,
        ],
        "id" => "edit_entity",
    ]);
} else if ($canPartialEdit) {
    echo $this->Form->create($memberForm, [
        "url" => [
            "controller" => "Members",
            "action" => "partialEdit",
            $member->id,
        ],
        "id" => "edit_entity",
    ]);
}
echo $this->Modal->create("Edit " . $member->sca_name, [
    "id" => "editModal",
    "close" => true,
    "form" => true,
]); ?>
<?php
$emailOptions = [
    'type' => 'email',
    'data-original-value' => $member->email_address,
    'autoSetCustomValidity' => false,
    'data-controller' => 'member-unique-email',
    'data-member-unique-email-url-value' => $this->URL->build([
        'controller' => 'Members',
        'action' => 'emailTaken',
        'plugin' => null,
    ]),
];
$timezoneOptions = [
    'type' => 'select',
    'options' => $this->Timezone->getTimezoneOptions(),
    'label' => 'Timezone',
    'empty' => 'Use Kingdom Default',
    'help' => 'Select the member\'s preferred timezone for displaying dates and times',
];
?>
<div class="row g-3">
    <div class="col-12 col-xl-6">
        <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                <i class="bi bi-person-vcard text-primary me-1" aria-hidden="true"></i>
                <?= __("SCA Identity") ?>
            </legend>
            <?php
            echo $this->Form->control("title");
            echo $this->Form->control("sca_name");
            echo $this->Form->control("pronunciation");
            echo $this->Form->control("pronouns");
            echo $this->KMP->comboBoxControl(
                $this->Form,
                'branch_name',
                'branch_id',
                $treeList,
                "Branch",
                true,
                false,
                [
                    'data-ac-init-selection-value' => json_encode(['value' => $member->branch_id, 'text' => $member->branch->name]),
                ]
            );
            echo $this->Form->control('timezone', $timezoneOptions);
            ?>
        </fieldset>
    </div>
    <div class="col-12  col-xl-6">
        <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                <i class="bi bi-house-heart text-success me-1" aria-hidden="true"></i>
                <?= __("Legal Name & Contact") ?>
            </legend>
            <div class="row g-3">
                <div class="col-12 col-md-6"><?php echo $this->Form->control("first_name"); ?></div>
                <div class="col-12 col-md-6"><?php echo $this->Form->control("middle_name"); ?></div>
                <div class="col-12"><?php echo $this->Form->control("last_name"); ?></div>
                <div class="col-12"><?php echo $this->Form->control("street_address"); ?></div>
                <div class="col-12 col-md-6"><?php echo $this->Form->control("city"); ?></div>
                <div class="col-12 col-md-6"><?php echo $this->Form->control("state"); ?></div>
                <div class="col-12 col-md-6"><?php echo $this->Form->control("zip"); ?></div>
                <div class="col-12 col-md-6"><?php echo $this->Form->control("phone_number"); ?></div>
                <div class="col-12 col-md-6"><?php echo $this->Form->control("email_address", $emailOptions); ?></div>
            </div>
        </fieldset>
    </div>
    <?php if ($user->checkCan("edit", $member)) : ?>
    <div class="col-12">
        <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                <i class="bi bi-card-checklist text-info me-1" aria-hidden="true"></i>
                <?= __("Membership & Safety") ?>
            </legend>
            <?php
                echo $this->Form->control("membership_number");
                echo $this->Form->control("membership_expires_on", [
                    "empty" => true,
                ]);
                echo $this->Form->control("background_check_expires_on", [
                    "empty" => true,
                ]);
                ?>
            <div class="row g-3">
                <div class="col-12 col-md-6"><?php echo $this->Form->control("birth_month"); ?></div>
                <div class="col-12 col-md-6"><?php echo $this->Form->control("birth_year"); ?></div>
            </div>
            <?php
                echo $this->Form->control("status", [
                    'type' => 'select',
                    "options" => $statusList,
                    ""
                ]); ?>
        </fieldset>
    </div>
    <?php endif; ?>
</div>

<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "member-edit-submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]); ?>
<?= $this->Form->end() ?>
<?php if ($memberForm->getErrors()) : ?>
<div data-controller="modal-opener" data-modal-opener-modal-btn-value="editModalBtn"></div>
<?php endif; ?>