<?php
if ($user->checkCan("verifyMembership", "Members") && $needVerification) :
    echo $this->Form->create(null, [
        "url" => ["controller" => "Members", "action" => "verifyMembership", $member->id],
        "data-controller" => "member-verify-form",

    ]);
    echo $this->Modal->create("Verify Membership", [
        "id" => "verifyMembershipModal",
        "close" => true,
    ]);
?>
    <fieldset>
        <?php

        echo $this->Form->control("member_id", [
            "type" => "hidden",
            "value" => $member->id
        ]);
        if ($needsParentVerification) {
            if ($needsMemberCardVerification) {
                echo $this->Form->Control("verify_parent", [
                    "type" => "checkbox",
                    "value" => 1,
                    "checked" => "checked",
                    "data-action" => "member-verify-form#toggleParent",
                ]);
            } else {
                echo $this->Form->control("verify_parent", [
                    "type" => "hidden",
                    "value" => 1,
                ]);
            }
            $url = $this->Url->build([
                'controller' => 'Members',
                'action' => 'AutoComplete',
                'plugin' => null
            ]);
            $this->KMP->autoCompleteControl(
                $this->Form,
                'sca_name',
                'parent_id',
                $url,
                "Parent",
                true,
                false,
                3,
                [
                    'data-member-verify-form-target' => 'scaMember',
                ]
            );
        }
        if ($needsMemberCardVerification) {
            if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) {
                echo $this->Glide->image($member->membership_card_path, [], ['width' => '400']);
            }
            if ($needsParentVerification) {
                echo $this->Form->control("verify_membership", [
                    "type" => "checkbox",
                    "value" => 1,
                    "checked" => "checked",
                    "data-action" => "member-verify-form#toggleMembership",
                ]);
            } else {
                echo $this->Form->control("verify_membership", [
                    "type" => "hidden",
                    "value" => 1,
                ]);
            }
            echo $this->Form->control("membership_number", [
                'required' => true,
                'data-member-verify-form-target' => 'membershipNumber',
            ]);
            echo $this->Form->control("membership_expires_on", [
                "type" => "date",
                'required' => true,
                "empty" => true,
                'data-member-verify-form-target' => 'membershipExpDate',
            ]);
        }
        ?>
    </fieldset>
<?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
            "type" => "button",
        ]),
    ]);
    echo $this->Form->end();
endif; ?>