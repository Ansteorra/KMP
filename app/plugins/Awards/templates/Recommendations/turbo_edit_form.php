<turbo-frame id="editRecommendation">
    <script type="application/json" data-awards-rec-edit-target="stateRulesBlock" class="d-none">
        <?= json_encode($rules) ?>
    </script>
    <fieldset>
        <?php
        echo $this->Form->hidden('id', ['value' => $recommendation->id, 'data-awards-rec-edit-target' => 'recId']);
        $url = $this->Url->build([
            'controller' => 'Members',
            'action' => 'AutoComplete',
            'plugin' => null
        ]);
        echo $this->KMP->autoCompleteControl(
            $this->Form,
            'member_sca_name',
            'member_public_id',
            $url,
            "Recommendation For",
            true,
            true,
            3,
            [
                'data-awards-rec-edit-target' => 'scaMember',
                'data-action' => 'change->awards-rec-edit#loadScaMemberInfo',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->member->public_id ?? null, 'text' => $recommendation->member_sca_name]),
            ]
        );
        echo $this->Form->control('not_found', [
            'type' => 'checkbox',
            'label' => "Name not registered in " . $this->KMP->getAppSetting("KMP.ShortSiteTitle") . " database",
            "id" => "recommendation__not_found",
            "value" => "on",
            "disabled" => true,
            "data-awards-rec-edit-target" => "notFound"
        ]); ?>
        <div class="row mb-2" data-awards-rec-edit-target="externalLinks"></div>
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
                'data-awards-rec-edit-target' => 'branch',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->branch_id, 'text' =>
                $recommendation->branch->name]),
            ]
        );
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'domain_name',
            'domain_id',
            $awardsDomains->toArray(),
            "Award Type",
            true,
            false,
            [
                'data-action' => 'change->awards-rec-edit#populateAwardDescriptions',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->award->domain_id, 'text' =>
                $recommendation->award->domain->name]),
                'data-awards-rec-edit-target' => 'domain',
            ]
        );
        echo $this->Form->control('current_award_id', [
            'type' => 'hidden',
            'value' => $recommendation->award_id,
            'data-awards-rec-edit-target' => 'currentAwardId',
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
                'data-awards-rec-edit-target' => 'award',
                'data-action' => 'change->awards-rec-edit#populateSpecialties',
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
                'data-awards-rec-edit-target' => 'specialty',
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
        $selectedGatherings = [];
        if (!empty($recommendation->gatherings)) {
            foreach ($recommendation->gatherings as $gathering) {
                $selectedGatherings[] = $gathering->id;
            }
        }
        $cancelledGatheringIds = $cancelledGatheringIds ?? [];
        echo $this->Form->control('gatherings._ids', [
            'label' => 'Gatherings/Events They May Attend:',
            "type" => "select",
            "multiple" => "checkbox",
            'options' => $gatheringList,
            'value' => $selectedGatherings,
            'id' => 'recommendation__gathering_ids',
            'disabled' => $cancelledGatheringIds,
        ]);
        echo $this->Form->control('contact_number', [
            'type' => 'tel',
            'label' => 'Contact Number',
            'value' => $recommendation->contact_number,
            'disabled' => 'disabled',
        ]);
        echo $this->Form->control('contact_email', [
            'type' => 'email',
            'label' => 'Contact Email',
            'value' => $recommendation->contact_email,
            'disabled' => 'disabled',
        ]);
        echo $this->Form->control(
            'state',
            [
                'options' => $statusList,
                'value' => $recommendation->state,
                'data-awards-rec-edit-target' => 'state',
                'data-action' => 'change->awards-rec-edit#setFieldRules',
            ]
        );
        echo $this->Form->control(
            'close_reason',
            [
                'label' => 'Reason for No Action',
                'value' => $recommendation->close_reason,
                'data-awards-rec-edit-target' => 'closeReason',
                'container' => ['data-awards-rec-edit-target' => 'closeReasonBlock'],
            ]
        );
        
        // Show warning if assigned gathering is cancelled
        $assignedGatheringCancelled = $assignedGatheringCancelled ?? false;
        if ($assignedGatheringCancelled): ?>
        <div class="alert alert-danger mb-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong><?= __('Warning:') ?></strong> <?= __('This recommendation is scheduled for a cancelled gathering. Please reschedule to a different gathering.') ?>
        </div>
        <?php endif;
        
        echo $this->Form->control('gathering_id', [
            'label' => 'Plan to Give At',
            "type" => "select",
            'options' => $gatheringList,
            'empty' => true,
            'value' => $recommendation->gathering_id,
            'data-awards-rec-edit-target' => 'planToGiveGathering',
            'container' => ['data-awards-rec-edit-target' => 'planToGiveBlock'],
            'disabled' => $cancelledGatheringIds,
        ]);

        // Format given date for HTML5 date input (requires Y-m-d format)
        // Since this is a date-only field, format without timezone conversion
        $givenValue = null;
        if ($recommendation->given) {
            $givenValue = $recommendation->given->format('Y-m-d');
        }

        echo $this->Form->control(
            'given',
            [
                'type' => 'date',
                'label' => 'Given On',
                'value' => $givenValue,
                'data-awards-rec-edit-target' => 'givenDate',
                'container' => ['data-awards-rec-edit-target' => 'givenBlock'],
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