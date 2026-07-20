<?php
foreach ($query as $role) :
    $highlighted_name = preg_replace('/(' . preg_quote($q, '/') . ')/i', '<span class="text-primary">$1</span>', h($role->name)); ?>
    <li class="list-group-item" role="option" data-ac-value="<?= h($role->name) ?>">
        <?= $highlighted_name ?></li>
<?php endforeach; ?>
