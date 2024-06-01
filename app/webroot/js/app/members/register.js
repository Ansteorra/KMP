class memberRegister {
    constructor() {
    };
    //onInput for Autocomplete

    getApporversList(me, authId, memberId, elementId) {
        $.get('/members/approvers_list/' + authId + '/' + memberId, function (data) {
            //remove all options
            $('#' + elementId).find('option').remove();
            //add new options
            $('#' + elementId).append('<option value="0"></option>');
            $.each(data, function (key, value) {
                $('#' + elementId).append('<option value="' + value.id + '">' + value.sca_name + ' (' + value.branch.name + ')</option>');
            });
        });
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
        $("#request_auth__auth_type_id").change(function () {
            me.handleAuthIdChange(me, this.value, 'request_auth__approver_id', 'request_auth__member_id');
        });
        $("#request_auth__approver_id").change(function () {
            me.handleApproverIdChange(me, this.value, 'request_auth__submit');
        });
        $('#request_auth__submit').click(function () {
            me.handleSubmitBtnClick(me, 'request_auth__approver_id', 'request_auth__form');
        });
    }
    wireUpRenewalEvents() {
        var me = this;
        $("#renew_auth__auth_type_id").change(function () {
            me.handleAuthIdChange(me, this.value, 'renew_auth__approver_id', 'renew_auth__member_id');
        });
        $("#renew_auth__approver_id").change(function () {
            me.handleApproverIdChange(me, this.value, 'renew_auth__submit');
        });
        $('#renew_auth__submit').click(function () {
            me.handleSubmitBtnClick(me, 'renew_auth__approver_id', 'renew_auth__form');
        });
    }
    run() {
        var me = this;
        me.wireUpRequestEvents();
        me.wireUpRenewalEvents();
        $(document).ready(function () {
            //$("#upload-images").laiImagePreview();
            $("#upload-images").laiImagePreview({
                columns: "col-sm-6 col-md-3",
                inputFileName: "member_card",
                imageCaption: false,
                imageLimit: 1,
                label: "Picture of Membership Card (Optional)",
                maxFileSize: 2000000,
            });
        });
    }
}
var pageControl = new memberRegister();
pageControl.run();

