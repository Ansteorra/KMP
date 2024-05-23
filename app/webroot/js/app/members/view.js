class memberView {
    constructor() {
    };
    //onInput for Autocomplete

    run(){
        $("#request_auth__auth_type_id").change(function () {
            var auth_id = this.value;
            var member_id = $('#request_auth__member_id').val();
            if (auth_id > 0) {
                //query GET: members/approvers_list/{auth_type_id}
                $.get('/members/approvers_list/'+auth_id+'/'+member_id, function(data){
                    //remove all options
                    $('#request_auth__approver_id').find('option').remove();
                    //add new options
                    $('#request_auth__approver_id').append('<option value="0"></option>');
                    $.each(data, function(key, value){
                        $('#request_auth__approver_id').append('<option value="'+value.id+'">'+value.sca_name+' ('+value.branch.name+')</option>');
                    });
                });
                $('#request_auth__approver_id').prop('disabled', false);
            }else{
                //remove all options
                $('#request_auth__approver_id').find('option').remove();
                $('#request_auth__approver_id').prop('disabled', true);
            }
        });
        $("#request_auth__approver_id").change(function () {
            var end = this.value;
            if (end > 0) {
                $('#request_auth__submit').prop('disabled', false);
            }else{
                $('#request_auth__submit').prop('disabled', true);
            }
        });
        $('#request_auth__submit').click(function(){
            if($('#request_auth__approver_id').val() > 0){
                $('#request_auth__form').submit();
            }
        });
    }
}
var pageControl = new memberView();
pageControl.run();
  
        