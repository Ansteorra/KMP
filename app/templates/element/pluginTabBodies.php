<?php

use App\Services\ViewCellRegistry;

if (!empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_TAB])) : ?>
    <?php foreach ($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_TAB] as $tab) : ?>
        <div class="related tab-pane fade m-3 <?= ($activateFirst ? " active show " : "") ?>" id="nav-<?= $tab["id"] ?>"
            role="tabpanel" aria-labelledby="nav-<?= $tab["id"] ?>-tab" data-detail-tabs-target="tabContent">
            <?= $this->cell($tab["cell"], [$id, $model]) ?>
        </div>
    <?php
        $activateFirst = false;
    endforeach; ?>
<?php
endif; ?>