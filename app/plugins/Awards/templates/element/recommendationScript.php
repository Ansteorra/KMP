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
        $('#recommendation__branch_id').prop('disabled', false);
        branch.removeClass('d-none');
        $('#recommendation_submit').prop('disabled', false);
        var memberLinks = $('#member_links');
        memberLinks.empty();
    }
    searchHadResults() {
        var notFound = $('#recommendation__not_found');
        var branch = $('#recommendation__branch_id').parent();
        $('#recommendation__branch_id').prop('disabled', true);
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
                if (data["external_links"]) {
                    var extLinks = data["external_links"];
                    if (extLinks.length != 0) {
                        var links = $('<div class="col-12"><h5>Links of Interest</h5></div>');
                        //data is an an array where the key is the name and the value is the url
                        for (var key in extLinks) {
                            var link = $('<a href="' + data[key] + '" target="_blank">' + key + '</a>');
                            var linkDiv = $('<div class="col-12"></div>');
                            linkDiv.append(link);
                            links.append(linkDiv);
                        }
                        memberLinks.append(links);
                    }
                }
                if (data["additional_info"]) {
                    var callIntoCourt = data["additional_info"]["CallIntoCourt"];
                    var courtAvailability = data["additional_info"]["CourtAvailability"];
                    if (callIntoCourt && callIntoCourt != "") {
                        $("#recommendation__call_into_court").val(callIntoCourt);
                        $("#recommendation__call_into_court").prop('disabled', true);
                    }
                    if (courtAvailability && courtAvailability != "") {
                        $("#recommendation__court_availability").val(courtAvailability);
                        $("#recommendation__court_availability").prop('disabled', true);
                    }
                }
            }
        });
    }

    run() {
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
                me.getPublicProfile($('#recommendation__member_id').val());
            }
        });
        $('#recommendation_form').on('submit', function(e) {
            $('#recommendation__not_found').prop('disabled', false);
            $("#recommendation__call_into_court").prop('disabled', false);
            $("#recommendation__court_availability").prop('disabled', false);
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
                    var tabButtons = $('<ul class="nav nav-pills" role="tablist"></div>');
                    var tabContentArea = $(
                        '<div class="tab-content border border-light-subtle p-2"></div>'
                    );
                    var active = "active";
                    var show = "show";
                    var wasSelected = '<?= $recommendation->award_id ?>';
                    var selected = "";
                    data.forEach(award => {
                        <?php if ($recommendation->award_id > 0) : ?>
                        if (award.id == wasSelected) {
                            selected = "selected='selected'";
                            show = "show";
                            active = "active";
                        } else {
                            active = "";
                            show = "";
                            selected = "";
                        }
                        <?php endif; ?>
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

        <?php if (!$recommendation->isNew()) : ?>
        $('#recommendation__domain_id').val('');
        $('#recommendation__sca_name').val('');
        $('#recommendation__member_id').val('');
        $('#member_sca_name').val('');
        $('input[type="checkbox"]').prop('checked', false);
        $('#member_links').empty();
        $('#recommendation__branch_id').parent().addClass('d-none');
        $('#recommendation__award_id').val('');
        $('#recommendation_reason').val('');
        $('#recommendation__branch_id').val('');
        $('#recommendation__branch_id').val('');
        <?php else : ?>
        <?php if ($recommendation->branch_id && $recommendation->branch_id > 0) : ?>
        $('#recommendation__branch_id').parent().removeClass('d-none');
        $('#recommendation__not_found').prop('checked', true);
        <?php else : ?>
        $('#recommendation__branch_id').parent().addClass('d-none');
        $('#recommendation__not_found').prop('checked', false);
        <?php endif; ?>
        <?php endif; ?>
        $('#recommendation__domain_id').trigger('change')
    }
};
window.addEventListener('DOMContentLoaded', function() {
    var view = new recommendationsAdd();
    view.run();
});
</script>
<?php echo $this->KMP->endBlock(); ?>