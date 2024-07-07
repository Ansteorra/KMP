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
if (!$isEmpty) {
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
} else {
    echo "<p>No Authorizations</p>";
} ?>
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

    handleFormSubmit(e, me, approverElementId, formId) {
        if (!$('#' + approverElementId).val() > 0) {
            e.preventDefault();
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
        $('#request_auth__form').on('submit', function() {
            me.handleFormSubmit(me, 'request_auth__approver_id', 'request_auth__form');
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
        $('#renew_auth__form').on('submit', function(e) {
            me.handleFormSubmit(e, me, 'renew_auth__approver_id', 'renew_auth__form');
        });
    }
    run() {
        var me = this;
        me.wireUpRequestEvents();
        me.wireUpRenewalEvents();
    }
}
window.addEventListener('DOMContentLoaded', function() {
    var memberAuth = new memberAuthorizations();
    memberAuth.run();
});
</script>
<?php $this->KMP->endBlock(); ?>