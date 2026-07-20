<?php
foreach ($query as $permission) :
    $highlighted_name = preg_replace('/(' . preg_quote($q, '/') . ')/i', '<span class="text-primary">$1</span>', h($permission->name)); ?>
    <li class="list-group-item" role="option" data-ac-value="<?= h($permission->name) ?>">
        <?= $highlighted_name ?></li>
<?php endforeach; ?>
