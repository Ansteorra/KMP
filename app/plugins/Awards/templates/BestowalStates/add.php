<?php

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\BestowalState $state
 * @var array $statuses
 * @var array<int, string> $recommendationStateOptions
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Bestowal State';
$this->KMP->endBlock(); ?>

<div class="bestowalStates form content">
    <?= $this->Form->create($state) ?>
    <fieldset>
        <legend><?= $this->element('backButton') ?> <?= __("Add Bestowal State") ?></legend>
        <?php
        echo $this->Form->control("name");
        echo $this->Form->control("status_id", ['options' => $statuses, 'empty' => '-- Select Status --']);
        echo $this->Form->control("sort_order", ['type' => 'number', 'default' => 0]);
        echo $this->Form->control("supports_gathering", ['type' => 'checkbox', 'switch' => true, 'label' => __('Supports Gathering Assignment')]);
        echo $this->Form->control("locks_recommendations", ['type' => 'checkbox', 'switch' => true, 'label' => __('Locks Linked Recommendations')]);
        echo $this->Form->control("is_hidden", ['type' => 'checkbox', 'switch' => true, 'label' => __('Hidden (requires permission to view)')]);
        echo $this->Form->control('sync_recommendation_state_id', [
            'label' => __('Sync Recommendation State'),
            'options' => $recommendationStateOptions,
            'empty' => '-- None --',
        ]);
        echo $this->Form->control('unwind_recommendation_state_id', [
            'label' => __('Unwind Recommendation State'),
            'options' => $recommendationStateOptions,
            'empty' => '-- None --',
        ]);
        ?>
    </fieldset>
    <div class='text-end'><?= $this->Form->button(__("Submit"), [
                                "class" => "btn-primary",
                            ]) ?></div>
    <?= $this->Form->end() ?>
</div>
