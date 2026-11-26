<?php
declare(strict_types=1);

$similarRecommendation = $similarRecommendation ?? null;
$includeCurrent = $includeCurrent ?? false;
$frameId = $similarRecommendation === null
    ? 'recommendations-grid'
    : 'similar-recs-grid-' . $similarRecommendation->id;
$postUrl = ['plugin' => 'Awards', 'controller' => 'Recommendations', 'action' => 'groupRecommendations'];
if ($similarRecommendation !== null) {
    $postUrl['action'] = 'groupSimilarRecommendations';
    $postUrl[] = $similarRecommendation->id;
}
$current = $includeCurrent ? [
    'id' => (string)$similarRecommendation->id,
    'activeBestowal' => $similarRecommendation->isLockedByBestowal(),
] : [];
?>
<div class="modal fade" id="groupRecommendationsModal" tabindex="-1"
    aria-labelledby="groupRecommendationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <?= $this->Form->create(null, [
                'url' => $postUrl,
                'id' => 'groupRecommendationsForm',
                'data-turbo' => $similarRecommendation === null ? 'true' : 'false',
                'data-awards-rec-group-frame-id-value' => $frameId,
                'data-awards-rec-group-similar-value' => $similarRecommendation !== null ? 'true' : 'false',
                'data-awards-rec-group-current-value' => json_encode((object)$current),
                'data-controller' => $similarRecommendation === null
                    ? 'awards-rec-group turbo-modal' : 'awards-rec-group',
            ] + ($similarRecommendation === null ? [
                'data-action' => 'submit->turbo-modal#submitAsTurboStream'
                    . ' turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
            ] : [])) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="groupRecommendationsModalLabel">
                    <kmp-icon name="layers-linked"  aria-hidden="true"></kmp-icon> <?= __('Group Recommendations') ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="<?= h(__('Close')) ?>"></button>
            </div>
            <div class="modal-body bg-light-subtle">
                <div id="groupValidationMessage" class="alert alert-info border-start border-info border-4"
                    data-awards-rec-group-target="validationMessage" role="status">
                    <?= __('Selected recommendations will be grouped together.') ?>
                </div>
                <?php if ($similarRecommendation === null) : ?>
                    <?= $this->Form->hidden('page_context_url', ['value' => '']) ?>
                <?php endif; ?>
                <div id="groupSelectedIds" data-awards-rec-group-target="selectedIds"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
                <button type="submit" class="btn btn-primary" data-awards-rec-group-target="submit" disabled>
                    <kmp-icon name="layers-linked"  aria-hidden="true"></kmp-icon> <?= __('Group Selected') ?>
                </button>
            </div>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>
