<?php

use App\KMP\StaticHelpers;

echo $this->Modal->create("Edit " . $member->sca_name, [
    "id" => "editModal",
    "close" => true,
]); ?>
<fieldset>
    <?php if ($user->can("edit", $member)) {
        echo $this->Form->create($memberForm, [
            "url" => [
                "controller" => "Members",
                "action" => "edit",
                $member->id,
            ],
            "id" => "edit_entity",
        ]);
        echo $this->Form->control("sca_name");
        echo $this->Form->control("membership_number");
        echo $this->Form->control("membership_expires_on", [
            "empty" => true,
        ]);
        echo $this->Form->control("branch_id", ["options" => $treeList]);
        echo $this->Form->control("first_name");
        echo $this->Form->control("middle_name");
        echo $this->Form->control("last_name");
        echo $this->Form->control("street_address");
        echo $this->Form->control("city");
        echo $this->Form->control("state");
        echo $this->Form->control("zip");
        echo $this->Form->control("phone_number");
        echo $this->Form->control("email_address");
        echo $this->Form->control("background_check_expires_on", [
            "empty" => true,
        ]);
        echo $member->age < 18 ? $this->Form->control("parent_name") : "";
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
            echo $this->Form->control("email_address");
            echo $member->age < 18
                ? $this->Form->control("parent_name")
                : "";
        }
    } ?>
</fieldset>
<?= $this->Form->end() ?>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
        "onclick" => '$("#edit_entity").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>