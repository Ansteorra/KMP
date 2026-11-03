<?php
declare(strict_types=1);

/** @var iterable<\Waivers\Model\Entity\GatheringWaiverLoss> $waiverLosses */
?>
<?php if (count($waiverLosses)) : ?>
<section class="card border-warning mb-3" aria-labelledby="waiver-loss-reports-heading">
    <div class="card-header">
        <h3 id="waiver-loss-reports-heading" class="h5 mb-0"><?= __('Waivers Lost') ?></h3>
    </div>
    <div class="card-body">
        <p>
            <?= __(
                'These waivers were required and reported lost. '
                    . 'Loss reports do not count as uploaded waivers or exemptions.',
            ) ?>
        </p>
        <?php foreach ($waiverLosses as $loss) : ?>
        <article class="border-top pt-3 mb-3">
            <h4 class="h6"><?= $this->Html->link(h($loss->waiver_type->name), [
                'plugin' => 'Waivers', 'controller' => 'GatheringWaivers', 'action' => 'viewLoss', $loss->id,
            ]) ?></h4>
            <p class="small mb-2">
                <?= __(
                    'Recorded {0} by {1}',
                    $this->Timezone->format($loss->created, $gathering, 'M d, Y g:i A'),
                    h($loss->created_by_member?->sca_name ?? __('Unknown')),
                ) ?>
            </p>
            <p class="mb-0" style="white-space: pre-wrap;"><?= h($loss->notes) ?></p>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
