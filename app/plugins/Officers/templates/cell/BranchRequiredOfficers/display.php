<?php if (!empty($requiredOffices)) : ?>
    <?php foreach ($requiredOffices as $office) : ?>
        <tr scope="row">
            <th class="col"><?= h($office->name) ?></th>
            <td class="col-10">
                <?php if (!empty($office->current_officers)) : ?>
                    <?php foreach ($office->current_officers as $officer) :
                        if ($officer->email_address != null && $officer->email_address !== ''):
                            $email = true;
                    ?>
                            <a href="mailto:<?= h($officer->email_address) ?>" title="<?= h($officer->email_address) ?>" target="_blank">
                                <?= h($officer->member->sca_name) ?></a>
                        <?php else: ?>
                            <?= h($officer->member->sca_name) ?>
                        <?php endif; ?>
                        (<?= h($officer->start_on_to_string) ?> -
                        <?= h($officer->expires_on_to_string) ?>)
                    <?php endforeach; ?>
                <?php else : ?>
                    <?= __("No officer assigned") ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>