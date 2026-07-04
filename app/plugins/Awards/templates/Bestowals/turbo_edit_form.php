<?php
use Awards\Model\Entity\Bestowal;

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
    <script type="application/json" data-awards-bestowal-edit-target="bestowedDateHints" class="d-none">
        <?= json_encode([
            'gatheringStartDate' => $gatheringStartDateYmd ?? null,
            'courtSessionDates' => $courtSessionDates ?? [],
            'suggestedBestowedDate' => $suggestedBestowedDate ?? null,
        ]) ?>
    </script>
    <fieldset>
        <div class="row g-3">
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
        <div class="col-12 col-xl-5">
            <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                    <i class="bi bi-award text-primary me-1" aria-hidden="true"></i>
                    <?= __('Recipient & Award') ?>
                </legend>
        <div class="mb-3">
            <p class="form-label mb-1"><?= __('Member') ?></p>
            <div class="form-control-plaintext">
                <?= h($bestowal->member->sca_name ?? $bestowal->member_sca_name ?? __('Unknown Member')) ?>
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
        $domainAttrs = [
            'data-action' => implode(' ', [
                'autocomplete.change->awards-bestowal-edit#onDomainChange',
                'change->awards-bestowal-edit#onDomainChange',
            ]),
            'data-awards-bestowal-edit-target' => 'domain',
        ];
        if ($domainInitSelection !== null) {
            $domainAttrs['data-ac-init-selection-value'] = json_encode($domainInitSelection);
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
            $domainAttrs,
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
        $awardAttrs = [
            'data-awards-bestowal-edit-target' => 'award',
            'data-action' => implode(' ', [
                'autocomplete.change->awards-bestowal-edit#onAwardChange',
                'change->awards-bestowal-edit#onAwardChange',
            ]),
        ];
        if ($awardInitSelection !== null) {
            $awardAttrs['data-ac-init-selection-value'] = json_encode($awardInitSelection);
        }
        $specialtyOptions = [];
        $configuredSpecialties = $selectedAward->specialties ?? [];
        if (is_string($configuredSpecialties)) {
            $decodedSpecialties = json_decode($configuredSpecialties, true);
            $configuredSpecialties = is_array($decodedSpecialties)
                ? $decodedSpecialties
                : [$configuredSpecialties];
        }
        if (is_array($configuredSpecialties)) {
            foreach ($configuredSpecialties as $specialtyOption) {
                $specialtyOption = trim((string)$specialtyOption);
                if ($specialtyOption !== '') {
                    $specialtyOptions[$specialtyOption] = $specialtyOption;
                }
            }
        }
        $specialtyInitSelection = null;
        if (!empty($bestowal->specialty)) {
            $specialtyInitSelection = [
                'value' => $bestowal->specialty,
                'text' => $bestowal->specialty,
            ];
            $specialtyOptions[(string)$bestowal->specialty] = (string)$bestowal->specialty;
        }
        $specialtyAttrs = [
            'data-awards-bestowal-edit-target' => 'specialty',
            'data-action' => 'change->awards-bestowal-edit#updateSubmitState',
        ];
        if ($specialtyInitSelection !== null) {
            $specialtyAttrs['data-ac-init-selection-value'] = json_encode($specialtyInitSelection);
        }
        echo $this->KMP->comboBoxControl(
            $this->Form,
            'award_name',
            'award_id',
            $awardsList,
            __('Award to Bestow'),
            true,
            false,
            $awardAttrs,
        );
        ?>
        <div class="d-none" data-awards-bestowal-edit-target="specialtyBlock">
            <?= $this->KMP->comboBoxControl(
                $this->Form,
                'specialty',
                'specialty_hidden',
                $specialtyOptions,
                __('Specialty'),
                false,
                true,
                $specialtyAttrs,
            ) ?>
            <div class="form-text">
                <?= __('Select a configured specialty or type the specialty to record.') ?>
            </div>
        </div>
        <div class="mb-3">
            <p class="form-label mb-1"><?= __('Lifecycle Status') ?></p>
            <div class="form-control-plaintext">
                <?= h(ucfirst((string)($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN))) ?>
            </div>
        </div>
        <?php
        echo $this->Form->control('close_reason', [
            'label' => __('Reason for Cancellation'),
            'value' => $bestowal->close_reason,
            'data-awards-bestowal-edit-target' => 'closeReason',
            'container' => ['data-awards-bestowal-edit-target' => 'closeReasonBlock'],
        ]);
        ?>
            </fieldset>
        </div>
        <div class="col-12 col-xl-7">
            <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                    <i class="bi bi-calendar-event text-success me-1" aria-hidden="true"></i>
                    <?= __('Court Planning') ?>
                </legend>

        <?php if (!empty($memberAttendanceGatherings)) : ?>
            <div class="mb-3">
                <p class="form-label mb-1"><?= __('Gatherings/Events They May Attend:') ?></p>
                <ul>
                    <?php foreach ($memberAttendanceGatherings as $gathering) :
                        $displayName = h($gathering->name);
                        if (isset($gathering->branch)) {
                            $displayName .= ' in ' . h($gathering->branch->name);
                        }
                        if (isset($gathering->start_date)) {
                            $displayName .= ' on '
                                . $this->Timezone->format($gathering->start_date, $gathering, 'Y-m-d');
                            if (isset($gathering->end_date)) {
                                $displayName .= ' - '
                                    . $this->Timezone->format($gathering->end_date, $gathering, 'Y-m-d');
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
                <?= __(
                    'This bestowal is scheduled for a cancelled gathering. '
                    . 'Please reschedule to a different gathering.',
                ) ?>
            </div>
        <?php endif;

        $selectedGatheringText = '';
        if (!empty($bestowal->gathering_id) && isset($gatheringList[$bestowal->gathering_id])) {
            $selectedGatheringText = $gatheringList[$bestowal->gathering_id];
        }
        $gatheringLookupQuery = array_filter([
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
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="bestowalIncludePastGatherings"
                    name="include_past" value="1"
                    data-awards-bestowal-edit-target="includePastGatherings"
                    data-action="change->awards-bestowal-edit#onIncludePastGatheringsChange">
                <label class="form-check-label" for="bestowalIncludePastGatherings">
                    <?= __('Include past gatherings') ?>
                </label>
            </div>
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
                    'data-action' => implode(' ', [
                        'autocomplete.change->awards-bestowal-edit#updateCourtSlots',
                        'autocomplete.change->awards-bestowal-edit#onGatheringChange',
                    ]),
                    'data-ac-init-selection-value' => json_encode([
                        'value' => $bestowal->gathering_id,
                        'text' => $selectedGatheringText,
                    ]),
                ],
            ) ?>
        </div>
        <?php
        $courtSlotsAvailable = $courtSlotsAvailable ?? false;
        $courtSlotHasScheduledSessions = $courtSlotHasScheduledSessions ?? false;
        $courtSlotHelpText = $courtSlotHelpText ?? '';
        $courtSlotNoScheduleText = $courtSlotNoScheduleText ?? '';
        $courtSlotValue = $courtSlotValue ?? null;
        $hasGatheringSelected = !empty($bestowal->gathering_id);
        $showNoSchedule = $courtSlotsAvailable && !$courtSlotHasScheduledSessions && $hasGatheringSelected;
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
                class="alert alert-info small mb-2 <?= $showNoSchedule ? '' : 'd-none' ?>"
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
            </fieldset>
        </div>
        <div class="col-12">
            <fieldset class="border rounded-3 bg-white shadow-sm p-3">
                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                    <i class="bi bi-link-45deg text-info me-1" aria-hidden="true"></i>
                    <?= __('Recommendation Links & Notes') ?>
                </legend>

        <?= $this->Form->control('reason_summary', [
            'type' => 'textarea',
            'label' => __('Reason Summary'),
            'value' => $bestowal->reason_summary,
            'help' => __(
                'Created from linked recommendation reasons and submitter names. '
                . 'Update this if court notes need a shorter or edited version.',
            ),
            'rows' => 5,
        ]) ?>

        <?php
        $linkedRecommendations = $bestowal->recommendations ?? [];
        $linkedRecommendationCount = count($linkedRecommendations);
        if ($linkedRecommendationCount > 1) : ?>
            <div class="mb-3" data-awards-bestowal-edit-target="unlinkRecommendationsBlock">
                <label class="form-label"><?= __('Unlink Recommendations') ?></label>
                <p class="text-muted small">
                    <?= __(
                        'Unlinked recommendations return to their pre-link state '
                        . '(typically King Approved). At least one recommendation must remain linked.',
                    ) ?>
                </p>
                <?php foreach ($linkedRecommendations as $recommendation) :
                    $awardLabel = $recommendation->award->abbreviation
                        ?? $recommendation->award->name
                        ?? 'Rec #' . $recommendation->id;
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
                ?? 'Rec #' . $onlyRecommendation->id;
            ?>
            <div class="mb-3">
                <label class="form-label"><?= __('Linked Recommendation') ?></label>
                <p class="form-control-plaintext mb-1">
                    <?= h($awardLabel) ?> — <?= h($onlyRecommendation->state) ?>
                </p>
                <p class="text-muted small mb-0">
                    <?= __(
                        'A bestowal must keep at least one linked recommendation. '
                        . 'Link another recommendation before unlinking this one.',
                    ) ?>
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

        <?php if (!empty($linkableRecommendations)) : ?>
            <div class="mb-3" data-awards-bestowal-edit-target="linkRecommendationsBlock">
                <label class="form-label"><?= __('Link Recommendations') ?></label>
                <?php
                $linkedIds = array_map(
                    fn($rec) => (int)$rec->id,
                    $linkedRecommendations,
                );
                foreach ($linkableRecommendations as $recId => $label) :
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
        </div>
        </div>
    </fieldset>
    <?= $this->Form->end() ?>
</turbo-frame>
