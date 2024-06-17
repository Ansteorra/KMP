{
"member": <?= json_encode($member) ?>
<?php if (isset($pluginViewCells['json']) && !empty($pluginViewCells['json'])) :
    foreach ($pluginViewCells['json'] as $cell) : ?>
, "<?= $cell['id'] ?>": <?= $this->cell($cell["cell"], [$member->id]) ?>
<?php endforeach;
endif; ?>
}