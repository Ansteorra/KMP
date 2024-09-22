<?php

use App\KMP\StaticHelpers;

echo $this->Form->create($memberForm, [
    "url" => [
        "controller" => "Members",
        "action" => "edit",
        $member->id,
    ],
    "id" => "edit_entity",
]);
echo $this->Modal->create("Edit " . $member->sca_name, [
    "id" => "editModal",
    "close" => true,
]); ?>
<fieldset>
    <?php if ($user->can("edit", $member)) {
        echo $this->Form->control("sca_name");
        echo $this->Form->control("membership_number");
        echo $this->Form->control("membership_expires_on", [
            "empty" => true,
        ]);
        //initial value needs to be json and then made safe to be in an html attribute
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
        echo $this->Form->control("first_name");
        echo $this->Form->control("middle_name");
        echo $this->Form->control("last_name");
        echo $this->Form->control("street_address");
        echo $this->Form->control("city");
        echo $this->Form->control("state");
        echo $this->Form->control("zip");
        echo $this->Form->control("phone_number");
        echo $this->Form->control("email_address", [
            'type' => 'email',
            'data-original-value' => $member->email_address,
            'autoSetCustomValidity' => false,
            'data-controller' => 'member-unique-email',
            'data-member-unique-email-url-value' => $this->URL->build([
                'controller' => 'Members',
                'action' => 'emailTaken',
                'plugin' => null,
            ]),
        ]);
        echo $this->Form->control("background_check_expires_on", [
            "empty" => true,
        ]);
        echo $this->Form->control("birth_month");
        echo $this->Form->control("birth_year");
        echo $this->Form->control("status", [
            'type' => 'select',
            "options" => $statusList,
            ""
        ]);
    } else {
        if ($user->can("partialEdit", $member)) {
            echo $this->Form->create($memberForm, [
                "url" => [
                    "controller" => "Members",
                    "action" => "partialEdit",
                    $member->id,
                ],
                "id" => "edit_entity",
            ]);
            echo $this->Form->control("sca_name");
            echo $this->Form->control("branch_id", [
                "options" => $treeList,
            ]);
            echo $this->Form->control("first_name");
            echo $this->Form->control("middle_name");
            echo $this->Form->control("last_name");
            echo $this->Form->control("street_address");
            echo $this->Form->control("city");
            echo $this->Form->control("state");
            echo $this->Form->control("zip");
            echo $this->Form->control("phone_number");
        }
    } ?>
</fieldset>

<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
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