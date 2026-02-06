<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member[] $children
 */

$children = $children ?? [];
?>

<?php if (empty($children)) : ?>
<div class="alert alert-info">
    <?= __('No minor children are linked to this account.') ?>
</div>
<?php else : ?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= __('SCA Name') ?></th>
                <th><?= __('Age') ?></th>
                <th><?= __('Status') ?></th>
                <th><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($children as $child) : ?>
            <tr>
                <td><?= h($child->sca_name) ?></td>
                <td><?= h($child->age) ?></td>
                <td><?= h($child->status) ?></td>
                <td>
                    <?= $this->Html->link(
                        __('View Profile'),
                        ['controller' => 'Members', 'action' => 'view', $child->id],
                        ['class' => 'btn btn-sm btn-primary']
                    ) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
