<?php

/**
 * Award Recommendations Index - Dataverse Grid View
 *
 * @var \App\View\AppView $this
 * @var string $view Current view context
 * @var string $status Current status filter
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Award Recommendations';
$this->KMP->endBlock();
?>

<div class="row align-items-start mb-3">
    <div class="col">
        <h3>Award Recommendations</h3>
        <p class="text-muted mb-0">
            <?= __('Track recommendations through intake, approval, and conversion into bestowals.') ?>
        </p>
    </div>
    <div class="col text-end">
        <?php if ($user->checkCan("add", "Awards.Recommendations")): ?>
            <?= $this->Html->link(
                ' Add Recommendation',
                ['action' => 'add'],
                ['class' => 'btn btn-primary bi bi-plus-circle', 'escape' => false, 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<!-- Dataverse Grid with lazy loading -->
<?= $this->element('dv_grid', [
    'gridKey' => 'Awards.Recommendations.index.main',
    'frameId' => 'recommendations-grid',
    'dataUrl' => $this->Url->build([
        'plugin' => 'Awards',
        'controller' => 'Recommendations',
        'action' => 'gridData',
    ]),
]) ?>

<?php
// Modals
echo $this->KMP->startBlock("modals");

// Edit Recommendation Modal - uses existing element with proper Stimulus controller
echo $this->element('recommendationQuickEditModal', ['modalId' => 'editRecommendationModal']);

// Group Recommendations Modal - confirmation dialog for grouping
if ($user->checkCan("edit", "Awards.Recommendations")):
?>
<div class="modal fade" id="groupRecommendationsModal" tabindex="-1" aria-labelledby="groupRecommendationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => ['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'groupRecommendations'],
                'id' => 'groupRecommendationsForm',
                'data-controller' => 'awards-rec-group',
            ]) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="groupRecommendationsModalLabel">
                    <i class="bi bi-collection"></i> <?= __('Group Recommendations') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light-subtle">
                <div id="groupValidationMessage" class="alert alert-info border-start border-info border-4" data-awards-rec-group-target="validationMessage">
                    <?= __('Selected recommendations will be grouped together. The first selected recommendation will become the group head.') ?>
                </div>
                <div id="groupSelectedIds" data-awards-rec-group-target="selectedIds"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-collection"></i> <?= __('Group Selected') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
<?php
endif;

if ($user->checkCan("requestFeedback", "Awards.Recommendations")):
    echo $this->element('recommendationFeedbackModal', ['modalId' => 'requestRecommendationFeedbackModal']);
endif;

echo $this->element('recommendationWorkflowDecisionModals');

$this->KMP->endBlock();
?>