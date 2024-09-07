<?php

/**
 * @var \App\View\AppView $this
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/register");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Recommend an Award';
$this->KMP->endBlock(); ?>
<div class="container-sm">
    <?= $this->Form->create($recommendation, [
        'id' => 'recommendation_form',
        'data-controller' => 'awards-rec-add',
        'data-awards-rec-add-public-profile-url-value' => $this->URL->build([
            'controller' => 'Members',
            'action' => 'PublicProfile',
            'plugin' => null
        ]),
        'data-action' => 'submit->awards-rec-add#submit',
        'data-awards-rec-add-award-list-url-value' => $this->URL->build(['controller' => 'Awards', 'action' => 'awardsByDomain', 'plugin' => "Awards"])
    ]) ?>
    <div class="card mb-3">
        <div class="card-body">

            <fieldset>
                <div class="text-center mt-3"><?= $this->Html->image($headerImage, [
                                                    "alt" => "site logo",
                                                    'class' => "img-fluid w-25"
                                                ]) ?></div>
                <legend class="text-center">
                    <h5 class="card-title"><?= __('Submit Award Recommendation') ?></h5>
                </legend>
                <fieldset>
                    <?php
                    echo $this->Form->control("requester_sca_name", [
                        "type" => "text",
                        "label" => "Your SCA Name",
                        "id" => "recommendation__requester_sca_name",
                    ]);
                    echo $this->Form->control('contact_email', ['type' => 'email', 'help' => 'incase we need to contact you']);
                    echo $this->Form->control('contact_number', ['help' => 'optional way for us to contact you']);
                    $url = $this->Url->build([
                        'controller' => 'Members',
                        'action' => 'AutoComplete',
                        'plugin' => null
                    ]);
                    $this->KMP->autoCompleteControl(
                        $this->Form,
                        'member_sca_name',
                        'member_id',
                        $url,
                        "Recommendation For",
                        true,
                        true,
                        3,
                        [
                            'data-awards-rec-add-target' => 'scaMember',
                            'data-action' => 'change->awards-rec-add#loadScaMemberInfo ready->awards-rec-add#acConnected'
                        ]
                    );
                    echo $this->Form->control('not_found', [
                        'type' => 'checkbox',
                        'label' => "Name not registered in " . $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . " database",
                        "value" => "on",
                        "disabled" => true,
                        "data-awards-rec-add-target" => "notFound"
                    ]); ?>
                    <div class="row mb-2" data-awards-rec-add-target="externalLinks"></div>
                    <?php
                    echo $this->KMP->comboBoxControl(
                        $this->Form,
                        'branch_name',
                        'branch_id',
                        $branches,
                        "Member Of",
                        true,
                        false,
                        [
                            'data-awards-rec-add-target' => 'branch',
                            'data-action' => 'ready->awards-rec-add#acConnected'
                        ]
                    );
                    $selectOptions = [];
                    foreach ($callIntoCourtOptions as $option) {
                        $selectOptions[$option] = $option;
                    }
                    echo $this->KMP->comboBoxControl(
                        $this->Form,
                        'call_into_court_val',
                        'call_into_court',
                        $selectOptions,
                        "Call Into Court",
                        true,
                        false,
                        [
                            'data-awards-rec-add-target' => 'callIntoCourt',
                            'data-action' => 'ready->awards-rec-add#acConnected'
                        ]
                    );
                    $selectOptions = [];
                    foreach ($courtAvailabilityOptions as $option) {
                        $selectOptions[$option] = $option;
                    }
                    echo $this->KMP->comboBoxControl(
                        $this->Form,
                        'court_availability_val',
                        'court_availability',
                        $selectOptions,
                        "Court Availability",
                        true,
                        false,
                        [
                            'data-awards-rec-add-target' => 'courtAvailability',
                            'data-action' => 'ready->awards-rec-add#acConnected'
                        ]
                    );
                    echo $this->KMP->comboBoxControl(
                        $this->Form,
                        'domain_name',
                        'domain_id',
                        $awardsDomains,
                        "Award Type",
                        true,
                        false,
                        ['data-action' => 'change->awards-rec-add#populateAwardDescriptions ready->awards-rec-add#acConnected']
                    ); ?>
                    <div class="role p-3" id="award_descriptions" data-awards-rec-add-target="awardDescriptions">

                    </div>
                    <?php
                    echo $this->KMP->comboBoxControl(
                        $this->Form,
                        'award_name',
                        'award_id',
                        ["Select Award Type First" => "Select Award Type First"],
                        "Award",
                        true,
                        false,
                        [
                            'data-awards-rec-add-target' => 'award',
                            'data-action' => 'ready->awards-rec-add#acConnected change->awards-rec-add#populateSpecialties'
                        ]
                    );
                    echo $this->KMP->comboBoxControl(
                        $this->Form,
                        'specialty',
                        'specialty_hidden',
                        ["Select Award First" => "Select Award First"],
                        "Specialty",
                        true,
                        true,
                        [
                            'data-awards-rec-add-target' => 'specialty',
                            'data-action' => 'ready->awards-rec-add#acConnected'
                        ]
                    );
                    echo $this->Form->control('reason', [
                        'id' => 'recommendation_reason',
                        'required' => true,
                        'label' => 'Reason for Recommendation',
                        'data-awards-rec-add-target' => 'reason'
                    ]);
                    echo $this->Form->control('events._ids', [
                        'label' => 'Events They May Attend:',
                        "type" => "select",
                        "multiple" => "checkbox",
                        'options' => $events,
                        'data-awards-rec-add-target' => 'events'
                    ]);
                    ?>
                </fieldset>
                <?= $this->Form->end() ?>
                <?= $this->Form->button(__('Submit'), ["id" => 'recommendation_submit', 'class' => 'btn-primary']) ?>
                <div class="text-end mt-3">
                    <?= $this->Html->link(__('Return to Login'), ['plugin' => null, 'controller' => 'Members', 'action' => 'login'], ['class' => 'btn btn-secondary']) ?>
                </div>

        </div>
    </div>
</div>