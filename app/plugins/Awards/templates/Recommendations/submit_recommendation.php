<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 * @var \App\Model\Entity\MemberActivity[]|\Cake\Collection\CollectionInterface $MemberActivities
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/register");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Recommend an Award';
$this->KMP->endBlock(); ?>
<div class="container-fluid">
    <?= $this->Form->create($recommendation, ['id' => 'recommendation_form']) ?>
    <div class="card mb-3">
        <div class="card-body">

            <fieldset>
                <div class="text-center mt-3"><?= $this->Html->image($headerImage, [
                                                    "alt" => "site logo",
                                                    'width' => '250',
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
                    echo $this->Form->control('contact_number', ['id' => 'recommendation__contact_number']);
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
                    ]); ?>
                    <div class="row mb-2" id="member_links"></div>
                    <?php
                    echo $this->Form->control('branch_id', ['options' => $branches, 'empty' => true, "label" => "Member Of", "id" => "recommendation__branch_id"]);
                    echo $this->Form->control('domain_id', ['options' => $awardsDomains, 'empty' => true, "label" => "Award Type", "id" => "recommendation__domain_id"]); ?>
                    <div class="role p-3" id="award_descriptions">

                    </div>
                    <?php
                    echo $this->Form->control('award_id', ['options' => ["Please select the type of award first."], "disabled" => true, "id" => "recommendation__award_id"]);
                    echo $this->Form->control('reason', ['id' => 'recommendation_reason']);
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
    </div>
</div>

<?= $this->element('recommendationScript', ['user' => $user]); ?>