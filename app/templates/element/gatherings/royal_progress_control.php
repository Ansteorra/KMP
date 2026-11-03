<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var array<\Officers\Model\Entity\Officer> $progressOfficers
 * @var \App\Model\Entity\GatheringAttendance|null $userAttendance
 * @var string $controlIdPrefix
 */
$isEdit = !empty($userAttendance);
$progressEnabled = !$isEdit || $userAttendance->is_royal_progress;
?>
<?php if (!empty($progressOfficers) || ($isEdit && $userAttendance->is_royal_progress)) : ?>
    <fieldset class="border rounded-3 bg-white shadow-sm p-3 mb-3">
        <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-3">
            <kmp-icon name="diamond" class="text-warning me-1" aria-hidden="true"></kmp-icon>
            <?= __('Royal Progress') ?>
        </legend>
        <?php
        $progressOptions = [];
        foreach ($progressOfficers as $progressOfficer) {
            $label = $progressOfficer->office->name;
            if (!empty($progressOfficer->branch->name)) {
                $label .= ' of ' . $progressOfficer->branch->name;
            }
            $progressOptions[$progressOfficer->id] = $label;
        }
        $selectedProgressOfficer = array_key_first($progressOptions);
        if ($isEdit && $userAttendance->is_royal_progress) {
            // Keep the saved snapshot when the original office/branch is no longer held.
            $selectedProgressOfficer = 'preserve';
            foreach ($progressOfficers as $progressOfficer) {
                if (
                    $progressOfficer->office_id === $userAttendance->progress_office_id
                    && $progressOfficer->office->name === $userAttendance->progress_office_name
                    && ($progressOfficer->branch->name ?? null) === $userAttendance->progress_branch_name
                ) {
                    $selectedProgressOfficer = $progressOfficer->id;
                    break;
                }
            }
            if ($selectedProgressOfficer === 'preserve') {
                $progressOptions['preserve'] = $userAttendance->progress_title;
            }
        }
        ?>
        <?= $this->Form->control('royal_progress_enabled', [
            'type' => 'checkbox',
            'id' => $controlIdPrefix . '-royal-progress-enabled',
            'switch' => true,
            'role' => 'switch',
            'aria-describedby' => $controlIdPrefix . '-royal-progress-help',
            'label' => __('Royal Progress'),
            'checked' => $progressEnabled,
            'value' => '1',
        ]) ?>
        <?= $this->Form->control('progress_officer_id', [
            'type' => 'select',
            'id' => $controlIdPrefix . '-progress-officer-id',
            'label' => __('Attend as Royal Progress for'),
            'options' => $progressOptions,
            'empty' => false,
            'value' => $selectedProgressOfficer,
            'class' => 'form-select',
            'aria-describedby' => $controlIdPrefix . '-royal-progress-help',
            'tooltip' => __('Marks this event as official progress, shown publicly with your office title.'),
        ]) ?>
        <small id="<?= h($controlIdPrefix) ?>-royal-progress-help" class="form-text text-muted">
            <?= __('When Royal Progress is on, this RSVP is publicly shown as progress for the selected office.') ?>
        </small>
    </fieldset>
<?php endif; ?>
