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

// Bulk Edit Modal - only render if user has edit permission
if ($user->checkCan("edit", "Awards.Recommendations") && isset($rules) && isset($statusList)):
    echo $this->element('recommendationsBulkEditModal', ['modalId' => 'bulkEditRecommendationModal']);
endif;

$this->KMP->endBlock();
?>