<?php

/**
 * Quick-edit recommendation form loaded inside turbo-frame (grid modal).
 *
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Recommendation $recommendation
 */

$formUrl = $this->Url->build([
    'plugin' => 'Awards',
    'controller' => 'Recommendations',
    'action' => 'edit',
    $recommendation->id,
]);
$submitAction = implode(' ', [
    'submit->awards-rec-quick-edit#submit',
    'submit->turbo-modal#submitAsTurboStream',
    'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
]);
?>
<turbo-frame id="editRecommendationQuick">
    <?= $this->Form->create(null, [
        'url' => $formUrl,
        'id' => 'recommendation_form',
        'data-turbo' => 'true',
        'data-controller' => 'turbo-modal',
        'data-action' => $submitAction,
    ]) ?>
    <?= $this->Form->hidden('page_context_url', [
        'value' => '',
    ]) ?>
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
        echo $this->Form->hidden('id', [
            'value' => $recommendation->id,
            'data-awards-rec-quick-edit-target' => 'recId',
        ]);
        echo $this->Form->hidden('member_id', [
            'value' => $recommendation->member_id,
            'data-awards-rec-quick-edit-target' => 'memberId',
        ]);
        ?>
        <div style="margin:0 !important;" class="form-group text pb-3"><label class="form-label"
                for="member-sca-name"><?= __('Recommendation For') ?></label>
            <div class="input-group ps-3"><strong><?= h($recommendation->member_sca_name) ?></strong></div>
        </div>
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
                'data-action' => 'change->awards-rec-quick-edit#populateAwardDescriptions',
                'data-ac-init-selection-value' => json_encode([
                    'value' => $recommendation->award->domain_id,
                    'text' => $recommendation->award->domain->name,
                ]),
                'data-awards-rec-quick-edit-target' => 'domain',
            ],
        );
        echo $this->Form->control('current_award_id', [
            'type' => 'hidden',
            'value' => $recommendation->award_id,
            'data-awards-rec-quick-edit-target' => 'currentAwardId',
        ]);
        echo $this->Form->control('current_approval_process_id', [
            'type' => 'hidden',
            'value' => $currentApprovalProcessId ?? '',
            'data-awards-rec-quick-edit-target' => 'currentApprovalProcessId',
        ]);
        echo $this->Form->control('approval_workflow_restart_confirmed', [
            'type' => 'hidden',
            'value' => '0',
            'data-awards-rec-quick-edit-target' => 'approvalWorkflowRestartConfirmed',
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
                'data-awards-rec-quick-edit-target' => 'award',
                'data-action' => 'change->awards-rec-quick-edit#populateSpecialties',
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
                'data-awards-rec-quick-edit-target' => 'specialty',
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

        echo $this->Form->control('note', [
            'type' => 'textarea',
            'label' => 'Note',
            'id' => 'recommendation__notes',
        ]);
        ?>
    </fieldset>
    <?= $this->Form->end() ?>
</turbo-frame>
