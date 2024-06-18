<?php if ($user->can("release", "Officers.Officers")) {
    echo $this->Modal->create("Release Office", [
        "id" => "releaseModal",
        "close" => true,
    ]); ?>
<fieldset>
    <?php
        echo $this->Form->create(null, [
            "url" => ["controller" => "Officers", "action" => "release"],
            "id" => "release_officer",
        ]);
        echo $this->Form->control("id", [
            "type" => "hidden",
            "id" => "release_officer__id",
        ]);
        echo $this->Form->control("revoked_reason", [
            "label" => "Reason for Release",
            "onkeypress" => '$("#release_officer__submit").removeAttr("disabled");',
        ]);
        echo $this->Form->end();
        ?>
</fieldset>
<?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "release_officer__submit",
            "onclick" => '$("#release_officer").submit();',
            "disabled" => "disabled",
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]);
} ?>