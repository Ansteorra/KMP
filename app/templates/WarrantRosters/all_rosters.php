<turbo-frame id="<?= $turboFrameId ?>" data-turbo='true'>
    <?php if (count($warrantRosters) == 0): ?>

    <div class="mt-3">
        <?= __('No Warrant Rosters found') ?>
    </div>
    <?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th scope="col"><?= $this->Paginator->sort('name') ?></th>
                <th scope="col"><?= h('Warrant Count') ?></th>
                <th scope="col"><?= $this->Paginator->sort('approvals_required') ?></th>
                <th scope="col"><?= $this->Paginator->sort('approval_count') ?></th>
                <th scope="col"><?= $this->Paginator->sort('status') ?></th>
                <th scope="col"><?= $this->Paginator->sort('created') ?></th>
                <th scope="col"><?= h('Created By') ?></th>
                <th scope="col" class="actions"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($warrantRosters as $warrantRoster) : ?>
            <tr>
                <td><?= h($warrantRoster->name) ?></td>
                <td><?= $this->Number->format($warrantRoster->warrant_count) ?></td>
                <td><?= $this->Number->format($warrantRoster->approvals_required) ?></td>
                <td><?= $warrantRoster->approval_count === null ? '' : $this->Number->format($warrantRoster->approval_count) ?>
                </td>
                <td><?= h($warrantRoster->status) ?></td>
                <td><?= h($warrantRoster->created->toDateString()) ?></td>
                <td><?= $warrantRoster->created_by_member === null ? '' : $warrantRoster->created_by_member->sca_name ?>
                </td>
                <td class="actions">
                    <?= $this->Html->link(__('View'), ['action' => 'view', $warrantRoster->id], ['title' => __('View'), 'class' => 'btn btn-secondary',  "data-turbo-frame" => "_top"]) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('«', ['label' => __('First')]) ?>
            <?= $this->Paginator->prev('‹', ['label' => __('Previous')]) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next('›', ['label' => __('Next')]) ?>
            <?= $this->Paginator->last('»', ['label' => __('Last')]) ?>
        </ul>
        <p><?= $this->Paginator->counter(__('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')) ?>
        </p>
    </div>
    <?php endif; ?>
</turbo-frame>