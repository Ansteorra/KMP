<turbo-frame id="bulkEditBestowal">
    <script type="application/json" data-awards-bestowal-bulk-edit-target="stateRulesBlock" class="d-none">
        <?= json_encode($rules) ?>
    </script>
    <fieldset>
        <?php
        echo $this->Form->hidden('ids', [
            'value' => [],
            'data-awards-bestowal-bulk-edit-target' => 'bulkIds',
        ]);
        echo $this->Form->control('newState', [
            'label' => __('State'),
            'options' => $statusList,
            'empty' => __('Select a state'),
            'data-awards-bestowal-bulk-edit-target' => 'state',
            'data-action' => 'change->awards-bestowal-bulk-edit#setFieldRules',
        ]);
        echo $this->Form->control('close_reason', [
            'label' => __('Reason for Cancellation'),
            'data-awards-bestowal-bulk-edit-target' => 'closeReason',
            'container' => ['data-awards-bestowal-bulk-edit-target' => 'closeReasonBlock'],
        ]);
        $bulkGatheringLookupUrl = $this->URL->build([
            'plugin' => 'Awards',
            'controller' => 'Bestowals',
            'action' => 'gatheringsForBestowalBulkAutoComplete',
        ]);
        ?>
        <div data-awards-bestowal-bulk-edit-target="planToGiveBlock">
            <?= $this->KMP->autoCompleteControl(
                $this->Form,
                'gathering_name',
                'gathering_id',
                $bulkGatheringLookupUrl,
                __('Gathering'),
                false,
                false,
                2,
                ['data-awards-bestowal-bulk-edit-target' => 'planToGiveGathering']
            ) ?>
        </div>
        <?php
        echo $this->Form->control('bestowed_at', [
            'type' => 'date',
            'label' => __('Bestowed On'),
            'data-awards-bestowal-bulk-edit-target' => 'givenDate',
            'container' => ['data-awards-bestowal-bulk-edit-target' => 'givenBlock'],
        ]);
        echo $this->Form->control('note', [
            'type' => 'textarea',
            'label' => __('Note'),
        ]);
        ?>
    </fieldset>
</turbo-frame>
