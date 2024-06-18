<?php if (!empty($requiredOffices)) : ?>
<?php foreach ($requiredOffices as $office) : ?>
<tr scope="row">
    <th class="col"><?= h($office->name) ?></th>
    <td class="col-10">
        <?php if (!empty($office->current_officers)) : ?>
        <?php foreach ($office->current_officers as $officer) : ?>
        <?= h($officer->member->sca_name) ?> (<?= h($officer->start_on->toDateString()) ?> -
        <?= h($officer->expires_on->toDateString()) ?>)
        <?php endforeach; ?>
        <?php else : ?>
        <?= __("No officer assigned") ?>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>