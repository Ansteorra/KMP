<?php

use App\Services\ViewCellRegistry;

if (!empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_TAB])) : ?>
<?php foreach ($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_TAB] as $tab) : ?>
<button class="nav-link <?= ($activateFirst ? " active " : "") ?>" id="nav-<?= $tab["id"] ?>-tab" data-bs-toggle="tab"
    data-bs-target="#nav-<?= $tab["id"] ?>" type="button" role="tab" aria-controls="nav-<?= $tab["id"] ?>"
    aria-selected="false" data-detail-tabs-target='tabBtn'><?= __($tab["label"]) ?>
</button>
<?php
        $activateFirst = false;
    endforeach; ?>
<?php endif; ?>