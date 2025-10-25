<?php

/**
 * Activity Awards Cell Template
 * 
 * Displays awards that can be given out during a specific gathering activity.
 * Shows a list of associated awards with add/remove functionality for authorized users.
 * 
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\GatheringActivity $gatheringActivity
 * @var \Cake\ORM\ResultSet|\Awards\Model\Entity\Award[] $awards
 * @var bool $canEdit
 * @var array $availableAwards
 */

$user = $this->request->getAttribute('identity');
?>

<turbo-frame id="activity-awards-<?= $gatheringActivity->id ?>">
    <div class="related">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <?php if ($canEdit && !empty($availableAwards)) : ?>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAwardModal">
                    <i class="bi bi-plus-circle"></i> <?= __('Add Award') ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($awards) && $awards->count() > 0) : ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?= __('Award') ?></th>
                            <th><?= __('Domain') ?></th>
                            <th><?= __('Level') ?></th>
                            <th><?= __('Branch') ?></th>
                            <th class="actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($awards as $award) : ?>
                            <tr>
                                <td>
                                    <?= h($award->name) ?>
                                    <?php if ($award->abbreviation) : ?>
                                        <small class="text-muted">(<?= h($award->abbreviation) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $award->has('domain') ? h($award->domain->name) : '' ?>
                                </td>
                                <td>
                                    <?= $award->has('level') ? h($award->level->name) : '' ?>
                                </td>
                                <td>
                                    <?= $award->has('branch') ? h($award->branch->name) : '' ?>
                                </td>
                                <td class="actions text-end text-nowrap">
                                    <?php if ($canEdit) : ?>
                                        <?= $this->Form->postLink(
                                            '<i class="bi bi-x-circle-fill"></i>',
                                            ['plugin' => 'Awards', 'controller' => 'Awards', 'action' => 'remove-activity', $award->id, $gatheringActivity->id],
                                            [
                                                'confirm' => __('Remove "{0}" from this activity?', $award->name),
                                                'escape' => false,
                                                'title' => __('Remove'),
                                                'class' => 'btn btn-sm btn-danger',
                                                'data-turbo' => 'true',
                                            ],
                                        ) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div class="alert alert-secondary">
                <i class="bi bi-info-circle"></i>
                <?= __('No awards have been associated with this activity yet.') ?>
                <?php if ($canEdit && !empty($availableAwards)) : ?>
                    <?= __('Click "Add Award" above to get started.') ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</turbo-frame>

<?php if ($canEdit && !empty($availableAwards)) : ?>
    <!-- Add Award Modal -->
    <?= $this->Form->create(null, [
        'url' => [
            'plugin' => 'Awards',
            'controller' => 'Awards',
            'action' => 'add-activity-to-gathering-activity',
            $gatheringActivity->id,
        ],
        'data-turbo' => 'true',
        'data-controller' => 'turbo-modal',
        'data-action' => 'turbo:submit-start->turbo-modal#closeModalBeforeSubmit',
    ]) ?>
    <?= $this->Modal->create('Add Award', [
        'id' => 'addAwardModal',
        'close' => true,
    ]) ?>
    <div class="mb-3">
        <label for="award_id" class="form-label"><?= __('Select Award') ?></label>
        <?= $this->Form->control('award_id', [
            'options' => $availableAwards,
            'empty' => __('-- Select an award --'),
            'class' => 'form-select',
            'label' => false,
            'required' => true,
        ]) ?>
        <div class="form-text">
            <?= __('Select an award that can be given out during this activity.') ?>
        </div>
    </div>
    <?= $this->Modal->end([
        $this->Form->button(__('Add Award'), [
            'class' => 'btn btn-primary',
        ]),
        $this->Form->button(__('Close'), [
            'data-bs-dismiss' => 'modal',
            'type' => 'button',
            'class' => 'btn btn-secondary',
        ]),
    ]) ?>
    <?= $this->Form->end() ?>
<?php endif; ?>