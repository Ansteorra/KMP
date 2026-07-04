<?php

/**
 * Ad-hoc bestowal modal.
 *
 * @var \App\View\AppView $this
 * @var string $modalId Modal DOM ID
 * @var array<string, mixed>|null $adHocFormData Prepared bestowal form data
 */

use Awards\Model\Entity\Bestowal;

$modalId = $modalId ?? 'adHocBestowalModal';
$formData = $adHocFormData ?? [];
$bestowal = $formData['bestowal'] ?? new Bestowal([
    'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
    'stack_rank' => 0,
    'source' => Bestowal::SOURCE_AD_HOC,
]);
$awardsDomains = $formData['awardsDomains'] ?? [];
$awards = $formData['awards'] ?? [];
$courtSlotList = $formData['courtSlotList'] ?? [];
$courtSlotValue = $formData['courtSlotValue'] ?? null;
$courtSlotsAvailable = $formData['courtSlotsAvailable'] ?? false;
$courtSlotHasScheduledSessions = $formData['courtSlotHasScheduledSessions'] ?? false;
$courtSlotHelpText = $formData['courtSlotHelpText'] ?? '';
$courtSlotNoScheduleText = $formData['courtSlotNoScheduleText'] ?? '';
$gatheringScheduleUrl = $formData['gatheringScheduleUrl'] ?? null;
$gatheringStartDateYmd = $formData['gatheringStartDateYmd'] ?? null;
$courtSessionDates = $formData['courtSessionDates'] ?? [];
$suggestedBestowedDate = $formData['suggestedBestowedDate'] ?? null;
$adHocHelpText = __(
    'Use this when the Crown gives an award on the spot without an existing recommendation.',
);
$courtSlotHelpClass = $courtSlotsAvailable ? '' : 'd-none';
$courtSlotNoScheduleClass = $courtSlotsAvailable && !$courtSlotHasScheduledSessions ? '' : 'd-none';
$awardsDomainOptions = is_object($awardsDomains) && method_exists($awardsDomains, 'toArray')
    ? $awardsDomains->toArray()
    : (array)$awardsDomains;
$awardsList = [];
foreach ($awards as $award) {
    $awardsList[$award->id] = [
        'text' => $award->name,
        'specialties' => $award->specialties,
    ];
}

$memberLookupUrl = $this->Url->build([
    'plugin' => null,
    'controller' => 'Members',
    'action' => 'AutoComplete',
]);
$gatheringLookupUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Bestowals',
    'action' => 'gatheringsForBestowalAutoComplete',
]);
$submitAction = implode(' ', [
    'submit->awards-bestowal-edit#submit',
    'input->awards-bestowal-edit#updateSubmitState',
    'change->awards-bestowal-edit#updateSubmitState',
    'autocomplete.change->awards-bestowal-edit#updateSubmitState',
]);
?>

