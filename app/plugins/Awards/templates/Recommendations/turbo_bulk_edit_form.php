<turbo-frame id="bulkEditRecommendation">
    <script type="application/json" data-awards-rec-bulk-edit-target="stateRulesBlock" class="d-none">
        <?= json_encode($rules) ?>
    </script>
    <fieldset>

        <?php
        echo $this->Form->hidden('ids', ['value' => [], 'data-awards-rec-bulk-edit-target' => 'bulkIds']);
        $cancelledGatheringIds = $cancelledGatheringIds ?? [];
        ?>
        <div style="margin:0 !important;" class="form-group text pb-3"><label class="form-label"
                for="member-sca-name">Bulk Recommendations</label>

        </div>
        <?php

        echo $this->Form->control(
            'newState',
            [
                'options' => $statusList,
                'value' => "",
                'data-awards-rec-bulk-edit-target' => 'state',
                'data-action' => 'change->awards-rec-bulk-edit#setFieldRules',
            ]
        );
        echo $this->Form->control(
            'close_reason',
            [
                'label' => 'Reason for No Action',
                'value' => "",
                'data-awards-rec-bulk-edit-target' => 'closeReason',
                'container' => ['data-awards-rec-bulk-edit-target' => 'closeReasonBlock'],
            ]
        );
        $bulkGatheringLookupUrl = $this->URL->build([
            'plugin' => 'Awards',
            'controller' => 'Recommendations',
            'action' => 'gatheringsForBulkEditAutoComplete',
        ]);
        ?>
        <div data-awards-rec-bulk-edit-target="planToGiveBlock">
            <?= $this->KMP->autoCompleteControl(
                $this->Form,
                'gathering_name',
                'gathering_id',
                $bulkGatheringLookupUrl,
                'Plan to Give At',
                false,
                false,
                2,
                ['data-awards-rec-bulk-edit-target' => 'planToGiveGathering']
            ) ?>
        </div>
        <?php
        echo $this->Form->control(
            'given',
            [
                'type' => 'date',
                'label' => 'Given On',
                'value' => "",
                'data-awards-rec-bulk-edit-target' => 'givenDate',
                'container' => ['data-awards-rec-bulk-edit-target' => 'givenBlock'],
            ]
        );
        echo $this->Form->control('note', [
            'type' => 'textarea',
            'label' => 'Note',
            'id' => 'recommendation__notes',
        ]);
        ?>
    </fieldset>
</turbo-frame>
