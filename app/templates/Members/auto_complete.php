<?php
foreach ($query as $sca_member) :
    //split up the word by the search term so it can be highlighted
    $sca_name = preg_replace('/(' . $q . ')/i', '<span class="text-primary">$1</span>', h($sca_member->sca_name)); ?>
<li class="list-group-item" role="option" data-ac-value="<?= h($sca_member->id) ?>">
    <?= $sca_name ?></li>
<?php endforeach; ?>