<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Submit Award Recoomendation';
$this->KMP->endBlock(); ?>

<div class="recommendations form content">
    <?= $this->Form->create($recommendation, ['id' => 'recommendation_form']) ?>
    <fieldset>
        <legend><?= __('Submit Award Recommendation') ?></legend>
        <?php
        echo $this->Form->control("requester_id", [
            "type" => "hidden",
            "value" => $this->Identity->get("id"),
        ]);
        echo $this->Form->control("member_id", [
            "type" => "hidden",
            "id" => "recommendation__member_id",
        ]);
        echo $this->Form->control("member_sca_name", [
            "type" => "text",
            "label" => "Recommendation For",
            "id" => "recommendation__sca_name",
        ]);
        echo $this->Form->control('not_found', [
            'type' => 'checkbox',
            'label' => "Name not registered in " . $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . " database",
            "id" => "recommendation__not_found",
            "value" => "on",
            "disabled" => true
        ]);
        echo $this->Form->control('branch_id', ['options' => $branches, 'empty' => true, "label" => "Member Of", "id" => "recommendation__branch_id"]);
        echo $this->Form->control('domain_id', ['options' => $awardsDomains, 'empty' => true, "label" => "Award Type", "id" => "recommendation__domain_id"]); ?>
        <div class="role p-3" id="award_descriptions">

        </div>
        <?php
        echo $this->Form->control('award_id', ['options' => ["Please select the type of award first."], "disabled" => true, "id" => "recommendation__award_id"]);
        echo $this->Form->control('contact_number', ['value' => $user->phone_number]);
        echo $this->Form->control('reason');
        echo $this->Form->control('events._ids', [
            'label' => 'Events They may Attend:',
            "type" => "select",
            "multiple" => "checkbox",
            'options' => $events
        ]);
        ?>
    </fieldset>
    <?= $this->Form->end() ?>
    <?= $this->Form->button(__('Submit'), ["disabled" => true, "id" => 'recommendation_submit']) ?>

</div>

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

    run() {
        $('#recommendation__branch_id').parent().addClass('d-none');
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
        $('#recommendation_submit').on('click', function() {
            if (
                ($('#recommendation__member_id').val() > 0 ||
                    $('#recommendation__not_found').prop('checked')
                ) &&
                $('#recommendation__award_id').val() > 0
            ) {
                $('#recommendation__not_found').prop('disabled', false);
                $('#recommendation_form').submit();
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
                        awardSelect.append('<option value="' + award.id + '">' + award
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
        $('#recommendation__domain_id').trigger('change');
    }
};
window.addEventListener('DOMContentLoaded', function() {
    var view = new recommendationsAdd();
    view.run();
});
</script>
<?php echo $this->KMP->endBlock(); ?>