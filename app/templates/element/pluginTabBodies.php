<?php

use App\View\Cell\BasePluginCell;

if (!empty($pluginViewCells[BasePluginCell::PLUGIN_TYPE_TAB])) : ?>
<?php foreach ($pluginViewCells[BasePluginCell::PLUGIN_TYPE_TAB] as $tab) : ?>
<div class="related tab-pane fade m-3 <?= ($activateFirst ? " active show " : "") ?>" id="nav-<?= $tab["id"] ?>"
    role="tabpanel" aria-labelledby="nav-<?= $tab["id"] ?>-tab">
    <?= $this->cell($tab["cell"], [$id]) ?>
</div>
<?php
        $activateFirst = false;
    endforeach; ?>
<?php
endif; ?>