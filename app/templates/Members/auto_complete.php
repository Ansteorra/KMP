<?php
foreach ($query as $sca_member) :
    //split up the word by the search term so it can be highlighted
    $terms = array_filter([
        preg_quote(h($q), '/'),
        preg_quote(h($nq), '/'),
        preg_quote(h($uq), '/'),
    ], fn($term) => $term !== '');
    $escapedName = h($sca_member->sca_name);
    $sca_name = empty($terms)
        ? $escapedName
        : preg_replace('/(' . implode('|', $terms) . ')/i', '<span class="text-primary">$1</span>', $escapedName); ?>
    <li class="list-group-item" role="option" data-ac-value="<?= h($sca_member->public_id) ?>">
        <?= $sca_name ?></li>
<?php endforeach; ?>