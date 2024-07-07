<?php
if ($user->can("verifyMembership", "Members") && $needVerification) :
    echo $this->Form->create(null, [
        "url" => ["controller" => "Members", "action" => "verifyMembership", $member->id],
        "id" => "verify__form",
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
            "value" => $member->id,
            "id" => "verify__member_id",
        ]);
        if ($needsParentVerification) {
            if ($needsMemberCardVerification) {
                echo $this->Form->Control("verify_parent", [
                    "type" => "checkbox",
                    "id" => "verify__parent_check",
                    "value" => 1,
                    "checked" => "checked",
                    "onchange" => "$('#verify_member__sca_name').prop('disabled', !this.checked);",
                ]);
            } else {
                echo $this->Form->control("verify_parent", [
                    "type" => "hidden",
                    "value" => 1,
                    "id" => "verify__parent_check",
                ]);
            }
            echo $this->Form->control("sca_name", [
                "type" => "text",
                "label" => "Parent SCA Name",
                "id" => "verify_member__sca_name",
                'required' => true,
            ]);
            echo $this->Form->control("parent_id", [
                "type" => "hidden",
                "id" => "verify_member__parent_id",
            ]);
        }
        if ($needsMemberCardVerification) {
            if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) {
                echo $this->Glide->image($member->membership_card_path, [], ['width' => '400']);
            }
            if ($needsParentVerification) {
                echo $this->Form->control("verify_membership", [
                    "type" => "checkbox",
                    "id" => "verify__membership_check",
                    "value" => 1,
                    "checked" => "checked",
                    "onchange" => "$('#verify__membership_number').prop('disabled', !this.checked); $('#verify__membership_expires_on').prop('disabled', !this.checked);",
                ]);
            } else {
                echo $this->Form->control("verify_membership", [
                    "type" => "hidden",
                    "value" => 1,
                    "id" => "verify__membership_check",
                ]);
            }
            echo $this->Form->control("membership_number", [
                "id" => "verify__membership_number",
                'required' => true,
            ]);
            echo $this->Form->control("membership_expires_on", [
                "type" => "date",
                "id" => "verify__membership_expires_on",
                'required' => true,
                "empty" => true,
            ]);
        }
        ?>
</fieldset>
<?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "verify__submit"
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]);
    echo $this->Form->end();
endif;

?>