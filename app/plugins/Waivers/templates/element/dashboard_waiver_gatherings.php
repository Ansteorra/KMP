<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var array $gatherings
 * @var bool $showDaysUntilStart
 */
?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th scope="col"><?= __('Gathering') ?></th>
                <th scope="col"><?= __('Branch') ?></th>
                <th scope="col"><?= __('End Date') ?></th>
                <?php if ($showDaysUntilStart): ?>
                    <th scope="col"><?= __('Days Until Start') ?></th>
                <?php endif; ?>
                <th scope="col"><?= __('Needed Waivers') ?></th>
                <th scope="col"><?= __('Uploaded / Exempted Types') ?></th>
                <th scope="col"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gatherings as $gathering): ?>
                <tr>
                    <th scope="row">
                        <?= $this->Html->link($gathering->name, [
                            'plugin' => null, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id,
                        ]) ?>
                    </th>
                    <td><?= h($gathering->branch->name) ?></td>
                    <td><?= $this->Timezone->format($gathering->end_date, $gathering, 'M d, Y') ?></td>
                    <?php if ($showDaysUntilStart): ?>
                        <td>
                            <?php if ($gathering->has_started): ?>
                                <?= __('Started') ?>
                            <?php else: ?>
                                <?= (int)$gathering->days_until_start ?>
                                <?= __n('day', 'days', $gathering->days_until_start) ?>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <span class="badge bg-danger"><?= (int)$gathering->missing_waiver_count ?></span>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($gathering->missing_waiver_names as $waiverName): ?>
                                <li><?= h($waiverName) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </td>
                    <td>
                        <span class="badge bg-info text-dark"><?= (int)$gathering->uploaded_waiver_count ?></span>
                        <?php if (!empty($gathering->uploaded_waiver_names)): ?>
                            <ul class="mb-0 mt-1">
                                <?php foreach ($gathering->uploaded_waiver_names as $waiverName): ?>
                                    <li><?= h($waiverName) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="small text-muted mt-1"><?= __('None') ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $this->Html->link(__('View Waivers'), [
                            'plugin' => 'Waivers', 'controller' => 'GatheringWaivers', 'action' => 'index',
                            '?' => ['gathering_id' => $gathering->id],
                        ], [
                            'class' => 'btn btn-sm btn-primary',
                            'aria-label' => __('View waivers for {0}', $gathering->name),
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
