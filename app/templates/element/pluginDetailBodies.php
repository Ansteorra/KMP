<?php

use App\Services\ViewCellRegistry;

if (!empty($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_DETAIL])) : ?>
<?php foreach ($pluginViewCells[ViewCellRegistry::PLUGIN_TYPE_DETAIL] as $details) : ?>
<?php $cellOutput = $this->cell($details["cell"], [$id]);
        echo $cellOutput;
?>
<?php
    endforeach;
endif; ?>