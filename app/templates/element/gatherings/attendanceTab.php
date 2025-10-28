<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Gathering $gathering
 * @var int $totalAttendanceCount
 */
?>

<div class="gathering-attendance">
    <div class="alert alert-info mb-4">
        <h5><i class="bi bi-info-circle"></i> Attendance Information</h5>
        <p class="mb-2">
            <strong>Total Announced Attendance:</strong> <?= $totalAttendanceCount ?>
            <?= $totalAttendanceCount === 1 ? 'person' : 'people' ?>
        </p>
        <small class="text-muted">
            This list only shows attendees who have chosen to share their attendance with the hosting group or make it public.
            The total count includes all registered attendees, including those keeping their attendance private.
        </small>
    </div>

    <?php if (!empty($gathering->gathering_attendances)): ?>
        <h6 class="mt-4 mb-3">Attendees Sharing with Host</h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Note</th>
                        <th>Visibility</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gathering->gathering_attendances as $attendance): ?>
                        <tr>
                            <td>
                                <?php if ($user->can('view', $attendance->member)): ?>
                                    <?= $this->Html->link(
                                        h($attendance->member->sca_name),
                                        ['controller' => 'Members', 'action' => 'view', $attendance->member->id]
                                    ) ?>
                                <?php else: ?>
                                    <?= h($attendance->member->sca_name) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($attendance->public_note)): ?>
                                    <?= h($attendance->public_note) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php
                                    $visibility = [];
                                    if ($attendance->is_public) {
                                        $visibility[] = '<span class="badge bg-success">Public</span>';
                                    }
                                    if ($attendance->share_with_hosting_group) {
                                        $visibility[] = '<span class="badge bg-info">Host</span>';
                                    }
                                    if ($attendance->share_with_kingdom) {
                                        $visibility[] = '<span class="badge bg-primary">Kingdom</span>';
                                    }
                                    if ($attendance->share_with_crown) {
                                        $visibility[] = '<span class="badge bg-warning text-dark">Crown</span>';
                                    }
                                    echo implode(' ', $visibility);
                                    ?>
                                </small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mt-3">
            <i class="bi bi-info-circle"></i>
            No attendees have shared their attendance with the hosting group yet.
        </div>
    <?php endif; ?>
</div>