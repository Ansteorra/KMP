<turbo-frame id="editRecommendation">
    <?= $this->element('recommendation_bestowal_lock_notice', ['recommendation' => $recommendation]) ?>
    <?php if (!empty($recommendation->current_approval_run)) : ?>
    <div class="alert alert-primary" role="status">
        <div class="fw-semibold">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            <?= __('Approval workflow in progress') ?>
        </div>
        <div>
            <?= __('Current step: {0}', h($recommendation->current_approval_run->current_step_label ?? __('Approval'))) ?>
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
        $url = $this->Url->build([
            'controller' => 'Members',
            'action' => 'AutoComplete',
            'plugin' => null,
        ]);
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
        ]); ?>
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
        $awardsList = [];
        foreach ($awards as $award) {
            $awardsList[$award->id] = [
                'text' => $award->name,
                'specialties' => $award->specialties,
                'approval_process_id' => $award->approval_process_id,
            ];
        }
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
        $specialties = [];
        if (is_array($recommendation->award->specialties)) {
            foreach ($recommendation->award->specialties as $specialty) {
                $specialties[$specialty] = ['value' => $specialty, 'text' => $specialty];
            }
        }
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
        echo $this->Form->control('reason', [
            'type' => 'textarea',
            'label' => 'Reason for Recommendation',
            'id' => 'recommendation__reason',
            'value' => $recommendation->reason,
            'disabled' => 'disabled',
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
        echo $this->Form->control('note', [
            'type' => 'textarea',
            'label' => 'Note',
            'id' => 'recommendation__notes',
        ]);
        ?>
    </fieldset>
</turbo-frame>
