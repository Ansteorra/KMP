<?php echo $this->KMP->startBlock("script"); ?>
<script>
class recommendationsAdd {
    constructor() {
        this.ac = null;
    };
    //onInput for Autocomplete
    nameNotFound() {
        var notFound = $('#recommendation__not_found');
        var branch = $('#recommendation__branch_id').parent();
        notFound.prop('checked', true);
        branch.removeClass('d-none');
        $('#recommendation_submit').prop('disabled', false);
    }
    searchHadResults() {
        var notFound = $('#recommendation__not_found');
        var branch = $('#recommendation__branch_id').parent();
        notFound.prop('checked', false);
        branch.addClass('d-none');
    }

    getPublicProfile(memberId) {
        var url =
            '<?= $this->URL->build(['controller' => 'Members', 'action' => 'PublicProfile', 'plugin' => null]) ?>/' +
            memberId;
        $.get(url, function(data) {
            if (data) {
                var memberLinks = $('#member_links');
                memberLinks.empty();
                $("#recommendation__call_into_court").prop('disabled', false);
                $("#recommendation__court_availability").prop('disabled', false);
                if (data["additional_info"]) {
                    var callIntoCourt = data["additional_info"]["CallIntoCourt"];
                    var courtAvailability = data["additional_info"]["CourtAvailability"];
                    if (callIntoCourt && callIntoCourt != "") {
                        $("#recommendation__call_into_court").val(callIntoCourt);
                    }
                    if (courtAvailability && courtAvailability != "") {
                        $("#recommendation__court_availability").val(courtAvailability);
                    }
                }
            }
        });
    }

    run() {
        $('#recommendation__branch_id').parent().addClass('d-none');
        $('#recommendation__given').parent().addClass('d-none');
        var me = this;
        var searchUrl =
            '<?= $this->URL->build(['controller' => 'Members', 'action' => 'SearchMembers', 'plugin' => null]) ?>';
        KMP_utils.configureAutoComplete(me.ac, searchUrl, 'recommendation__sca_name', 'id', 'sca_name',
            'recommendation__member_id', me.searchHadResults, me.nameNotFound);


        $('#recommendation__member_id').change(function() {
            if ($('#recommendation__member_id').val() > 0) {
                //enable button
                var notFound = $('#recommendation__not_found');
                var branch = $('#recommendation__branch_id').parent();
                notFound.prop('checked', false);
                branch.addClass('d-none');
                $('#recommendation_submit').prop('disabled', false);
            } else {
                //disable button
                $('#recommendation_submit').prop('disabled', true);
            }
        });
        $('#recommendation_form').on('submit', function(e) {
            if (
                ($('#recommendation__member_id').val() > 0 ||
                    $('#recommendation__not_found').prop('checked')
                ) &&
                $('#recommendation__award_id').val() > 0
            ) {
                $('#recommendation__not_found').prop('disabled', false);
            }
        });

        $('#recommendation__domain_id').change(function() {
            var domainId = $('#recommendation__domain_id').val();
            var awardSelect = $('#recommendation__award_id');
            var awardDescription = $('#award_descriptions');
            if (domainId > 0) {
                var awardUrl =
                    '<?= $this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => "Awards"]) ?>/' +
                    domainId;
                $.get(awardUrl, function(data) {
                    awardSelect.empty();
                    awardSelect.append('<option value=""></option>');
                    awardDescription.empty();
                    var tabButtons = $('<ul class="nav nav-tabs" role="tablist"></div>');
                    var tabContentArea = $(
                        '<div class="tab-content border border-top-0 border-light-subtle p-2"></div>'
                    );
                    var active = "active";
                    var show = "show";
                    data.forEach(award => {
                        var selected = "";
                        if (award.id == $("#recommendation__current_award_id").val()) {
                            selected = "selected";
                        };
                        awardSelect.append('<option value="' + award.id + '" ' + selected +
                            ' >' + award
                            .name + " - " + award.level.name +
                            '</option>');
                        var tabButton = $(
                            '<li class="nav-item" role="presentation"><button class="nav-link ' +
                            active +
                            '" id="award_' + award.id +
                            '_btn" data-bs-toggle="tab" data-bs-target="#award_' + award
                            .id + '"' +
                            ' type="button" role="tab" aria-controls="haward_' + award
                            .id + '" aria-selected="true">' + award.name +
                            '</button></li>');
                        var tabContent = $('<div class="tab-pane fade ' + active + ' ' +
                            show + '" id="award_' + award.id +
                            '" role="tabpanel" aria-labelledby="award_' + award.id +
                            '_btn">' + award.name + ": " + award.description + '</div>');
                        active = "";
                        show = "";
                        tabButtons.append(tabButton);
                        tabContentArea.append(tabContent);
                    });
                    awardDescription.append(tabButtons);
                    awardDescription.append(tabContentArea);
                    awardSelect.prop('disabled', false);
                });
            } else {
                awardSelect.prop('disabled', true);
                awardSelect.append('<option value="">Please select the type of award first.</option>');
            }
        });
        $('#recommendation__award_id').on("change", function() {
            var awardId = $('#recommendation__award_id').val();
            if (awardId > 0) {
                var tabid = "award_" + awardId + "_btn";
                $("#" + tabid).click();
            }
        });
        $('#recommendation__status').on('change', function() {
            var status = $('#recommendation__status').val();
            switch (status) {
                case "given":
                    $('#recommendation__given').parent().removeClass('d-none');
                    $('#recommendation__event_id').parent().removeClass('d-none');
                    $('#recommendation__given').attr('required', true);
                    $('#recommendation__event_id').attr('required', true);
                    break;
                case "scheduled":
                    $('#recommendation__given').parent().addClass('d-none');
                    $('#recommendation__event_id').parent().removeClass('d-none');
                    $('#recommendation__given').removeAttr('required');
                    $('#recommendation__event_id').attr('required', true);
                    break;
                case "scheduling":
                    $('#recommendation__given').parent().addClass('d-none');
                    $('#recommendation__event_id').parent().removeClass('d-none');
                    $('#recommendation__given').removeAttr('required');
                    $('#recommendation__event_id').removeAttr('required');
                    break;
                default:
                    $('#recommendation__given').parent().addClass('d-none');
                    $('#recommendation__event_id').parent().addClass('d-none');
                    $('#recommendation__given').removeAttr('required');
                    $('#recommendation__event_id').removeAttr('required');
                    break;
            }
        });
        $('#recommendation__domain_id').trigger('change');
        $('#recommendation__status').trigger('change');
    }
}
</script>
<?php echo $this->KMP->endBlock(); ?>