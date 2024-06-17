<?php if (!empty($pluginViewCells["tabs"])) : ?>
<?php foreach ($pluginViewCells["tabs"] as $tab) : ?>
<button class="nav-link <?= ($activateFirst ? " active " : "") ?>" id="nav-<?= $tab["id"] ?>-tab" data-bs-toggle="tab"
    data-bs-target="#nav-<?= $tab["id"] ?>" type="button" role="tab" aria-controls="nav-<?= $tab["id"] ?>"
    aria-selected="false"><?= __($tab["label"]) ?>
</button>
<?php
        $activateFirst = false;
    endforeach; ?>
<?php endif; ?>