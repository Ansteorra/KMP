class memberView {
    constructor() {
        this.ac = null;

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


    onInputHandler() {
        $('#verify_member__parent_id').val(0).trigger('change');
        var input = this.ac.field.value;
        var me = this;
        //AJAX call to get data
        $.ajax({
            url: '/members/search_members',
            dataType: 'json',
            type: 'GET',
            data: { q: input },
            success: function (data) {
                var sendData = [];
                for (var i = 0; i < data.length; i++) {
                    sendData.push({ label: data[i].sca_name, value: data[i].id });
                }
                me.ac.setData(sendData);
            }
        });
    };

    run() {
        var me = this;
        if ($('#verify_member__sca_name').length > 0) {
            this.ac = new Autocomplete($('#verify_member__sca_name')[0], {
                data: [],
                treshold: 3,
                maximumItems: 8,
                onInput: () => me.onInputHandler(),
                onSelectItem: ({ label, value }) => {
                    $('#verify_member__parent_id').val(value).trigger('change');
                }
            });
        }
        me.wireUpRequestEvents();
        me.wireUpRenewalEvents();
    }
}
var pageControl = new memberView();
pageControl.run();

