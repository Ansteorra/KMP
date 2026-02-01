<turbo-frame id="editRecommendation">
    <script type="application/json" data-awards-rec-quick-edit-target="stateRulesBlock" class="d-none">
        <?= json_encode($rules) ?>
    </script>
    <fieldset>

        <?php
        echo $this->Form->hidden('id', ['value' => $recommendation->id, 'data-awards-rec-quick-edit-target' => 'recId']);
        echo $this->Form->hidden('member_id', ['value' => $recommendation->member_id, 'data-awards-rec-quick-edit-target' => 'memberId']);
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

        // Display gatherings with attendance indicators
        if (!empty($recommendation->gatherings)) {
            echo '<div class="mb-3">';
            echo '<label class="form-label">Gatherings/Events They May Attend:</label>';
            echo '<ul>';
            foreach ($recommendation->gatherings as $gathering) {
                // Build display name with branch and dates
                $displayName = h($gathering->name);
                if (isset($gathering->branch)) {
                    $displayName .= ' in ' . h($gathering->branch->name);
                }
                if (isset($gathering->start_date)) {
                    $displayName .= ' on ' . $this->Timezone->format($gathering->start_date, $gathering, 'Y-m-d');
                    if (isset($gathering->end_date)) {
                        $displayName .= ' - ' . $this->Timezone->format($gathering->end_date, $gathering, 'Y-m-d');
                    }
                }

                // Check if this gathering has attendance indicator from the member
                // Look for matching gathering in the gatheringList which has attendance info
                $hasAttendanceMarker = false;
                if (isset($gatheringList[$gathering->id])) {
                    // If the gathering in the list ends with ' *', member is attending with share_with_crown
                    $hasAttendanceMarker = str_ends_with($gatheringList[$gathering->id], ' *');
                }

                // Add attendance indicator
                if ($hasAttendanceMarker) {
                    $displayName .= ' <strong>*</strong>';
                }

                echo '<li>' . $displayName . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

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
        
        // Show warning if assigned gathering is cancelled
        $assignedGatheringCancelled = $assignedGatheringCancelled ?? false;
        $cancelledGatheringIds = $cancelledGatheringIds ?? [];
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
            'data-awards-rec-quick-edit-target' => 'planToGiveGathering',
            'container' => ['data-awards-rec-quick-edit-target' => 'planToGiveBlock'],
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