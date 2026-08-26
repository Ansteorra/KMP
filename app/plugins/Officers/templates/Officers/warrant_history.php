<?php
declare(strict_types=1);

use App\Model\Entity\ActiveWindowBaseEntity;
use App\Model\Entity\Warrant;

/**
 * Warrant history sub-row for a member's officer assignment.
 *
 * @var \App\View\AppView $this
 * @var \Officers\Model\Entity\Officer $officer
 * @var array<\App\Model\Entity\Warrant> $warrants
 */

$headingId = 'warrant-history-heading-' . $officer->id;
$statusClasses = [
    ActiveWindowBaseEntity::CURRENT_STATUS => 'bg-success',
    Warrant::PENDING_STATUS => 'bg-warning text-dark',
    ActiveWindowBaseEntity::EXPIRED_STATUS => 'bg-secondary',
    ActiveWindowBaseEntity::DEACTIVATED_STATUS => 'bg-secondary',
    ActiveWindowBaseEntity::CANCELLED_STATUS => 'bg-secondary',
    ActiveWindowBaseEntity::REPLACED_STATUS => 'bg-secondary',
    Warrant::DECLINED_STATUS => 'bg-secondary',
];
?>
<section class="px-3 py-2" aria-labelledby="<?= h($headingId) ?>">
    <header class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
        <h6 id="<?= h($headingId) ?>" class="mb-0"><?= __('Warrant History') ?></h6>
        <p class="text-body-secondary small mb-0">
            <?= h($officer->office->name) ?> <span aria-hidden="true">&middot;</span>
            <?= h($officer->branch->name) ?>
        </p>
    </header>

    <?php if (empty($warrants)) : ?>
        <p class="mb-0 text-muted"><?= __('No warrants have been recorded for this office assignment.') ?></p>
    <?php else : ?>
        <div class="table-responsive overflow-y-hidden">
            <table class="table table-sm table-striped align-middle w-auto mb-0">
                <caption class="visually-hidden">
                    <?= __('Warrant periods for {0} at {1}', $officer->office->name, $officer->branch->name) ?>
                </caption>
                <thead>
                    <tr>
                        <th scope="col" class="pe-4"><?= __('Status') ?></th>
                        <th scope="col" class="pe-4"><?= __('Starts') ?></th>
                        <th scope="col" class="pe-4"><?= __('Expires') ?></th>
                        <th scope="col"><?= __('Reason Ended') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warrants as $warrant) : ?>
                        <tr>
                            <td class="pe-4">
                                <span class="badge <?= h($statusClasses[$warrant->status] ?? 'bg-secondary') ?>">
                                    <?= h($warrant->status) ?>
                                </span>
                            </td>
                            <td class="pe-4 text-nowrap">
                                <?php if ($warrant->start_on) : ?>
                                    <time
                                        datetime="<?= h($this->Timezone->date($warrant->start_on, 'Y-m-d')) ?>"
                                        aria-label="<?= h($this->Timezone->date($warrant->start_on)) ?>">
                                        <?= h($this->Timezone->date($warrant->start_on, 'M j, Y')) ?>
                                    </time>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-nowrap">
                                <?php if ($warrant->expires_on) : ?>
                                    <time
                                        datetime="<?= h($this->Timezone->date($warrant->expires_on, 'Y-m-d')) ?>"
                                        aria-label="<?= h($this->Timezone->date($warrant->expires_on)) ?>">
                                        <?= h($this->Timezone->date($warrant->expires_on, 'M j, Y')) ?>
                                    </time>
                                <?php else : ?>
                                    <?= __('No expiration') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $warrant->revoked_reason ? h($warrant->revoked_reason) : '&mdash;' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
