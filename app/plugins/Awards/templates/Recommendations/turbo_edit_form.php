<turbo-frame id="editRecommendation">
    <fieldset>
        <?php
        echo $this->Form->hidden('id', ['value' => $recommendation->id, 'data-awards-rec-edit-target' => 'recId']);
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
                'data-awards-rec-edit-target' => 'scaMember',
                'data-action' => 'change->awards-rec-edit#loadScaMemberInfo',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->member_id, 'text' => $recommendation->member_sca_name]),
            ]
        );
        echo $this->Form->control('not_found', [
            'type' => 'checkbox',
            'label' => "Name not registered in " . $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . " database",
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
                'data-awards-rec-edit-target' => 'callIntoCourt',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->call_into_court, 'text' =>
                $recommendation->call_into_court]),
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
                'data-awards-rec-edit-target' => 'courtAvailability',
                'data-ac-init-selection-value' => json_encode(['value' => $recommendation->court_availability, 'text' =>
                $recommendation->court_availability]),
            ]
        );
        echo $this->Form->control('person_to_notify', [
            'label' => 'Person to Notify',
            'value' => $recommendation->person_to_notify,
            'disabled' => 'disabled',
            'data-awards-rec-edit-target' => 'personToNotify',
        ]);
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'domain_name',
            'domain_id',
            $awardsDomains,
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
        $selectedEvents = [];
        foreach ($recommendation->events as $event) {
            $selectedEvents[] = $event->id;
        }
        echo $this->Form->control('events._ids', [
            'label' => 'Events They May Attend:',
            "type" => "select",
            "multiple" => "checkbox",
            'options' => $eventList,
            'value' => $selectedEvents,
            'id' => 'recommendation__event_ids',
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
            'status',
            [
                'options' => $statusList,
                'value' => $recommendation->status,
                'data-awards-rec-edit-target' => 'status',
                'data-action' => 'change->awards-rec-edit#setFieldVisibility',
            ]
        );
        echo $this->Form->control('event_id', [
            'label' => 'Plan to Give At:',
            "type" => "select",
            'options' => $eventList,
            'empty' => true,
            'value' => $recommendation->event_id,
            'data-awards-rec-edit-target' => 'planToGiveEvent',
            'container' => ['data-awards-rec-edit-target' => 'planToGiveBlock'],
        ]);
        echo $this->Form->control(
            'given',
            [
                'type' => 'date',
                'label' => 'Given On',
                'value' => $recommendation->given,
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