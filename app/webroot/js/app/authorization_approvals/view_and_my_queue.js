class authorizationApprovalViewAndMyQueue {
    constructor() {
    };
    //onInput for Autocomplete

    run(rootPath) {
        var me = this;
        me.rootPath = rootPath;
        $("#approve_and_assign_auth_id").change(function () {
            var auth_id = this.value;
            if (auth_id > 0) {
                //query GET: members/approvers_list/{auth_type_id}
                $.get(me.rootPath + 'authorization-approvals/available-approvers-list/' + auth_id, function (data) {
                    //remove all options
                    $('#approve_and_assign_auth_approver_id').find('option').remove();
                    //add new options
                    $('#approve_and_assign_auth_approver_id').append('<option value="0"></option>');
                    $.each(data, function (key, value) {
                        $('#approve_and_assign_auth_approver_id').append('<option value="' + value.id + '">' + value.sca_name + ' (' + value.branch.name + ')</option>');
                    });
                });
                $('#approve_and_assign_auth_approver_id').prop('disabled', false);
            } else {
                //remove all options
                $('#approve_and_assign_auth_approver_id').find('option').remove();
                $('#approve_and_assign_auth_approver_id').prop('disabled', true);
            }
        });
        $("#approve_and_assign_auth_approver_id").change(function () {
            var end = this.value;
            if (end > 0) {
                $('#approve_and_assign_auth__submit').prop('disabled', false);
            } else {
                $('#approve_and_assign_auth__submit').prop('disabled', true);
            }
        });
        $('#approve_and_assign_auth__submit').click(function () {
            if ($('#approve_and_assign_auth_approver_id').val() > 0) {
                $('#approve_and_assign_auth').submit();
            }
        });
    }
}

