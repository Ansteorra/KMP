<?php
$user = $this->request->getAttribute("identity");
?>
<button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
    data-bs-target="#requestAuthModal">Request Authorization</button>
<?= $this->Html->link(
    __("Email Link to Mobile Card"),
    ["controller" => "Members", "action" => "SendMobileCardEmail", $id],
    ["class" => "btn btn-sm mb-3 btn-secondary"],
) ?>

<?php if (!empty($pendingAuths) || !empty($currentAuths) || !empty($previousAuths)) {
    $renewButton = [
        "type" => "button",
        "verify" => false,
        "label" => "Renew",
        "options" => [
            "class" => "btn btn-primary",
            "data-bs-toggle" => "modal",
            "data-bs-target" => "#renewalModal",
            "onclick" => "$('#renew_auth__id').val('{{id}}'); $('#renew_auth__auth_type_id').val('{{activity->id}}');$('#renew_auth__auth_type_id').trigger('change');",

        ],
    ];
    $revokeButton = [
        "type" => "button",
        "verify" => true,
        "label" => "Revoke",
        "controller" => "Authorizations",
        "action" => "revoke",
        "options" => [
            "class" => "btn btn-danger",
            "data-bs-toggle" => "modal",
            "data-bs-target" => "#revokeModal",
            "onclick" => "$('#revoke_auth__id').val('{{id}}')",
        ],
    ];
    $activeColumnTemplate = [
        "Authorization" => "activity->name",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Actions" => [
            $renewButton,
            $revokeButton
        ]
    ];
    $pendingColumnTemplate = [
        "Authorization" => "activity->name",
        "Requested Date" => "current_pending_approval->requested_on",
        "Assigned To" => "current_pending_approval->approver->sca_name",
    ];
    $previousColumnTemplate = [
        "Authorization" => "activity->name",
        "Start Date" => "start_on",
        "End Date" => "expires_on",
        "Reason" => "revoked_reason",
    ];
    echo $this->element('activeWindowTabs', [
        'user' => $user,
        'tabGroupName' => "authorizationTabs",
        'tabs' => [
            "active" => [
                "label" => __("Active"),
                "id" => "active-authorization",
                "selected" => true,
                "columns" => $activeColumnTemplate,
                "data" => $currentAuths,
            ],
            "pending" => [
                "label" => __("Pending"),
                "id" => "upcoming-authorization",
                "badge" => count($pendingAuths),
                "badgeClass" => "bg-danger",
                "selected" => false,
                "columns" => $pendingColumnTemplate,
                "data" => $pendingAuths,
            ],
            "previous" => [
                "label" => __("Previous"),
                "id" => "previous-authorization",
                "selected" => false,
                "columns" => $previousColumnTemplate,
                "data" => $previousAuths,
            ]
        ]
    ]);
} else {
    echo "<p>No Authorizations</p>";
} ?>
<?php
$this->KMP->startBlock("modals");
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
echo $this->KMP->startBlock("script"); ?>
<script>
class memberAuthorizations {
    getApporversList(me, authId, memberId, elementId) {
        const serviceUrl =
            '<?= $this->Url->build(['controller' => 'Activities', 'action' => 'ApproversList', "plugin" => "Activities"]) ?>';
        KMP_utils.populateList(serviceUrl, authId, memberId, 'id', 'sca_name', elementId);
    }

    handleAuthIdChange(me, authId, approverElementId, memeberElementId) {
        var memberId = $('#' + memeberElementId).val();
        if (authId > 0) {
            me.getApporversList(me, authId, memberId, approverElementId);
            $('#' + approverElementId).prop('disabled', false);
        } else {
            //remove all options
            $('#' + approverElementId).find('option').remove();
            $('#' + approverElementId).prop('disabled', true);
        }
    }

    handleApproverIdChange(me, approverId, submitBtnId) {
        if (approverId > 0) {
            $('#' + submitBtnId).prop('disabled', false);
        } else {
            $('#' + submitBtnId).prop('disabled', true);
        }
    }

    handleSubmitBtnClick(me, approverElementId, formId) {
        if ($('#' + approverElementId).val() > 0) {
            $('#' + formId).submit();
        }
    }

    wireUpRequestEvents() {
        var me = this;
        $("#request_auth__auth_type_id").change(function() {
            me.handleAuthIdChange(me, this.value, 'request_auth__approver_id', 'request_auth__member_id');
        });
        $("#request_auth__approver_id").change(function() {
            me.handleApproverIdChange(me, this.value, 'request_auth__submit');
        });
        $('#request_auth__submit').click(function() {
            me.handleSubmitBtnClick(me, 'request_auth__approver_id', 'request_auth__form');
        });
    }
    wireUpRenewalEvents() {
        var me = this;
        $("#renew_auth__auth_type_id").change(function() {
            me.handleAuthIdChange(me, this.value, 'renew_auth__approver_id', 'renew_auth__member_id');
        });
        $("#renew_auth__approver_id").change(function() {
            me.handleApproverIdChange(me, this.value, 'renew_auth__submit');
        });
        $('#renew_auth__submit').click(function() {
            me.handleSubmitBtnClick(me, 'renew_auth__approver_id', 'renew_auth__form');
        });
    }
    run() {
        var me = this;
        me.wireUpRequestEvents();
        me.wireUpRenewalEvents();
    }
}
var memberAuth = new memberAuthorizations();
memberAuth.run();
</script>
<?php $this->KMP->endBlock(); ?>