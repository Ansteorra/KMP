<?php
$formUrl = $this->Url->build(['plugin' => 'Awards', 'controller' => 'Bestowals', 'action' => 'edit', $bestowal->id]);
$submitAction = implode(' ', [
    'submit->turbo-modal#submitAsTurboStream',
    'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
    'input->awards-bestowal-edit#updateSubmitState',
    'change->awards-bestowal-edit#updateSubmitState',
    'autocomplete.change->awards-bestowal-edit#updateSubmitState',
]);
?>
<turbo-frame id="editBestowalQuick"
    data-awards-bestowal-edit-target="turboFrame"
    data-action="turbo:frame-load->awards-bestowal-edit#onTurboFrameLoad"
    data-awards-bestowal-edit-linked-recommendation-count-value="<?= count($bestowal->recommendations ?? []) ?>">
    <?= $this->Form->create(null, [
        'url' => $formUrl,
        'id' => 'bestowal_form',
        'data-turbo' => 'true',
        'data-controller' => 'turbo-modal',
        'data-action' => $submitAction,
    ]) ?>
    <?= $this->Form->hidden('page_context_url', [
        'value' => $this->request->getRequestTarget(),
    ]) ?>
    <script type="application/json" data-awards-bestowal-edit-target="stateRulesBlock" class="d-none">
        <?= json_encode($rules) ?>
    </script>
    <script type="application/json" data-awards-bestowal-edit-status-map-json class="d-none">
        <?= json_encode($statusMap ?? []) ?>
    </script>
    <script type="application/json" data-awards-bestowal-edit-target="bestowedDateHints" class="d-none">
        <?= json_encode([
            'gatheringStartDate' => $gatheringStartDateYmd ?? null,
            'courtSessionDates' => $courtSessionDates ?? [],
            'suggestedBestowedDate' => $suggestedBestowedDate ?? null,
        ]) ?>
    </script>
    <fieldset>
        <?php
        echo $this->Form->hidden('id', [
            'value' => $bestowal->id,
            'data-awards-bestowal-edit-target' => 'bestowalId',
        ]);
        echo $this->Form->hidden('member_id', [
            'value' => $bestowal->member_id,
            'data-awards-bestowal-edit-target' => 'memberId',
        ]);
        ?>
        <div class="mb-3">
            <label class="form-label"><?= __('Member') ?></label>
            <div class="form-control-plaintext">
                <?= h($bestowal->member->sca_name ?? __('Unknown Member')) ?>
            </div>
        </div>
        <?php
        $selectedAward = $bestowal->award
            ?? ($bestowal->primary_recommendation->award ?? null);
        $domainInitSelection = null;
        if ($selectedAward !== null && $selectedAward->hasValue('domain')) {
            $domainInitSelection = [
                'value' => $selectedAward->domain_id,
                'text' => $selectedAward->domain->name,
            ];
        }
        $awardsList = [];
        foreach ($awards as $award) {
            $awardsList[$award->id] = [
                'text' => $award->name,
                'specialties' => $award->specialties,
            ];
        }
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'domain_name',
            'domain_id',
            $awardsDomains->toArray(),
            __('Award Type'),
            true,
            false,
            [
                'data-action' => 'autocomplete.change->awards-bestowal-edit#onDomainChange change->awards-bestowal-edit#onDomainChange',
                'data-ac-init-selection-value' => $domainInitSelection !== null
                    ? json_encode($domainInitSelection)
                    : null,
                'data-awards-bestowal-edit-target' => 'domain',
            ],
        );
        echo $this->Form->control('current_award_id', [
            'type' => 'hidden',
            'value' => $bestowal->award_id ?? ($selectedAward->id ?? null),
            'data-awards-bestowal-edit-target' => 'currentAwardId',
        ]);
        $awardInitSelection = null;
        if ($selectedAward !== null) {
            $awardInitSelection = [
                'value' => $selectedAward->id,
                'text' => $selectedAward->name,
            ];
        }
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'award_name',
            'award_id',
            $awardsList,
            __('Award to Bestow'),
            true,
            false,
            [
                'data-awards-bestowal-edit-target' => 'award',
                'data-action' => 'autocomplete.change->awards-bestowal-edit#onAwardChange change->awards-bestowal-edit#onAwardChange',
                'data-ac-init-selection-value' => $awardInitSelection !== null
                    ? json_encode($awardInitSelection)
                    : null,
            ],
        );
        ?>
        <div class="mb-3">
            <label class="form-label"><?= __('Status') ?></label>
            <div class="form-control-plaintext" data-awards-bestowal-edit-target="statusDisplay">
                <?= h($bestowal->status) ?>
            </div>
        </div>
        <?php
        echo $this->Form->control('state', [
            'label' => __('State'),
            'options' => $statusList,
            'value' => $bestowal->state,
            'data-awards-bestowal-edit-target' => 'state',
            'data-action' => 'change->awards-bestowal-edit#setFieldRules change->awards-bestowal-edit#updateStatusDisplay',
        ]);
        echo $this->Form->control('close_reason', [
            'label' => __('Reason for Cancellation'),
            'value' => $bestowal->close_reason,
            'data-awards-bestowal-edit-target' => 'closeReason',
            'container' => ['data-awards-bestowal-edit-target' => 'closeReasonBlock'],
        ]);
        ?>

        <?php if (!empty($memberAttendanceGatherings)) : ?>
            <div class="mb-3">
                <label class="form-label"><?= __('Gatherings/Events They May Attend:') ?></label>
                <ul>
                    <?php foreach ($memberAttendanceGatherings as $gathering) :
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
                        $hasAttendanceMarker = isset($gatheringList[$gathering->id])
                            && str_ends_with($gatheringList[$gathering->id], ' *');
                        if ($hasAttendanceMarker) {
                            $displayName .= ' <strong>*</strong>';
                        }
                        ?>
                        <li><?= $displayName ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php
        $assignedGatheringCancelled = $assignedGatheringCancelled ?? false;
        if ($assignedGatheringCancelled) : ?>
            <div class="alert alert-danger mb-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong><?= __('Warning:') ?></strong>
                <?= __('This bestowal is scheduled for a cancelled gathering. Please reschedule to a different gathering.') ?>
            </div>
        <?php endif;

        $selectedGatheringText = '';
        if (!empty($bestowal->gathering_id) && isset($gatheringList[$bestowal->gathering_id])) {
            $selectedGatheringText = $gatheringList[$bestowal->gathering_id];
        }
        $gatheringLookupQuery = array_filter([
            'status' => $bestowal->state,
            'selected_id' => $bestowal->gathering_id,
            'award_id' => $bestowal->award_id ?? ($selectedAward->id ?? null),
        ], fn($value) => $value !== null && $value !== '');
        $gatheringLookupUrl = $this->URL->build([
            'plugin' => 'Awards',
            'controller' => 'Bestowals',
            'action' => 'gatheringsForBestowalAutoComplete',
            $bestowal->id,
            '?' => $gatheringLookupQuery,
        ]);
        ?>
        <div data-awards-bestowal-edit-target="planToGiveBlock">
            <?= $this->KMP->autoCompleteControl(
                $this->Form,
                'gathering_name',
                'gathering_id',
                $gatheringLookupUrl,
                __('Gathering'),
                false,
                false,
                2,
                [
                    'data-awards-bestowal-edit-target' => 'planToGiveGathering',
                    'data-ac-show-on-focus-value' => 'true',
                    'data-action' => 'autocomplete.change->awards-bestowal-edit#updateCourtSlots autocomplete.change->awards-bestowal-edit#onGatheringChange',
                    'data-ac-init-selection-value' => json_encode([
                        'value' => $bestowal->gathering_id,
                        'text' => $selectedGatheringText,
                    ]),
                ]
            ) ?>
        </div>
        <?php
        $courtSlotsAvailable = $courtSlotsAvailable ?? false;
        $courtSlotHasScheduledSessions = $courtSlotHasScheduledSessions ?? false;
        $courtSlotHelpText = $courtSlotHelpText ?? '';
        $courtSlotNoScheduleText = $courtSlotNoScheduleText ?? '';
        $courtSlotValue = $courtSlotValue ?? null;
        $hasGatheringSelected = !empty($bestowal->gathering_id);
        ?>
        <div
            data-awards-bestowal-edit-target="courtSlotBlock"
            data-awards-bestowal-edit-initial-court-slots-available="<?= $courtSlotsAvailable ? 'true' : 'false' ?>">
            <p
                class="form-text text-muted mb-2 <?= $courtSlotsAvailable ? '' : 'd-none' ?>"
                data-awards-bestowal-edit-target="courtSlotHelp">
                <?= h($courtSlotHelpText) ?>
            </p>
            <div
                class="alert alert-info small mb-2 <?= ($courtSlotsAvailable && !$courtSlotHasScheduledSessions && $hasGatheringSelected) ? '' : 'd-none' ?>"
                role="status"
                data-awards-bestowal-edit-target="courtSlotNoSchedule">
                <?= h($courtSlotNoScheduleText) ?>
                <?php if (!empty($gatheringScheduleUrl)) : ?>
                    <?= ' ' . $this->Html->link(
                        __('Open gathering schedule'),
                        $gatheringScheduleUrl,
                        ['target' => '_blank'],
                    ) ?>
                <?php endif; ?>
            </div>
            <div
                data-awards-bestowal-edit-target="courtSlotSelectWrap"
                class="<?= $courtSlotsAvailable ? '' : 'd-none' ?>">
                <?= $this->Form->control('gathering_scheduled_activity_id', [
                    'label' => __('Court session'),
                    'options' => $courtSlotList,
                    'value' => $courtSlotValue,
                    'empty' => __('Select a court session (optional)'),
                    'data-awards-bestowal-edit-target' => 'courtSlot',
                    'data-action' => 'change->awards-bestowal-edit#onCourtSlotChange',
                ]) ?>
            </div>
        </div>
        <?php
        $bestowedValue = null;
        if ($bestowal->bestowed_at !== null) {
            $bestowedValue = $bestowal->bestowed_at->format('Y-m-d');
        } elseif (!empty($suggestedBestowedDate)) {
            $bestowedValue = $suggestedBestowedDate;
        }
        echo $this->Form->control('bestowed_at', [
            'type' => 'date',
            'label' => __('Bestowed On'),
            'value' => $bestowedValue,
            'data-awards-bestowal-edit-target' => 'givenDate',
            'container' => ['data-awards-bestowal-edit-target' => 'givenBlock'],
        ]);
        echo $this->Form->control('noble_notes', [
            'type' => 'textarea',
            'label' => __('Noble Notes'),
            'value' => $bestowal->noble_notes,
            'data-awards-bestowal-edit-target' => 'nobleNotes',
        ]);
        echo $this->Form->control('herald_notes', [
            'type' => 'textarea',
            'label' => __('Herald Notes'),
            'value' => $bestowal->herald_notes,
            'data-awards-bestowal-edit-target' => 'heraldNotes',
        ]);
        ?>

        <?php
        $linkedRecommendations = $bestowal->recommendations ?? [];
        $linkedRecommendationCount = count($linkedRecommendations);
        if ($linkedRecommendationCount > 1) : ?>
            <div class="mb-3" data-awards-bestowal-edit-target="unlinkRecommendationsBlock">
                <label class="form-label"><?= __('Unlink Recommendations') ?></label>
                <p class="text-muted small">
                    <?= __('Unlinked recommendations return to their pre-link state (typically King Approved). At least one recommendation must remain linked.') ?>
                </p>
                <?php foreach ($linkedRecommendations as $recommendation) :
                    $awardLabel = $recommendation->award->abbreviation
                        ?? $recommendation->award->name
                        ?? ('Rec #' . $recommendation->id);
                    ?>
                    <div class="form-check">
                        <?= $this->Form->checkbox('unlink_recommendation_ids[]', [
                            'value' => $recommendation->id,
                            'hiddenField' => false,
                            'id' => 'unlink-rec-' . $recommendation->id,
                            'class' => 'form-check-input',
                            'data-awards-bestowal-edit-target' => 'unlinkRecommendation',
                            'data-action' => 'change->awards-bestowal-edit#updateUnlinkAvailability',
                        ]) ?>
                        <label class="form-check-label" for="unlink-rec-<?= $recommendation->id ?>">
                            <?= h($awardLabel) ?> — <?= h($recommendation->state) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($linkedRecommendationCount === 1) :
            $onlyRecommendation = $linkedRecommendations[0];
            $awardLabel = $onlyRecommendation->award->abbreviation
                ?? $onlyRecommendation->award->name
                ?? ('Rec #' . $onlyRecommendation->id);
            ?>
            <div class="mb-3">
                <label class="form-label"><?= __('Linked Recommendation') ?></label>
                <p class="form-control-plaintext mb-1">
                    <?= h($awardLabel) ?> — <?= h($onlyRecommendation->state) ?>
                </p>
                <p class="text-muted small mb-0">
                    <?= __('A bestowal must keep at least one linked recommendation. Link another recommendation before unlinking this one.') ?>
                </p>
            </div>
            <div class="mb-3 d-none" data-awards-bestowal-edit-target="unlinkRecommendationsBlock">
                <label class="form-label"><?= __('Unlink Recommendation') ?></label>
                <div class="form-check">
                    <?= $this->Form->checkbox('unlink_recommendation_ids[]', [
                        'value' => $onlyRecommendation->id,
                        'hiddenField' => false,
                        'id' => 'unlink-rec-' . $onlyRecommendation->id,
                        'class' => 'form-check-input',
                        'disabled' => true,
                        'data-awards-bestowal-edit-target' => 'unlinkRecommendation',
                        'data-action' => 'change->awards-bestowal-edit#updateUnlinkAvailability',
                    ]) ?>
                    <label class="form-check-label" for="unlink-rec-<?= $onlyRecommendation->id ?>">
                        <?= h($awardLabel) ?> — <?= h($onlyRecommendation->state) ?>
                    </label>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($linkableRecommendations)): ?>
            <div class="mb-3" data-awards-bestowal-edit-target="linkRecommendationsBlock">
                <label class="form-label"><?= __('Link Recommendations') ?></label>
                <?php
                $linkedIds = array_map(
                    fn($rec) => (int)$rec->id,
                    $linkedRecommendations,
                );
                foreach ($linkableRecommendations as $recId => $label):
                    if (in_array((int)$recId, $linkedIds, true)) {
                        continue;
                    }
                    ?>
                    <div class="form-check">
                        <?= $this->Form->checkbox('link_recommendation_ids[]', [
                            'value' => $recId,
                            'hiddenField' => false,
                            'id' => 'link-rec-' . $recId,
                            'class' => 'form-check-input',
                            'data-awards-bestowal-edit-target' => 'linkRecommendation',
                            'data-action' => 'change->awards-bestowal-edit#updateUnlinkAvailability',
                        ]) ?>
                        <label class="form-check-label" for="link-rec-<?= $recId ?>">
                            <?= h($label) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $this->Form->control('note', [
            'type' => 'textarea',
            'label' => __('Note'),
        ]) ?>
    </fieldset>
    <?= $this->Form->end() ?>
</turbo-frame>