<div id="<?= h($modalId) ?>Root"
    data-controller="awards-bestowal-edit"
    data-awards-bestowal-edit-modal-id-value="<?= h($modalId) ?>"
    data-awards-bestowal-edit-court-slots-url-value="<?= h($this->Url->build([
        'plugin' => 'Awards',
        'controller' => 'Bestowals',
        'action' => 'courtSlotsForGathering',
    ])) ?>"
    data-awards-bestowal-edit-gatherings-lookup-url-value="<?= h($gatheringLookupUrl) ?>"
    data-awards-bestowal-edit-award-list-url-value="<?= h($this->Url->build([
        'plugin' => 'Awards',
        'controller' => 'Awards',
        'action' => 'awardsByDomain',
    ])) ?>">
    <div class="modal fade"
        id="<?= h($modalId) ?>"
        tabindex="-1"
        aria-labelledby="<?= h($modalId) ?>Label"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <?= $this->Form->create(null, [
                'url' => [
                    'plugin' => 'Awards',
                    'controller' => 'Bestowals',
                    'action' => 'adHoc',
                ],
                'id' => 'ad_hoc_bestowal_form',
                'class' => 'modal-content',
                'data-action' => $submitAction,
            ]) ?>
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="<?= h($modalId) ?>Label">
                        <span class="badge rounded-pill text-bg-warning text-dark" aria-hidden="true">
                            <i class="bi bi-award"></i>
                        </span>
                        <?= __('Record Ad-Hoc Bestowal') ?>
                    </h5>
                    <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="<?= __('Close') ?>"></button>
                </div>
                <div class="modal-body bg-light-subtle">
                    <div class="alert alert-warning border-start border-warning border-4 py-2 mb-3" role="note">
                        <div class="d-flex gap-2">
                            <i class="bi bi-lightning-charge-fill flex-shrink-0" aria-hidden="true"></i>
                            <p class="mb-0">
                                <?= h($adHocHelpText) ?>
                            </p>
                        </div>
                    </div>
                    <script type="application/json" data-awards-bestowal-edit-target="bestowedDateHints" class="d-none">
                        <?= json_encode([
                            'gatheringStartDate' => $gatheringStartDateYmd,
                            'courtSessionDates' => $courtSessionDates,
                            'suggestedBestowedDate' => $suggestedBestowedDate,
                        ]) ?>
                    </script>

                    <?= $this->Form->hidden('current_page', ['value' => $this->request->getRequestTarget()]) ?>
                    <?= $this->Form->hidden('source', ['value' => Bestowal::SOURCE_AD_HOC]) ?>
                    <?= $this->Form->hidden('stack_rank', ['value' => $bestowal->stack_rank ?? 0]) ?>

                    <?= $this->Form->control('current_award_id', [
                        'type' => 'hidden',
                        'data-awards-bestowal-edit-target' => 'currentAwardId',
                    ]) ?>

                    <div class="row g-3 align-items-stretch">
                        <div class="col-12 col-xl-7">
                            <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
                                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                                    <i class="bi bi-person-badge text-primary me-1" aria-hidden="true"></i>
                                    <?= __('Recipient & Award') ?>
                                    <span class="badge text-bg-primary ms-2"><?= __('Required') ?></span>
                                </legend>
                                <div class="row g-3">
                                    <div class="col-12 col-lg-6">
                                        <?= $this->KMP->autoCompleteControl(
                                            $this->Form,
                                            'member_sca_name',
                                            'member_public_id',
                                            $memberLookupUrl,
                                            __('Recipient Name'),
                                            true,
                                            true,
                                            3,
                                            [
                                                'data-awards-bestowal-edit-target' => 'member',
                                                'data-action' =>
                                                    'autocomplete.change->awards-bestowal-edit#onMemberChange',
                                            ],
                                        ) ?>
                                       <div class="form-text">
                                           <?= __(
                                               'Select a member account, or type the recipient SCA name '
                                               . 'if they do not have one.',
                                           ) ?>
                                       </div>
                                    </div>

                                    <div class="col-12 col-lg-6">
                                        <?= $this->KMP->comboBoxControl(
                                            $this->Form,
                                            'domain_name',
                                            'domain_id',
                                            $awardsDomainOptions,
                                            __('Award Type'),
                                            true,
                                            false,
                                            [
                                                'data-action' => implode(' ', [
                                                    'autocomplete.change->awards-bestowal-edit#onDomainChange',
                                                    'change->awards-bestowal-edit#onDomainChange',
                                                ]),
                                                'data-awards-bestowal-edit-target' => 'domain',
                                            ],
                                        ) ?>
                                    </div>

                                    <div class="col-12 col-lg-8">
                                        <?= $this->KMP->comboBoxControl(
                                            $this->Form,
                                            'award_name',
                                            'award_id',
                                            $awardsList,
                                            __('Award to Bestow'),
                                            true,
                                            false,
                                            [
                                                'data-awards-bestowal-edit-target' => 'award',
                                                'data-action' => implode(' ', [
                                                    'autocomplete.change->awards-bestowal-edit#onAwardChange',
                                                    'change->awards-bestowal-edit#onAwardChange',
                                                ]),
                                            ],
                                        ) ?>
                                    </div>

                                    <div class="col-12 col-lg-8 d-none"
                                        data-awards-bestowal-edit-target="specialtyBlock">
                                        <?= $this->KMP->comboBoxControl(
                                            $this->Form,
                                            'specialty',
                                            'specialty_hidden',
                                            [],
                                            __('Specialty'),
                                            false,
                                            true,
                                            [
                                                'data-awards-bestowal-edit-target' => 'specialty',
                                                'data-action' =>
                                                    'change->awards-bestowal-edit#updateSubmitState',
                                            ],
                                        ) ?>
                                        <div class="form-text">
                                            <?= __(
                                                'Select a configured specialty or type the specialty to record.',
                                            ) ?>
                                        </div>
                                    </div>

                                </div>
                            </fieldset>
                        </div>

                        <div class="col-12 col-xl-5">
                            <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
                                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                                    <i class="bi bi-calendar-event text-success me-1" aria-hidden="true"></i>
                                    <?= __('Court & Timing') ?>
                                </legend>
                                <div class="row g-3">
                                    <div class="col-12"
                                        data-awards-bestowal-edit-target="planToGiveBlock">
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
                                            ],
                                        ) ?>
                                    </div>

                                    <div class="col-12"
                                        data-awards-bestowal-edit-target="courtSlotBlock"
                                        data-awards-bestowal-edit-initial-court-slots-available="<?=
                                            $courtSlotsAvailable ? 'true' : 'false'
                                        ?>">
                                        <p
                                            class="form-text text-muted mb-2 <?= $courtSlotHelpClass ?>"
                                            data-awards-bestowal-edit-target="courtSlotHelp">
                                            <?= h($courtSlotHelpText) ?>
                                        </p>
                                        <div
                                            class="alert alert-info small mb-2 <?= $courtSlotNoScheduleClass ?>"
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

                                    <?= $this->Form->control('bestowed_at', [
                                        'type' => 'date',
                                        'label' => __('Bestowed On'),
                                        'data-awards-bestowal-edit-target' => 'givenDate',
                                        'container' => [
                                            'class' => 'col-12 col-md-6',
                                            'data-awards-bestowal-edit-target' => 'givenBlock',
                                        ],
                                    ]) ?>

                                    <div class="col-12 col-md-6">
                                        <?= $this->Form->control('call_into_court', [
                                            'label' => __('Call Into Court'),
                                        ]) ?>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <?= $this->Form->control('person_to_notify', [
                                            'label' => __('Person to Notify'),
                                        ]) ?>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <?= $this->Form->control('court_availability', [
                                            'label' => __('Court Availability'),
                                        ]) ?>
                                    </div>

                                </div>
                            </fieldset>
                        </div>

                        <div class="col-12">
                            <fieldset class="border rounded-3 bg-white shadow-sm p-3">
                                <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                                    <i class="bi bi-journal-text text-secondary me-1" aria-hidden="true"></i>
                                    <?= __('Notes for Court and Records') ?>
                                </legend>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <?= $this->Form->control('note', [
                                            'type' => 'textarea',
                                            'label' => __('General Note'),
                                            'rows' => 4,
                                        ]) ?>
                                    </div>

                                    <div class="col-12 col-lg-6">
                                        <?= $this->Form->control('noble_notes', [
                                            'type' => 'textarea',
                                            'label' => __('Noble Notes'),
                                            'rows' => 4,
                                            'data-awards-bestowal-edit-target' => 'nobleNotes',
                                        ]) ?>
                                    </div>

                                    <div class="col-12 col-lg-6">
                                        <?= $this->Form->control('herald_notes', [
                                            'type' => 'textarea',
                                            'label' => __('Herald Notes'),
                                            'rows' => 4,
                                            'data-awards-bestowal-edit-target' => 'heraldNotes',
                                        ]) ?>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                    <button type="submit"
                        class="btn btn-primary"
                        data-awards-bestowal-edit-target="submitButton"
                        disabled>
                        <?= __('Save Bestowal') ?>
                    </button>
                </div>
                <?= $this->Form->end() ?>
        </div>
    </div>
</div>
