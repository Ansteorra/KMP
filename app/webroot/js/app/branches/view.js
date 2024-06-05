class branchesView {
    constructor() {
        this.ac = null;

    };
    //onInput for Autocomplete
    onInputHandler() {
        $('#assign_officer__member_id').val(0).trigger('change');
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
        this.ac = new Autocomplete($('#assign_officer__sca_name')[0], {
            data: [],
            treshold: 3,
            maximumItems: 8,
            onInput: () => me.onInputHandler(),
            onSelectItem: ({ label, value }) => {
                $('#assign_officer__member_id').val(value).trigger('change');
            }
        });
        $('#assign_officer__member_id').change(function () {
            if ($('#assign_officer__member_id').val() > 0) {
                //enable button
                $('#assign_officer__submit').prop('disabled', false);
            } else {
                //disable button
                $('#assign_officer__submit').prop('disabled', true);
            }
        });
        $('#assign_officer__submit').click(function () {
            if ($('#assign_officer__member_id').val() > 0) {
                $('#assign_officer__form').submit();
            }
        });
    }
}
var pageControl = new branchesView();
pageControl.run();

