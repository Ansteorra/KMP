<?php

use App\KMP\StaticHelpers;
//find out which tab should be selected by first seeing if any tab has selected true.. then check if that tab data is empty.. if it is select the next one.
$selected = false;
foreach ($tabs as $tab) {
    if ($tab["selected"]) {
        $selected = true;
    }
    if ($selected) {
        break;
    }
}
if (!$selected) {
    foreach ($tabs as &$tab) {
        $tabs[0]["selected"] = true;
    }
}
if (!isset($updateUrl)) {
    $updateUrl = true;
}
//if no tab is selected, select the first tab with data
?>
<div class="row" data-controller="detail-tabs" data-detail-tabs-update-url-value="<?= $updateUrl ? 'true' : 'false' ?>">
    <nav>
        <div class="nav nav-tabs" id="nav-<?= $tabGroupName ?>" role="tablist">
            <?php foreach ($tabs as &$tab) { ?>
            <button class="nav-link" id="nav-<?= $tab["id"] ?>-tab" data-bs-toggle="tab"
                data-bs-target="#nav-<?= $tab["id"] ?>" type="button" role="tab" data-level="activityWindow"
                aria-controls="nav-<?= $tab["id"] ?>" aria-selected="false"
                data-detail-tabs-target='tabBtn'><?= $tab["label"] ?>
                <?php if (isset($tab["badge"]) && $tab["badge"] != "" && $tab["badge"] > 0) { ?>
                <span class="badge <?= $tab["badgeClass"] ?>"><?= $tab["badge"] ?></span>
                <?php } ?>
            </button>
            <?php } ?>
        </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <?php foreach ($tabs as &$tab) { ?>
        <div class="tab-pane fade" id="nav-<?= $tab["id"] ?>" role="tabpanel"
            aria-labelledby="nav-<?= $tab["id"] ?>-tab" data-detail-tabs-target="tabContent">
            <turbo-frame id="<?= $tab["id"] ?>-frame" loading="lazy" src="<?= $tab["turboUrl"] ?>" data-turbo='true'>
            </turbo-frame>
        </div>
        <?php } ?>
    </div>
</div>