<?php

use Awards\Services\BestowalCourtSlotService;

/**
 * @var \App\View\AppView $this
 * @var \Awards\Model\Entity\Bestowal $bestowal
 */

$this->extend('/layout/TwitterBootstrap/view_record');

$memberName = $bestowal->member->sca_name ?? __('Unknown Member');

echo $this->KMP->startBlock('title');
echo $this->KMP->getAppSetting('KMP.ShortSiteTitle') . ': ' . __('View Bestowal') . ' - ' . h($memberName);
$this->KMP->endBlock();

echo $this->KMP->startBlock('pageTitle');
echo h($memberName);
$this->KMP->endBlock();

echo $this->KMP->startBlock('recordActions');
if ($user->checkCan('edit', $bestowal)) {
    echo $this->Html->tag(
        'button',
        __(' Edit'),
        [
            'type' => 'button',
            'class' => 'btn btn-primary btn-sm edit-bestowal bi-pencil-fill',
            'data-bs-toggle' => 'modal',
            'data-bs-target' => '#editBestowalModal',
            'data-controller' => 'outlet-btn',
            'data-action' => 'click->outlet-btn#fireNotice',
            'data-outlet-btn-btn-data-value' => json_encode(
                ['id' => $bestowal->id],
                JSON_THROW_ON_ERROR,
            ),
        ],
    );
    echo $this->Form->postLink(
        __('Cancel Bestowal'),
        ['action' => 'cancel', $bestowal->id],
        [
            'class' => 'btn btn-danger btn-sm',
            'confirm' => __('Are you sure you want to cancel this bestowal?'),
        ],
    );
}
$this->KMP->endBlock();

echo $this->KMP->startBlock('modals');
if ($user->checkCan('edit', $bestowal)) {
    echo $this->element('bestowalEditModal', [
        'modalId' => 'editBestowalModal',
        'initialBestowalId' => $bestowal->id,
    ]);
}
$this->KMP->endBlock();

echo $this->KMP->startBlock('recordDetails');
?>
<tr>
    <th scope="row"><?= __('Member') ?></th>
    <td>
        <?php if ($bestowal->member_id && $user->checkCan('view', $bestowal->member ?? 'Members')) : ?>
            <?= $this->Html->link(
                h($memberName),
                ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $bestowal->member_id],
            ) ?>
        <?php else : ?>
            <?= h($memberName) ?>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Award to Bestow') ?></th>
    <td>
        <?php if ($bestowal->hasValue('award')) : ?>
            <?= h($bestowal->award->abbreviation ?? $bestowal->award->name ?? '') ?>
            <?php if ($bestowal->award->hasValue('level') && !empty($bestowal->award->level->name)) : ?>
                (<?= h($bestowal->award->level->name) ?>)
            <?php endif; ?>
        <?php else : ?>
            <span class="text-muted"><?= __('Not set') ?></span>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <th scope="row"><?= __('Status') ?></th>
    <td><?= h($bestowal->status) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('State') ?></th>
    <td><?= h($bestowal->state) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Source') ?></th>
    <td><?= h($bestowal->source) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Gathering') ?></th>
    <td>
        <?php if ($bestowal->hasValue('gathering')) : ?>
            <?= h($bestowal->gathering->name) ?>
        <?php endif; ?>
    </td>
</tr>
<?php
$courtSlotLabel = (new BestowalCourtSlotService())->formatCourtSlotDisplay($bestowal);
if ($courtSlotLabel !== '') :
    ?>
<tr>
    <th scope="row"><?= __('Court session') ?></th>
    <td><?= h($courtSlotLabel) ?></td>
</tr>
<?php endif; ?>
<?php if (trim((string)($bestowal->specialty ?? '')) !== '') : ?>
<tr>
    <th scope="row"><?= __('Specialty') ?></th>
    <td><?= h((string)$bestowal->specialty) ?></td>
</tr>
<?php endif; ?>
<tr>
    <th scope="row"><?= __('Herald Notes') ?></th>
    <td><?= $this->Text->autoParagraph(h((string)($bestowal->herald_notes ?? ''))) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Noble Notes') ?></th>
    <td><?= $this->Text->autoParagraph(h((string)($bestowal->noble_notes ?? ''))) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Reason Summary') ?></th>
    <td><?= $this->Text->autoParagraph(h((string)($bestowal->reason_summary ?? ''))) ?></td>
</tr>
<tr>
    <th scope="row"><?= __('Linked Recommendations') ?></th>
    <td>
        <?php if (!empty($bestowal->recommendations)) : ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($bestowal->recommendations as $recommendation) : ?>
                    <?php
                    $recommendationLabel = ($recommendation->award->abbreviation
                        ?? $recommendation->award->name
                        ?? __('Award')) . ' — ' . ($recommendation->member_sca_name ?? '');
                    $recommendationReason = trim((string)($recommendation->reason ?? ''));
                    ?>
                    <li class="mb-3">
                        <div class="fw-semibold">
                            <?= $this->Html->link(
                                $recommendationLabel,
                                [
                                    'plugin' => 'Awards',
                                    'controller' => 'Recommendations',
                                    'action' => 'view',
                                    $recommendation->id,
                                ],
                            ) ?>
                        </div>
                        <?php
                        if (
                            !empty($recommendation->requester_sca_name)
                            || $recommendation->hasValue('requester')
                        ) :
                            ?>
                            <div class="text-muted small">
                                <?= __('Recommended by') ?>
                                <?= h($recommendation->requester->sca_name ?? $recommendation->requester_sca_name) ?>
                            </div>
                        <?php endif; ?>
                        <div class="mt-1">
                            <span class="fw-semibold"><?= __('Reason:') ?></span>
                            <?php if ($recommendationReason !== '') : ?>
                                <?= $this->Text->autoParagraph(h($recommendationReason)) ?>
                            <?php else : ?>
                                <span class="text-muted"><?= __('No reason recorded') ?></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <span class="text-muted"><?= __('None') ?></span>
        <?php endif; ?>
    </td>
</tr>
<?php
$this->KMP->endBlock();
