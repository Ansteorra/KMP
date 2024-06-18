{
"member": <?= json_encode($member) ?>
<?php

use App\View\Cell\BasePluginCell;

if (isset($pluginViewCells[BasePluginCell::PLUGIN_TYPE_JSON]) && !empty($pluginViewCells[BasePluginCell::PLUGIN_TYPE_JSON])) :
    foreach ($pluginViewCells[BasePluginCell::PLUGIN_TYPE_JSON] as $cell) : ?>
, "<?= $cell['id'] ?>": <?= $this->cell($cell["cell"], [$member->id]) ?>
<?php endforeach;
endif; ?>
}