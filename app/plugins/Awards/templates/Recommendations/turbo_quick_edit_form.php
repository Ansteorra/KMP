<turbo-frame id="editRecommendation">
    <script type="application/json" data-awards-rec-quick-edit-target="stateRulesBlock" class="d-none">
        <?= json_encode($rules) ?>
    </script>
    <fieldset>

        <?php
        echo $this->Form->hidden('id', ['value' => $recommendation->id, 'data-awards-rec-quick-edit-target' => 'recId']);
        ?>
        <div style="margin:0 !important;" class="form-group text pb-3"><label class="form-label"
                for="member-sca-name">Recommendation For</label>
            <div class="input-group ps-3"><strong><?= h($recommendation->member_sca_name) ?></strong></div>
        </div>
        <?php
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'domain_name',
            'domain_id',
            $awardsDomains->toArray(),
            "Award Type",
            true,
            false,
            [
                'data-action' => 'change->awards-rec-quick-edit#populateAwardDescriptions',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->award->domain_id, 'text' =>
                $recommendation->award->domain->name]),
                'data-awards-rec-quick-edit-target' => 'domain',
            ]
        );
        echo $this->Form->control('current_award_id', [
            'type' => 'hidden',
            'value' => $recommendation->award_id,
            'data-awards-rec-quick-edit-target' => 'currentAwardId',
        ]);
        $awardsList = [];
        foreach ($awards as $award) {
            $awardsList[$award->id] = ["text" => $award->name, "specialties" => $award->specialties];
        }
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'award_name',
            'award_id',
            $awardsList,
            "Award",
            true,
            false,
            [
                'data-awards-rec-quick-edit-target' => 'award',
                'data-action' => 'change->awards-rec-quick-edit#populateSpecialties',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->award->id, 'text' => $recommendation->award->name]),

            ]
        );
        $specialties = [];
        if (is_array($recommendation->award->specialties)) {

            foreach ($recommendation->award->specialties as $specialty) {
                $specialties[$specialty] = ["value" => $specialty, "text" => $specialty];
            }
        }
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'specialty',
            'specialty_hidden',
            $specialties,
            "Specialty",
            true,
            true,
            [
                'data-awards-rec-quick-edit-target' => 'specialty',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->specialty, 'text' => $recommendation->specialty]),

            ]
        );
        echo $this->Form->control('reason', [
            'type' => 'textarea',
            'label' => 'Reason for Recommendation',
            'id' => 'recommendation__reason',
            'value' => $recommendation->reason,
            'disabled' => 'disabled',
        ]);
        echo '<ul>';
        foreach ($recommendation->events as $event) {
            echo '<li>' . $event->name . '</li>';
        }
        echo '</ul>';
        echo $this->Form->control(
            'state',
            [
                'options' => $statusList,
                'value' => $recommendation->state,
                'data-awards-rec-quick-edit-target' => 'state',
                'data-action' => 'change->awards-rec-quick-edit#setFieldRules',
            ]
        );
        echo $this->Form->control(
            'close_reason',
            [
                'label' => 'Reason for No Action',
                'value' => $recommendation->close_reason,
                'data-awards-rec-quick-edit-target' => 'closeReason',
                'container' => ['data-awards-rec-quick-edit-target' => 'closeReasonBlock'],
            ]
        );
        echo $this->Form->control('event_id', [
            'label' => 'Plan to Give At',
            "type" => "select",
            'options' => $eventList,
            'empty' => true,
            'value' => $recommendation->event_id,
            'data-awards-rec-quick-edit-target' => 'planToGiveEvent',
            'container' => ['data-awards-rec-quick-edit-target' => 'planToGiveBlock'],
        ]);
        echo $this->Form->control(
            'given',
            [
                'type' => 'date',
                'label' => 'Given On',
                'value' => $recommendation->given,
                'data-awards-rec-quick-edit-target' => 'givenDate',
                'container' => ['data-awards-rec-quick-edit-target' => 'givenBlock'],
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