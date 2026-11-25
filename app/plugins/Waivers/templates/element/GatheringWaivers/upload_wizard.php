<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var array $waiverTypesData
 * @var array $uploadLimits
 * @var array $waiverStatusSummary
 * @var bool $mobile
 * @var string|null $pageContextUrl
 * @var int|null $preSelectedWaiverTypeId
 */

$controller = 'waiver-upload-wizard';
$attestUrl = $this->Url->build(['plugin' => 'Waivers', 'controller' => 'GatheringWaivers', 'action' => 'attest']);
$gatheringUrl = ['plugin' => false, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id];
$gatheringViewUrl = $this->Url->build($gatheringUrl + ['?' => ['tab' => 'gathering-waivers']]);
$mobileSelectUrl = $this->Url->build([
    'plugin' => 'Waivers', 'controller' => 'GatheringWaivers', 'action' => 'mobileSelectGathering',
]);
?>
<div data-controller="waiver-upload-wizard"
    data-waiver-upload-wizard-gathering-id-value="<?= h($gathering->id) ?>"
    data-waiver-upload-wizard-gathering-public-id-value="<?= h($gathering->public_id) ?>"
    data-waiver-upload-wizard-return-url-value="<?= h($pageContextUrl ?? '') ?>"
    data-waiver-upload-wizard-submit-waivers-label-value="<?= h(__('Submit Waivers')) ?>"
    data-waiver-upload-wizard-submit-attestation-label-value="<?= h(__('Submit Attestation')) ?>"
    data-waiver-upload-wizard-max-file-size-value="<?= h($uploadLimits['maxFileSize']) ?>"
    data-waiver-upload-wizard-total-max-size-value="<?= h($uploadLimits['postMaxSize']) ?>"
    data-waiver-upload-wizard-attest-url-value="<?= h($attestUrl) ?>"
    data-waiver-upload-wizard-gathering-view-url-value="<?= h($gatheringViewUrl) ?>"
    data-waiver-upload-wizard-mobile-select-url-value="<?= h($mobileSelectUrl) ?>"
    <?php if (!empty($preSelectedWaiverTypeId)) : ?>
    data-waiver-upload-wizard-pre-selected-waiver-type-id-value="<?= h($preSelectedWaiverTypeId) ?>"
    <?php endif; ?>>

    <?= $this->element('wizard/steps', [
        'label' => __('Waiver upload steps'),
        'steps' => [__('Waiver type'), __('Waiver details'), __('Review')],
        'panelIdPrefix' => $mobile ? 'mobile-waiver-upload-step-' : 'waiver-upload-step-',
        'controller' => $controller,
    ]) ?>

    <?= $this->element(
        $mobile ? 'GatheringWaivers/mobile_wizard_steps' : 'GatheringWaivers/upload_wizard_steps',
        compact('waiverTypesData', 'gathering', 'uploadLimits', 'waiverStatusSummary'),
    ) ?>

    <?= $this->element('wizard/actions', [
        'controller' => $controller,
        'cancelUrl' => $mobile ? $mobileSelectUrl : $gatheringUrl,
        'submitLabel' => __('Submit Waivers'),
        'submitOptions' => [
            'type' => 'button',
            'data-action' => 'waiver-upload-wizard#submitForm',
            'hidden' => true,
        ],
    ]) ?>
</div>
