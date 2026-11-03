<?php
declare(strict_types=1);

use Cake\I18n\Date;

/** @var \App\View\AppView $this */
?>
<nav class="d-flex flex-wrap gap-3 pt-3 px-2" aria-label="<?= h(__('On this overview')) ?>">
    <a href="#reader-about-heading"><?= __('About') ?></a>
    <a href="#reader-schedule-heading"><?= __('Schedule') ?></a>
    <a href="#reader-activities-heading"><?= __('Activities') ?></a>
    <a href="#reader-contacts-heading"><?= __('Contacts') ?></a>
</nav>
<div class="gathering-reader-overview">
    <div class="gathering-reader-main">
        <?php if ($progressAttendances !== []) : ?>
            <section class="gathering-reader-progress" aria-labelledby="reader-progress-heading">
                <h2 class="h6" id="reader-progress-heading">
                    <?= $this->Icon->render('crown') ?> <?= __('Royal Progress') ?>
                </h2>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($progressAttendances as $attendance) : ?>
                        <li><strong><?= h($attendance->progress_title) ?></strong>
                            <?= h($attendance->member->sca_name ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <section class="gathering-reader-section" aria-labelledby="reader-about-heading">
            <h2 id="reader-about-heading"><?= __('About this gathering') ?></h2>
            <?php if ($gathering->description) : ?>
                <div class="markdown-content"><?= $this->Markdown->toHtml($gathering->description) ?></div>
            <?php else : ?>
                <p class="text-muted mb-0"><?= __('Event details haven’t been added yet.') ?></p>
            <?php endif; ?>
        </section>
        <section class="gathering-reader-section" aria-labelledby="reader-schedule-heading">
            <h2 id="reader-schedule-heading"><?= __('Schedule') ?></h2>
            <?php if ($scheduleByDate === []) : ?>
                <p class="text-muted mb-0"><?= __('The schedule hasn’t been announced yet.') ?></p>
            <?php else : ?>
                <?php foreach ($scheduleByDate as $date => $items) : ?>
                    <h3 class="h6 mt-3"><?= h(Date::parse($date)->format('l, F j, Y')) ?></h3>
                    <ol class="gathering-reader-schedule">
                        <?php foreach ($items as $scheduled) : ?>
                            <li>
                                <div class="gathering-reader-schedule-time">
                                    <time datetime="<?= h($scheduled->start_datetime->toIso8601String()) ?>">
                                        <?= $this->Timezone->format(
                                            $scheduled->start_datetime,
                                            'g:i A',
                                            false,
                                            null,
                                            $gathering,
                                        ) ?>
                                    </time>
                                    <?php if ($scheduled->end_datetime) : ?>
                                        <span class="text-muted d-block">
                                            –
                                            <?= $this->Timezone->format(
                                                $scheduled->end_datetime,
                                                'g:i A',
                                                false,
                                                null,
                                                $gathering,
                                            ) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="h6 mb-1"><?= h($scheduled->display_title) ?></h4>
                                    <?php if ($scheduled->gathering_activity) :
                                        ?><?= $this->GatheringActivity->badge($scheduled->gathering_activity) ?><?php
                                    endif; ?>
                                    <?php if ($scheduled->display_description) : ?>
                                        <div class="markdown-content small mt-2">
                                            <?= $this->Markdown->toHtml($scheduled->display_description) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($scheduled->pre_register) : ?>
                                        <p class="small mt-2 mb-0">
                                            <?= __('Pre-registration required. Contact the organizers for details.') ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
    <aside class="gathering-reader-aside" aria-label="<?= h(__('Activities and contacts')) ?>">
        <section class="gathering-reader-section" aria-labelledby="reader-activities-heading">
            <h2 id="reader-activities-heading"><?= __('Activities') ?></h2>
            <?php if ($gathering->gathering_activities === []) : ?>
                <p class="text-muted mb-0"><?= __('Activities haven’t been announced yet.') ?></p>
            <?php else : ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($gathering->gathering_activities as $activity) : ?>
                        <li class="mb-3">
                            <?= $this->GatheringActivity->badge($activity) ?>
                            <?php $description = $activity->_joinData->custom_description ?? $activity->description; ?>
                            <?php if ($description) :
                                ?><p class="small mt-2 mb-0"><?= nl2br(h($description)) ?></p><?php
                            endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section class="gathering-reader-section" aria-labelledby="reader-contacts-heading">
            <h2 id="reader-contacts-heading"><?= __('Event contacts') ?></h2>
            <?php if ($readerStaff === []) : ?>
                <p class="text-muted mb-0"><?= __('Contact information hasn’t been shared yet.') ?></p>
            <?php else : ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($readerStaff as $staff) : ?>
                        <li class="mb-3">
                            <strong class="d-block"><?= h($staff->display_name) ?></strong>
                            <span class="small text-muted d-block"><?= h($staff->role) ?></span>
                            <?php if ($staff->email) :
                                ?><?= $this->Html->link(
                                    $staff->email,
                                    'mailto:' . $staff->email,
                                    ['class' => 'd-block small'],
                                ) ?><?php
                            endif; ?>
                            <?php if ($staff->phone) :
                                ?><?= $this->Html->link(
                                    $staff->phone,
                                    'tel:' . $staff->phone,
                                    ['class' => 'd-block small'],
                                ) ?><?php
                            endif; ?>
                            <?php if ($staff->contact_notes) :
                                ?><p class="small mb-0"><?= h($staff->contact_notes) ?></p><?php
                            endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php if ($kingdomAttendances !== []) : ?>
            <section class="gathering-reader-section" aria-labelledby="reader-attendees-heading">
                <h2 id="reader-attendees-heading"><?= __('Who’s going') ?></h2>
                <p class="small text-muted"><?= __('Attendees sharing with the kingdom') ?></p>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($kingdomAttendances as $attendance) : ?>
                        <li class="mb-2"><strong><?= h($attendance->member->sca_name ?? '') ?></strong>
                            <?php if ($attendance->public_note) :
                                ?><div class="small"><?= h($attendance->public_note) ?></div><?php
                            endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </aside>
</div>
