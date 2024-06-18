<?php

use App\View\Cell\BasePluginCell;

if (!empty($pluginViewCells[BasePluginCell::PLUGIN_TYPE_DETAIL])) : ?>
<?php foreach ($pluginViewCells[BasePluginCell::PLUGIN_TYPE_DETAIL] as $details) : ?>
<?php $cellOutput = $this->cell($details["cell"], [$id]);
        echo $cellOutput;
?>
<?php
    endforeach;
endif; ?>