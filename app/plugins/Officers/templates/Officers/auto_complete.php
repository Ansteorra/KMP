<?php
foreach ($query as $sca_member) :
    $extraClasses = "";
    $disabled = "";
    if (!$sca_member->warrantable) {
        $extraClasses = "list-group-item-danger";
        if ($office->requires_warrant) {
            $disabled = "aria-disabled='true'";
            $extraClasses = "list-group-item-secondary";
        }
    }
    //split up the word by the search term so it can be highlighted
    $sca_name = preg_replace('/(' . $q . '|' . $nq . '|' . $uq . ')/i', '<span class="text-primary">$1</span>', $sca_member->sca_name); ?>
<li class="list-group-item <?= $extraClasses ?>" role="option" data-ac-value="<?= h($sca_member->id) ?>"
    <?= $disabled ?>>
    <?= $sca_name ?></li>
<?php endforeach; ?>