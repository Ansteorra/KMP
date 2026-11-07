<?php
declare(strict_types=1);

/** @var \App\Model\Entity\GatheringActivity $gatheringActivity */
use App\Services\GatheringActivityPresentationService;

$appearance = GatheringActivityPresentationService::appearanceData($gatheringActivity);
?>
<fieldset class="border rounded p-3 mb-3" data-controller="activity-appearance"
    data-activity-appearance-icon-url-value="<?= h($appearance['icon_url'] ?? '') ?>">
    <legend class="float-none w-auto px-2 fs-5"><?= __('Activity appearance') ?></legend>
    <div class="row g-3">
        <div class="col-sm-6">
            <?= $this->Form->control('foreground_color', [
                'type' => 'color', 'label' => __('Foreground color'), 'value' => $appearance['foreground_color'],
                'data-activity-appearance-target' => 'foreground',
                'data-action' => 'input->activity-appearance#preview',
                'aria-describedby' => 'activity-color-help',
            ]) ?>
        </div>
        <div class="col-sm-6">
            <?= $this->Form->control('background_color', [
                'type' => 'color', 'label' => __('Background color'), 'value' => $appearance['background_color'],
                'data-activity-appearance-target' => 'background',
                'data-action' => 'input->activity-appearance#preview',
                'aria-describedby' => 'activity-color-help',
            ]) ?>
        </div>
    </div>
    <p class="form-text" role="status" id="activity-color-help" data-activity-appearance-target="contrast"></p>
    <?= $this->Form->control('icon_source', [
        'label' => __('Icon source'), 'type' => 'select',
        'options' => ['catalog' => __('KMP icon catalog'), 'upload' => __('Uploaded image')],
        'value' => $gatheringActivity->icon_source === 'upload' ? 'upload' : 'catalog',
        'default' => 'catalog', 'data-activity-appearance-target' => 'source',
        'data-action' => 'change->activity-appearance#sourceChanged',
    ]) ?>
    <?= $this->Form->control('icon_name', [
        'label' => __('Selected icon'), 'default' => 'activity', 'value' => $appearance['icon_name'], 'readonly' => true, 'class' => 'form-control',
        'data-activity-appearance-target' => 'iconName',
        'data-action' => 'input->activity-appearance#preview',
        'aria-describedby' => 'activity-icon-help',
    ]) ?>
    <p id="activity-icon-help" class="form-text"><?= __('Choose from the catalog below. Uploaded images remain available as a separate source.') ?></p>
    <button type="button" class="btn btn-outline-secondary d-block mb-2" data-action="activity-appearance#openCatalog">
        <?= __('Browse icon catalog') ?>
    </button>
    <label for="activity-icon-search" class="form-label"><?= __('Search icons') ?></label>
    <input type="search" class="form-control mb-2" id="activity-icon-search"
        data-activity-appearance-target="search" data-action="focus->activity-appearance#openCatalog input->activity-appearance#searchIcons">
    <div class="activity-icon-picker" role="group" aria-label="<?= h(__('KMP icons')) ?>"
        data-activity-appearance-target="icons"></div>
    <p role="status" class="form-text" data-activity-appearance-target="iconCount"></p>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3"
        data-activity-appearance-target="more" data-action="activity-appearance#showMore">
        <?= __('Show more icons') ?>
    </button>
    <?= $this->Form->control('icon_file', [
        'label' => __('Upload an activity icon'), 'type' => 'file', 'accept' => 'image/png,image/webp',
        'data-activity-appearance-target' => 'file', 'data-action' => 'change->activity-appearance#fileChanged',
        'aria-describedby' => 'activity-upload-help',
    ]) ?>
    <p id="activity-upload-help" class="form-text">
        <?= __('PNG or WebP, up to 128 × 128 pixels and 512 KB.') ?>
        <?= __('Use a transparent background for a clear compact icon.') ?>
    </p>
    <?php if ($gatheringActivity->icon_asset_key) : ?>
        <?= $this->Form->control('remove_icon', ['type' => 'checkbox', 'label' => __('Remove uploaded icon')]) ?>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-4 mt-3" aria-label="<?= h(__('Activity appearance previews')) ?>">
        <div>
            <p class="small mb-1"><?= __('Compact / calendar') ?></p>
            <span class="badge gathering-activity-badge p-2" data-activity-appearance-target="compact"></span>
        </div>
        <div>
            <p class="small mb-1"><?= __('Full color / details') ?></p>
            <span class="badge gathering-activity-badge p-2" data-activity-appearance-target="full"></span>
        </div>
    </div>
</fieldset>
