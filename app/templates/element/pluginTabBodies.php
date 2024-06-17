<?php if (!empty($pluginViewCells["tabs"])) : ?>
    <?php foreach ($pluginViewCells["tabs"] as $tab) : ?>
        <div class="related tab-pane fade m-3 <?= ($activateFirst ? " active show " : "") ?>" id="nav-<?= $tab["id"] ?>" role="tabpanel" aria-labelledby="nav-<?= $tab["id"] ?>-tab">
            <?= $this->cell($tab["cell"], [$id]) ?>
        </div>
    <?php
        $activateFirst = false;
    endforeach; ?>
<?php
endif; ?>