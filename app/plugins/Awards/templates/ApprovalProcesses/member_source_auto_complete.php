<?php
/**
 * @var \Cake\Collection\CollectionInterface|\Cake\ORM\ResultSet<\App\Model\Entity\Member> $members
 * @var string $q
 */

$term = preg_quote($q, '/');
?>
<?php foreach ($members as $member) : ?>
    <?php
    $displayName = $member->branch?->name
        ? $member->branch->name . ': ' . $member->sca_name
        : $member->sca_name;
    $safeName = h($displayName);
    $highlightedName = $q !== ''
        ? preg_replace('/(' . $term . ')/i', '<span class="text-primary">$1</span>', $safeName)
        : $safeName;
    ?>
    <li class="list-group-item" role="option" data-ac-value="<?= h((string)$member->id) ?>">
        <?= $highlightedName ?>
    </li>
<?php endforeach; ?>
