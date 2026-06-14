<turbo-frame id="editRecommendation">
    <?= $this->element('recommendation_bestowal_lock_notice', ['recommendation' => $recommendation]) ?>
    <?php if (!empty($recommendation->current_approval_run)) : ?>
    <div class="alert alert-primary" role="status">
        <div class="fw-semibold">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            <?= __('Approval workflow in progress') ?>
        </div>
        <div>
            <?php $currentStepLabel = $recommendation->current_approval_run->current_step_label ?? __('Approval'); ?>
            <?= __('Current step: {0}', h($currentStepLabel)) ?>
            <?= $this->Html->link(
                __('View approval history and decisions'),
                ['action' => 'view', $recommendation->id, '#' => 'nav-approval'],
                ['class' => 'alert-link', 'data-turbo-frame' => '_top'],
            ) ?>
        </div>
    </div>
    <?php endif; ?>
    <fieldset<?= $recommendation->isLockedByBestowal() ? ' disabled' : '' ?>>
        <?php
        echo $this->Form->hidden('id', ['value' => $recommendation->id, 'data-awards-rec-edit-target' => 'recId']);
        echo $this->Form->control('current_award_id', [
            'type' => 'hidden',
            'value' => $recommendation->award_id,
            'data-awards-rec-edit-target' => 'currentAwardId',
        ]);
        echo $this->Form->control('current_approval_process_id', [
            'type' => 'hidden',
            'value' => $currentApprovalProcessId ?? '',
            'data-awards-rec-edit-target' => 'currentApprovalProcessId',
        ]);
        echo $this->Form->control('approval_workflow_restart_confirmed', [
            'type' => 'hidden',
            'value' => '0',
            'data-awards-rec-edit-target' => 'approvalWorkflowRestartConfirmed',
        ]);
        $url = $this->Url->build([
            'controller' => 'Members',
            'action' => 'AutoComplete',
            'plugin' => null,
        ]);
        $awardsList = [];
        foreach ($awards as $award) {
            $awardsList[$award->id] = [
                'text' => $award->name,
                'specialties' => $award->specialties,
                'approval_process_id' => $award->approval_process_id,
            ];
        }
        $specialties = [];
        if (is_array($recommendation->award->specialties)) {
            foreach ($recommendation->award->specialties as $specialty) {
                $specialties[$specialty] = ['value' => $specialty, 'text' => $specialty];
            }
        }
        ?>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                        <i class="bi bi-person-badge text-primary me-1" aria-hidden="true"></i>
                        <?= __('Recipient') ?>
                    </legend>
                    <?php
                    echo $this->KMP->autoCompleteControl(
                        $this->Form,
                        'member_sca_name',
                        'member_public_id',
                        $url,
                        'Recommendation For',
                        true,
                        true,
                        3,
                        [
                            'data-awards-rec-edit-target' => 'scaMember',
                            'data-action' => 'autocomplete.change->awards-rec-edit#loadScaMemberInfo',
                            'data-ac-init-selection-value' => json_encode([
                                'value' => $recommendation->member->public_id ?? null,
                                'text' => $recommendation->member_sca_name,
                            ]),
                        ],
                    );
                    echo $this->Form->control('not_found', [
                        'type' => 'checkbox',
                        'label' => 'Name not registered in '
                            . $this->KMP->getAppSetting('KMP.ShortSiteTitle')
                            . ' database',
                        'id' => 'recommendation__not_found',
                        'value' => 'on',
                        'disabled' => true,
                        'data-awards-rec-edit-target' => 'notFound',
                    ]);
                    ?>
                    <div class="row mb-2" data-awards-rec-edit-target="externalLinks"></div>
                    <?php
                    echo $this->KMP->comboBoxControl(
                        $this->Form,
                        'branch_name',
                        'branch_id',
                        $branches,
                        'Member Of',
                        true,
                        false,
                        [
                            'data-awards-rec-edit-target' => 'branch',
                            'data-ac-init-selection-value' => json_encode([
                                'value' => $recommendation->branch_id,
                                'text' => $recommendation->branch->name,
                            ]),
                        ],
                    );
                    ?>
                </fieldset>
            </div>
            <div class="col-12 col-lg-6">
                <fieldset class="border rounded-3 bg-white shadow-sm p-3 h-100">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                        <i class="bi bi-award text-success me-1" aria-hidden="true"></i>
                        <?= __('Award') ?>
                    </legend>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <?php
                            echo $this->KMP->comboBoxControl(
                                $this->Form,
                                'domain_name',
                                'domain_id',
                                $awardsDomains->toArray(),
                                'Award Type',
                                true,
                                false,
                                [
                                    'data-action' => 'change->awards-rec-edit#populateAwardDescriptions',
                                    'data-ac-init-selection-value' => json_encode([
                                        'value' => $recommendation->award->domain_id,
                                        'text' => $recommendation->award->domain->name,
                                    ]),
                                    'data-awards-rec-edit-target' => 'domain',
                                ],
                            );
                            ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <?php
                            echo $this->KMP->comboBoxControl(
                                $this->Form,
                                'award_name',
                                'award_id',
                                $awardsList,
                                'Award',
                                true,
                                false,
                                [
                                    'data-awards-rec-edit-target' => 'award',
                                    'data-action' => 'change->awards-rec-edit#populateSpecialties',
                                    'data-ac-init-selection-value' => json_encode([
                                        'value' => $recommendation->award->id,
                                        'text' => $recommendation->award->name,
                                    ]),
                                ],
                            );
                            ?>
                        </div>
                        <div class="col-12">
                            <?php
                            echo $this->KMP->comboBoxControl(
                                $this->Form,
                                'specialty',
                                'specialty_hidden',
                                $specialties,
                                'Specialty',
                                true,
                                true,
                                [
                                    'data-awards-rec-edit-target' => 'specialty',
                                    'data-ac-init-selection-value' => json_encode([
                                        'value' => $recommendation->specialty,
                                        'text' => $recommendation->specialty,
                                    ]),
                                ],
                            );
                            ?>
                        </div>
                    </div>
                </fieldset>
            </div>
            <div class="col-12">
                <fieldset class="border rounded-3 bg-white shadow-sm p-3">
                    <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
                        <i class="bi bi-journal-text text-info me-1" aria-hidden="true"></i>
                        <?= __('Recommendation Details') ?>
                    </legend>
                    <div class="row g-3">
                        <div class="col-12">
                            <?php
                            echo $this->Form->control('reason', [
                                'type' => 'textarea',
                                'label' => 'Reason for Recommendation',
                                'id' => 'recommendation__reason',
                                'value' => $recommendation->reason,
                                'disabled' => 'disabled',
                            ]);
                            ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <?php
                            echo $this->Form->control('contact_email', [
                                'type' => 'email',
                                'label' => 'Contact Email',
                                'value' => $recommendation->contact_email,
                                'disabled' => 'disabled',
                            ]);
                            ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <?php
                            echo $this->Form->control('contact_number', [
                                'type' => 'tel',
                                'label' => 'Contact Number',
                                'value' => $recommendation->contact_number,
                                'disabled' => 'disabled',
                            ]);
                            ?>
                        </div>
                        <div class="col-12">
                            <?php
                            echo $this->Form->control('note', [
                                'type' => 'textarea',
                                'label' => 'Note',
                                'id' => 'recommendation__notes',
                            ]);
                            ?>
                        </div>
                    </div>
                </fieldset>
            </div>
        </div>
    </fieldset>
</turbo-frame>
