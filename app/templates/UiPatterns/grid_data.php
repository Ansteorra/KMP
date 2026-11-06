<?php
declare(strict_types=1);
?>
<turbo-frame id="ui-patterns-grid-table" data-grid-src="<?= h($this->getRequest()->getRequestTarget()) ?>">
    <?= $this->element('uiPatterns/grid_results') ?>
</turbo-frame>
