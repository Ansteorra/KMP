<?php

/**
 * App Settings Dataverse Grid Index Template
 * 
 * Modern grid interface with saved views, column picker, filtering, and sorting.
 * Uses lazy-loading turbo-frame architecture for consistent data flow.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\AppSetting $emptyAppSetting
 */

$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': App Settings';
$this->KMP->endBlock();

$this->assign('title', __('App Settings'));
?>

<div class="app-settings index content"
    data-controller="app-setting-modal"
    data-app-setting-modal-edit-url-value="<?= $this->Url->build(['action' => 'edit']) ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3><?= __('App Settings') ?></h3>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                data-bs-target="#addModal">
                <i class="bi bi-plus-circle me-1"></i><?= __('Add') ?>
            </button>
            <?php
            $infoHelpUrl = $this->KMP->getAppSetting("KMP.AppSettings.HelpUrl");
            if ($infoHelpUrl) :
            ?>
                <?= $this->Html->link(
                    __('App Settings Help'),
                    $infoHelpUrl,
                    [
                        "class" => "btn btn-outline-secondary ms-2",
                        "target" => "_blank",
                    ]
                ) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dataverse Grid with Lazy Loading -->
    <?= $this->element('dv_grid', [
        'gridKey' => 'AppSettings.index.main',
        'frameId' => 'app-settings-grid',
        'dataUrl' => $this->Url->build(['action' => 'gridData']),
    ]) ?>
</div>

<?php
// Add modal for creating new app settings
echo $this->KMP->startBlock("modals");
echo $this->Form->create($emptyAppSetting, [
    "url" => ["action" => "add"],
    "id" => "add_entity",
]);
echo $this->Modal->create("Add App Setting", [
    "id" => "addModal",
    "close" => true,
]);
?>
<fieldset>
    <?php
    echo $this->Form->control("name");
    echo $this->Form->control("value");
    ?>
</fieldset>
<?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "add_entity__submit",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
        "type" => "button",
    ]),
]);
echo $this->Form->end();
?>

<!-- Edit App Setting Modal -->
<div class="modal fade"
    id="editAppSettingModal"
    tabindex="-1"
    aria-labelledby="editAppSettingModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Turbo frame for loading edit form -->
            <turbo-frame id="editAppSettingFrame">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('Edit App Setting') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading setting...</p>
                    </div>
                </div>
            </turbo-frame>
        </div>
    </div>
</div>

<?php
$this->KMP->endBlock();
?>