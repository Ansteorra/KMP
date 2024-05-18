class rolesView {
    constructor() {
        this.ac = null;

    };
    //onInput for Autocomplete
    onInputHandler(){
        $('#add_member__member_id').val(0).trigger('change');
        var input = this.ac.field.value;
        var me = this;
        //AJAX call to get data
        $.ajax({
            url: '/roles/search_members',
            dataType: 'json',
            type: 'GET',
            data: {q: input},
            success: function(data){
                var sendData = [];
                for (var i = 0; i < data.length; i++) {
                    sendData.push({label: data[i].sca_name, value: data[i].id});
                }
                me.ac.setData(sendData);
            }
        });
    };

    run(){
        var me = this;
        this.ac = new Autocomplete($('#add_member__sca_name')[0], {
            data: [],
            treshold: 3,
            maximumItems: 8,
            onInput: () => me.onInputHandler(),
            onSelectItem: ({label, value}) => {
                $('#add_member__member_id').val(value).trigger('change');
            }
        });
        $('#add_member__member_id').change(function(){
            if($('#add_member__member_id').val() > 0){
                //enable button
                $('#add_member__submit').prop('disabled', false);
            }else{
                //disable button
                $('#add_member__submit').prop('disabled', true);
            }
        });
        $('#add_member__submit').click(function(){
            if($('#add_member__member_id').val() > 0){
                $('#add_member__form').submit();
            }
        });
        $("#add_permission__permission_id").change(function () {
            var end = this.value;
            if (end > 0) {
                $('#add_permission__submit').prop('disabled', false);
            }else{
                $('#add_permission__submit').prop('disabled', true);
            }
        });
        $('#add_permission__submit').click(function(){
            if($('#add_permission__permission_id').val() > 0){
                $('#add_permission__form').submit();
            }
        });
    }
}
rolesView = new rolesView();
rolesView.run();
  
        