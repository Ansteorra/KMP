<?php
declare(strict_types=1);
?>
<?php if (count($lossReports)) : ?>
<section class="card mb-3" id="lost-report" aria-labelledby="waiver-loss-notice-heading">
    <div class="card-header">
        <h2 class="h5 mb-0" id="waiver-loss-notice-heading"><?= __('Lost Report') ?></h2>
    </div>
    <div class="card-body">
        <p><?= __('Existing uploaded files remain available for the paper trail.') ?></p>
        <?php foreach ($lossReports as $report) : ?>
        <article class="border-top pt-3 mb-3">
            <h3 class="h6"><?= $report->reversed_at ? __('Lost status reversed') : __('Lost') ?></h3>
            <p class="small">
                <?= __(
                    'Recorded {0} by {1}',
                    $this->Timezone->format($report->created, $gatheringWaiver->gathering, 'M d, Y g:i A'),
                    h($report->created_by_member?->sca_name ?? __('Unknown')),
                ) ?>
            </p>
            <p style="white-space: pre-wrap;"><?= h($report->notes) ?></p>
            <?php if ($report->reversed_at) : ?>
                <p class="small mb-0">
                    <?= __(
                        'Reversed {0} by {1}',
                        $this->Timezone->format($report->reversed_at, $gatheringWaiver->gathering, 'M d, Y g:i A'),
                        h($report->reversed_by_member?->sca_name ?? __('Unknown')),
                    ) ?>
                </p>
            <?php elseif ($canReverseLoss) : ?>
                <?= $this->Form->create(null, ['url' => ['action' => 'reverseLoss', $report->id]]) ?>
                <?= $this->Form->button(__('Reverse Lost Status'), ['class' => 'btn btn-outline-primary']) ?>
                <?= $this->Form->end() ?>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
