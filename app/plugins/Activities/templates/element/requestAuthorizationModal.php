 <?php echo $this->Modal->create("Request Authorization", [
        "id" => "requestAuthModal",
        "close" => true,
    ]); ?>
 <fieldset>
     <?php
        echo $this->Form->create(null, [
            "id" => "request_auth__form",
            "url" => ["controller" => "Authorizations", "action" => "add"],
        ]);
        echo $this->Form->control("member_id", [
            "type" => "hidden",
            "value" => $id,
            "id" => "request_auth__member_id",
        ]);
        echo $this->Form->control("activity", [
            "options" => $activities,
            "empty" => true,
            "id" => "request_auth__auth_type_id",
            "label" => "Activity",
        ]);
        echo $this->Form->control("approver_id", [
            "type" => "select",
            "options" => [],
            "id" => "request_auth__approver_id",
            "label" => "Send Request To",
            "disabled" => "disabled",
        ]);
        echo $this->Form->end();
        ?>
 </fieldset>
 <?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "request_auth__submit",
            "disabled" => "disabled",
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]); ?>