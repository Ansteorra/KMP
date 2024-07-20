<turbo-frame id="editRecommendation">
    <fieldset>
        <?php
        echo $this->Form->control("member_id", [
            "type" => "hidden",
            "id" => "recommendation__member_id",
            "value" => $recommendation->member_id
        ]);
        echo $this->Form->control("member_sca_name", [
            "type" => "text",
            "label" => "Recommendation For",
            "id" => "recommendation__sca_name",
            "value" => $recommendation->member_sca_name,
        ]);
        echo $this->Form->control('not_found', [
            'type' => 'checkbox',
            'label' => "Name not registered in " . $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . " database",
            "id" => "recommendation__not_found",
            "value" => "on",
            "disabled" => true,
            "checked" => ($recommendation->member_id == null)
        ]);
        echo $this->Form->control(
            'branch_id',
            [
                'options' => $branches,
                'empty' => true,
                'label' => 'Member Of',
                'id' => 'recommendation__branch_id',
                'value' => $recommendation->branch_id
            ]
        );
        $selectOptions = [];
        foreach ($callIntoCourtOptions as $option) {
            $selectOptions[$option] = $option;
        }
        echo $this->Form->control(
            'call_into_court',
            [
                'options' => $selectOptions,
                'empty' => true,
                'id' => 'recommendation__call_into_court',
                'required' => true,
                'value' => $recommendation->call_into_court
            ]
        );
        $selectOptions = [];
        foreach ($courtAvailabilityOptions as $option) {
            $selectOptions[$option] = $option;
        }
        echo $this->Form->control(
            'court_availability',
            [
                'options' => $selectOptions,
                'empty' => true,
                'id' => 'recommendation__court_availability',
                "required" => true,
                'value' => $recommendation->court_availability,
            ]
        );
        echo $this->Form->control('domain_id', [
            'options' => $awardsDomains,
            'empty' => true,
            'label' => 'Award Type',
            'id' => 'recommendation__domain_id',
            'value' => $recommendation->domain_id
        ]); ?>
        <div class="role p-3" id="award_descriptions">

        </div>
        <?php
        echo $this->Form->control('current_award_id', [
            'type' => 'hidden',
            'id' => 'recommendation__current_award_id',
            'value' => $recommendation->award_id
        ]);
        echo $this->Form->control('award_id', ['options' => ["Please select the type of award first."], "disabled" => true, "id" => "recommendation__award_id"]);
        echo $this->Form->control('contact_number', [
            'type' => 'tel',
            'label' => 'Contact Number',
            'id' => 'recommendation__contact_number',
            'value' => $recommendation->contact_number
        ]);
        echo $this->Form->control('contact_email', [
            'type' => 'email',
            'label' => 'Contact Email',
            'id' => 'recommendation__contact_email',
            'value' => $recommendation->contact_email
        ]);
        echo $this->Form->control('reason', [
            'type' => 'textarea',
            'label' => 'Reason for Recommendation',
            'id' => 'recommendation__reason',
            'value' => $recommendation->reason
        ]);
        $selectedEvents = [];
        foreach ($recommendation->events as $event) {
            $selectedEvents[] = $event->id;
        }
        echo $this->Form->control('events._ids', [
            'label' => 'Events They may Attend:',
            "type" => "select",
            "multiple" => "checkbox",
            'options' => $eventList,
            'value' => $selectedEvents,
            'id' => 'recommendation__event_ids',
        ]);
        echo $this->Form->control(
            'status',
            [
                'options' => $statusList,
                'id' => 'recommendation__status',
                'value' => $recommendation->status
            ]
        );
        echo $this->Form->control(
            'given',
            [
                'type' => 'date',
                'label' => 'Given On',
                'id' => 'recommendation__given',
                'value' => $recommendation->given
            ]
        );
        echo $this->Form->control('event_id', [
            'label' => 'Plan to Give At:',
            "type" => "select",
            'options' => $eventList,
            'empty' => true,
            'id' => 'recommendation__event_id',
            'value' => $recommendation->event_id
        ]);
        echo $this->Form->control('note', [
            'type' => 'textarea',
            'label' => 'Note',
            'id' => 'recommendation__notes',
        ]);
        ?>
    </fieldset>
</turbo-frame>