<?php
$user = $this->request->getAttribute("identity");
?>
<button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
    data-bs-target="#requestAuthModal">Request Authorization</button>
<?= $this->Form->postLink(
    __("Email Link to Mobile Card"),
    ["controller" => "Members", "action" => "SendMobileCardEmail", $id],
    ["class" => "btn btn-sm mb-3 btn-secondary"],
) ?>

<?php
if (!$isEmpty) :
    echo $this->element('turboActiveTabs', [
        'user' => $user,
        'tabGroupName' => "authorizationTabs",
        'tabs' => [
            "active" => [
                "label" => __("Active"),
                "id" => "current-authorization",
                "selected" => true,
                "turboUrl" => $this->URL->build(["controller" => "Authorizations", "action" => "MemberAuthorizations", "plugin" => "Activities", "current", $id])
            ],
            "pending" => [
                "label" => __("Pending"),
                "id" => "pending-authorization",
                "badge" => $pendingAuthCount,
                "badgeClass" => "bg-danger",
                "selected" => false,
                "turboUrl" => $this->URL->build(["controller" => "Authorizations", "action" => "MemberAuthorizations", "plugin" => "Activities", "pending", $id])
            ],
            "previous" => [
                "label" => __("Previous"),
                "id" => "previous-authorization",
                "selected" => false,
                "turboUrl" => $this->URL->build(["controller" => "Authorizations", "action" => "MemberAuthorizations", "plugin" => "Activities", "previous", $id])
            ]
        ]
    ]);
else :
    echo "<p>No Authorizations</p>";
endif; ?>
<?php
echo $this->KMP->startBlock("modals");
echo $this->element('requestAuthorizationModal', [
    'user' => $user,
]);
echo $this->element('revokeAuthorizationModal', [
    'user' => $user,
]);
echo $this->element('renewAuthorizationModal', [
    'user' => $user,
]);
$this->KMP->endBlock();
?>