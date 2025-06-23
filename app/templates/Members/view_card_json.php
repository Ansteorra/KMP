{
"member": <?= json_encode($member) ?>
<?php

use App\Services\ViewCellRegistry;

if (isset($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_JSON]) && !empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_JSON])) :
    foreach ($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_JSON] as $cell) : ?>
, "<?= $cell['id'] ?>": <?= $this->cell($cell["cell"], [$member->id]) ?>
<?php endforeach;
endif; ?>
}