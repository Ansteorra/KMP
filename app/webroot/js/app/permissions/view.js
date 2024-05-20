class permissionsView {
    constructor() {
        this.ac = null;

    };
    run(){
        var me = this;
        $("#add_role__role_id").change(function () {
            var end = this.value;
            if (end > 0) {
                $('#add_role__submit').prop('disabled', false);
            }else{
                $('#add_role__submit').prop('disabled', true);
            }
        });
        $('#add_role__submit').click(function(){
            if($('#add_role__role_id').val() > 0){
                $('#add_role__form').submit();
            }
        });
    }
}
var pageControl = new permissionsView();
pageControl.run();
  
        