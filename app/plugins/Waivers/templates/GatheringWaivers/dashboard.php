<?php
declare(strict_types=1);

/**
 * @var \App\View\AppView $this
 * @var array $statistics
 * @var array $gatheringsMissingWaivers
 * @var array $gatheringsNeedingWaivers
 * @var array $gatheringsReadyToClose
 * @var array $gatheringsNeedingClosed
 * @var array $closedGatherings
 * @var array $branchesWithIssues
 * @var array $recentActivity
 * @var array $waiverTypesSummary
 * @var array|null $searchResults
 * @var string|null $searchTerm
 * @var array $gatheringsDueWaivers
 */

$gatheringsReadyToClose = $gatheringsReadyToClose ?? [];
$gatheringsNeedingClosed = $gatheringsNeedingClosed ?? [];
$closedGatherings = $closedGatherings ?? [];
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Waiver Secretary Dashboard';
$this->KMP->endBlock();
?>

<style>
    /* Collapsible section caret rotation */
    .waiver-dashboard .card-header[data-bs-toggle="collapse"] {
        cursor: pointer;
    }

    .waiver-dashboard .card-header[data-bs-toggle="collapse"] .bi-chevron-down {
        transition: transform 0.2s ease;
    }

    .waiver-dashboard .card-header[data-bs-toggle="collapse"].collapsed .bi-chevron-down {
        transform: rotate(-90deg);
    }

    /* Waiver Calendar Styles */
    .waiver-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background: #dee2e6;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        overflow: hidden;
    }

    .waiver-calendar-header {
        background: #f8f9fa;
        padding: 0.5rem;
        text-align: center;
        font-weight: 600;
        font-size: 0.85rem;
        color: #495057;
    }

    .waiver-calendar-day {
        background: #fff;
        min-height: 120px;
        padding: 0.25rem;
        position: relative;
    }

    .waiver-calendar-day.other-month {
        background: #f8f9fa;
        color: #5c636a;
    }

    .waiver-calendar-day.today {
        background: #fffde7;
    }

    .waiver-calendar-day-number {
        font-size: 0.8rem;
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 0.15rem;
    }

    .waiver-calendar-day.other-month .waiver-calendar-day-number {
        color: #5c636a;
    }

    .waiver-calendar-item {
        display: block;
        font-size: 0.75rem;
        padding: 0.25rem;
        margin-bottom: 3px;
        border-radius: 0.25rem;
        border-left: 3px solid #6c757d;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        transition: transform 0.1s ease, box-shadow 0.1s ease;
    }

    .waiver-calendar-meta {
        color: #495057;
    }

    .waiver-calendar-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        color: inherit;
    }

    .waiver-calendar-item .fw-bold {
        word-wrap: break-word;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        font-size: 0.7rem;
        line-height: 1.2;
    }

    .waiver-calendar-item.multi-day {
        border-left-width: 3px;
    }

    .waiver-calendar-badges {
        display: flex;
        gap: 0.2rem;
        flex-wrap: wrap;
        margin-top: 0.2rem;
    }

    .waiver-calendar-badges .badge {
        font-size: 0.6rem;
        padding: 0.15rem 0.3rem;
        font-weight: 500;
    }

    .waiver-calendar-item.status-none {
        background-color: #dc3545;
        color: #fff;
    }

    .waiver-calendar-item.status-partial {
        background-color: #ffc107;
        color: #212529;
    }

    .waiver-calendar-item.status-partial:hover {
        color: #212529;
    }

    .waiver-calendar-item.status-complete {
        background-color: #198754;
        color: #fff;
    }

    .waiver-calendar-item.status-closed {
        background-color: #0d6efd;
        color: #fff;
    }

    .waiver-calendar-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .waiver-calendar-nav .btn {
        min-width: 100px;
    }

    .waiver-calendar-legend {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 0.75rem;
        font-size: 0.8rem;
    }

    .waiver-calendar-legend-item {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .waiver-calendar-legend-swatch {
        width: 14px;
        height: 14px;
        border-radius: 0.2rem;
        display: inline-block;
    }
</style>

<div class="waiver-dashboard content">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>
                <i class="bi bi-file-earmark-text"></i> <?= __('Waiver Secretary Dashboard') ?>
            </h2>
            <p class="text-muted">
                <?= __('Comprehensive overview of waiver compliance and management across all gatherings.') ?>
            </p>
        </div>
    </div>

    <!-- Key Statistics (always visible, not collapsible) -->
    <div class="row mb-4">
        <div class="col-md-12">
            <h4><i class="bi bi-bar-chart-fill"></i> <?= __('Key Statistics') ?></h4>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h2 class="display-4"><?= number_format($statistics['recentWaivers']) ?></h2>
                    <p class="card-text"><?= __('Uploads in the last 30 Days') ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6 mb-3">
            <div class="card text-dark bg-info">
                <div class="card-body text-center">
                    <h2 class="display-4"><?= number_format(count($gatheringsReadyToClose)) ?></h2>
                    <p class="card-text"><?= __('Ready to Close') ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-warning text-dark">
                <div class="card-body text-center">
                    <h2 class="display-4"><?= number_format($statistics['gatheringsNeedingCount']) ?></h2>
                    <p class="card-text"><?= __('Upcoming Events') ?></p>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-3 col-sm-6 mb-3">
            <div class="card text-white bg-danger">
                <div class="card-body text-center">
                    <h2 class="display-4"><?= number_format($statistics['gatheringsMissingCount']) ?></h2>
                    <p class="card-text"><?= __('Past Due') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Waiver Clerk Calendar -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-primary">
                <h5 class="mb-0">
                    <button type="button" class="card-header bg-primary text-white collapsed w-100 text-start"
                        data-bs-toggle="collapse" data-bs-target="#collapse-calendar" aria-expanded="false" aria-controls="collapse-calendar">
                        <span class="mb-0 d-flex justify-content-between align-items-center w-100">
                        <span>
                            <i class="bi bi-calendar3"></i>
                            <?= __('Waiver Clerk Calendar') ?>
                        </span>
                        <i class="bi bi-chevron-down"></i>
                    </span>
                    </button>
                </h5>
                <div id="collapse-calendar" class="collapse">
                    <div class="card-body"
                        data-controller="waiver-calendar"
                        data-waiver-calendar-url-value="<?= $this->Url->build(['action' => 'calendarData', 'plugin' => 'Waivers']) ?>">
                        <div class="waiver-calendar-nav">
                            <button class="btn btn-outline-primary btn-sm" data-action="waiver-calendar#prevMonth" data-waiver-calendar-target="prevBtn">
                                <i class="bi bi-chevron-left"></i> <?= __('Prev') ?>
                            </button>
                            <h5 class="mb-0" data-waiver-calendar-target="monthLabel"></h5>
                            <button class="btn btn-outline-primary btn-sm" data-action="waiver-calendar#nextMonth" data-waiver-calendar-target="nextBtn">
                                <?= __('Next') ?> <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                        <div data-waiver-calendar-target="calendar">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden"><?= __('Loading...') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="waiver-calendar-legend">
                            <div class="waiver-calendar-legend-item">
                                <span class="badge bg-success"><?= __('Uploaded') ?></span>
                                <?= __('Waivers submitted') ?>
                            </div>
                            <div class="waiver-calendar-legend-item">
                                <span class="badge bg-info text-dark"><?= __('Exempted') ?></span>
                                <?= __('Waiver not required') ?>
                            </div>
                            <div class="waiver-calendar-legend-item">
                                <span class="badge bg-warning text-dark"><?= __('Pending') ?></span>
                                <?= __('Still needed') ?>
                            </div>
                            <div class="waiver-calendar-legend-item">
                                <span class="badge bg-primary"><?= __('Closed') ?></span>
                                <?= __('Reviewed & closed') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gatherings Ready to Close (marked by stewards) -->
    <?php if (!empty($gatheringsReadyToClose)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-info">
                    <h5 class="mb-0">
                    <button type="button" class="card-header bg-info text-dark collapsed w-100 text-start"
                        data-bs-toggle="collapse" data-bs-target="#collapse-ready-close" aria-expanded="false" aria-controls="collapse-ready-close">
                        <span class="mb-0 d-flex justify-content-between align-items-center w-100">
                            <span>
                                <i class="bi bi-check2-square"></i>
                                <?= __('Ready for Review & Close') ?>
                                <span class="badge bg-light text-dark"><?= count($gatheringsReadyToClose) ?></span>
                            </span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                </h5>
                    <div id="collapse-ready-close" class="collapse">
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="bi bi-info-circle"></i>
                                <?= __('These gatherings have been marked as ready to close by event staff. Review the waivers and close each gathering when satisfied.') ?>
                            </p>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?= __('Gathering') ?></th>
                                            <th><?= __('Branch') ?></th>
                                            <th><?= __('Event Dates') ?></th>
                                            <th><?= __('Marked Ready') ?></th>
                                            <th><?= __('Waiver Status') ?></th>
                                            <th class="actions"><?= __('Actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gatheringsReadyToClose as $gathering): ?>
                                            <tr>
                                                <td>
                                                    <?= $this->Html->link(
                                                        '<strong>' . h($gathering->name) . '</strong>',
                                                        ['plugin' => null, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id],
                                                        ['escape' => false]
                                                    ) ?>
                                                </td>
                                                <td><?= h($gathering->branch->name) ?></td>
                                                <td>
                                                    <?php
                                                    $startFormatted = $this->Timezone->format($gathering->start_date, $gathering, 'M d, Y');
                                                    $endFormatted = $gathering->end_date ? $this->Timezone->format($gathering->end_date, $gathering, 'M d, Y') : $startFormatted;
                                                    ?>
                                                    <?= h($startFormatted) ?>
                                                    <?php if ($startFormatted !== $endFormatted): ?>
                                                        <br><small class="text-muted">to <?= h($endFormatted) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $this->Timezone->format($gathering->ready_to_close_at, $gathering, 'M d, Y g:i A') ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= __('by {0}', h($gathering->ready_to_close_by_member?->sca_name ?? __('Unknown'))) ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php if ($gathering->is_waiver_complete): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-circle-fill"></i> <?= __('Complete') ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <?= $gathering->missing_waiver_count ?> <?= __('Missing') ?>
                                                        </span>
                                                        <?php if (!empty($gathering->missing_waiver_names)): ?>
                                                            <ul class="mb-0 mt-1 small">
                                                                <?php foreach ($gathering->missing_waiver_names as $waiverName): ?>
                                                                    <li><?= h($waiverName) ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions">
                                                    <?= $this->Html->link(
                                                        '<i class="bi bi-binoculars-fill"></i> ' . __('Review'),
                                                        ['action' => 'index', '?' => ['gathering_id' => $gathering->id]],
                                                        ['class' => 'btn btn-sm btn-info', 'escape' => false]
                                                    ) ?>
                                                    <?= $this->Form->postLink(
                                                        '<i class="bi bi-lock-fill"></i> ' . __('Close'),
                                                        ['action' => 'close', $gathering->id],
                                                        [
                                                            'class' => 'btn btn-sm btn-success',
                                                            'escapeTitle' => false,
                                                            'confirm' => __('Close waiver collection for "{0}"? This will prevent further uploads.', $gathering->name),
                                                        ]
                                                    ) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- In Progress Waivers -->
    <?php if (!empty($gatheringsNeedingClosed)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-warning">
                    <h5 class="mb-0">
                    <button type="button" class="card-header bg-warning text-dark collapsed w-100 text-start"
                        data-bs-toggle="collapse" data-bs-target="#collapse-needing-closed" aria-expanded="false" aria-controls="collapse-needing-closed">
                        <span class="mb-0 d-flex justify-content-between align-items-center w-100">
                            <span>
                                <i class="bi bi-clipboard-check"></i>
                                <?= __('In Progress Waivers') ?>
                                <span class="badge bg-dark"><?= count($gatheringsNeedingClosed) ?></span>
                            </span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                </h5>
                    <div id="collapse-needing-closed" class="collapse">
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="bi bi-info-circle"></i>
                                <?= __('These gatherings have waiver uploads/exemptions in progress and are not yet marked ready for review and close.') ?>
                            </p>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?= __('Gathering') ?></th>
                                            <th><?= __('Branch') ?></th>
                                            <th><?= __('Event Dates') ?></th>
                                            <th><?= __('Needed Waivers') ?></th>
                                            <th><?= __('Uploaded Waivers') ?></th>
                                            <th class="actions"><?= __('Actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gatheringsNeedingClosed as $gathering): ?>
                                            <tr>
                                                <td>
                                                    <?= $this->Html->link(
                                                        '<strong>' . h($gathering->name) . '</strong>',
                                                        ['plugin' => null, 'controller' => 'Gatherings', 'action' => 'view', $gathering->public_id],
                                                        ['escape' => false]
                                                    ) ?>
                                                    <?php if ($gathering->is_waiver_complete ?? false): ?>
                                                        <span class="badge bg-success ms-2">
                                                            <i class="bi bi-check-circle-fill"></i> <?= __('Complete') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= h($gathering->branch->name) ?></td>
                                                <td>
                                                    <?php
                                                    $startFormatted = $this->Timezone->format($gathering->start_date, $gathering, 'M d, Y');
                                                    $endFormatted = $gathering->end_date ? $this->Timezone->format($gathering->end_date, $gathering, 'M d, Y') : $startFormatted;
                                                    ?>
                                                    <?= h($startFormatted) ?>
                                                    <?php if ($startFormatted !== $endFormatted): ?>
                                                        <br><small class="text-muted">to <?= h($endFormatted) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (($gathering->missing_waiver_count ?? 0) > 0): ?>
                                                        <span class="badge bg-danger"><?= $gathering->missing_waiver_count ?></span>
                                                        <?php if (!empty($gathering->missing_waiver_names ?? [])): ?>
                                                            <ul class="mb-0 mt-1">
                                                                <?php foreach ($gathering->missing_waiver_names as $waiverName): ?>
                                                                    <li><?= h($waiverName) ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-success"><?= __('None Needed') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark"><?= $gathering->uploaded_waiver_count ?? 0 ?></span>
                                                    <?php if (!empty($gathering->uploaded_waiver_names ?? [])): ?>
                                                        <ul class="mb-0 mt-1">
                                                            <?php foreach ($gathering->uploaded_waiver_names as $waiverName): ?>
                                                                <li><?= h($waiverName) ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <div class="small text-muted mt-1"><?= __('None') ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions">
                                                    <?= $this->Html->link(
                                                        '<i class="bi bi-binoculars-fill"></i> ' . __('Review'),
                                                        ['action' => 'index', '?' => ['gathering_id' => $gathering->id]],
                                                        ['class' => 'btn btn-sm btn-info', 'escape' => false]
                                                    ) ?>
                                                    <?= $this->Form->postLink(
                                                        '<i class="bi bi-lock-fill"></i> ' . __('Close'),
                                                        ['action' => 'close', $gathering->id],
                                                        [
                                                            'class' => 'btn btn-sm btn-success',
                                                            'escapeTitle' => false,
                                                            'confirm' => __('Close waiver collection for "{0}"? This will prevent further uploads.', $gathering->name),
                                                        ]
                                                    ) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $waiverSections = [
        ['id' => 'needing-waivers', 'title' => __('Past Due Waivers'), 'gatherings' => $gatheringsMissingWaivers,
            'description' => __('Missing required waiver types for gatherings that ended more than 30 days ago.'),
            'future' => false, 'headerClass' => 'bg-danger text-white'],
        ['id' => 'due-waivers', 'title' => __('Waivers Due'), 'gatherings' => $gatheringsDueWaivers,
            'description' => __('Missing required waiver types are due when a gathering ends and remain due through day 30. On day 31 they become past due.'),
            'future' => false, 'headerClass' => 'bg-warning text-dark'],
        ['id' => 'upcoming', 'title' => __('Future Waivers'), 'gatherings' => $gatheringsNeedingWaivers,
            'description' => __('Missing required waiver types for gatherings ending within the next 30 days.'),
            'future' => true, 'headerClass' => ''],
    ];
    ?>
    <?php foreach ($waiverSections as $section): ?>
        <?php if (empty($section['gatherings'])) { continue; } ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <h5 class="mb-0">
                        <button type="button" class="card-header <?= h($section['headerClass']) ?> collapsed w-100 text-start"
                            data-bs-toggle="collapse" data-bs-target="#collapse-<?= h($section['id']) ?>"
                            aria-expanded="false" aria-controls="collapse-<?= h($section['id']) ?>">
                            <span class="d-flex justify-content-between align-items-center w-100">
                                <span>
                                    <?= h($section['title']) ?>
                                    <span class="badge bg-light text-dark"><?= count($section['gatherings']) ?></span>
                                </span>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </span>
                        </button>
                    </h5>
                    <div id="collapse-<?= h($section['id']) ?>" class="collapse">
                        <div class="card-body">
                            <p><?= h($section['description']) ?></p>
                            <?= $this->element('Waivers.dashboard_waiver_gatherings', [
                                'gatherings' => $section['gatherings'],
                                'showDaysUntilStart' => $section['future'],
                            ]) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Branches with Compliance Issues -->
    <?php if (!empty($branchesWithIssues)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <h5 class="mb-0">
                    <button type="button" class="card-header bg-warning collapsed w-100 text-start"
                        data-bs-toggle="collapse" data-bs-target="#collapse-branches" aria-expanded="false" aria-controls="collapse-branches">
                        <span class="mb-0 d-flex justify-content-between align-items-center w-100">
                            <span>
                                <i class="bi bi-building"></i> <?= __('Branches with Compliance Issues') ?>
                            </span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                </h5>
                    <div id="collapse-branches" class="collapse">
                        <div class="card-body">
                            <p id="branch-compliance-help"><?= __('Gatherings counts events that ended more than 30 days ago with missing required waiver types. Past Due Waivers counts those missing types once per gathering; accepted uploads or exemptions satisfy a type, while declined or deleted records do not. Select a branch for details.') ?></p>
                            <div class="table-responsive">
                                <table class="table table-hover" aria-describedby="branch-compliance-help">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?= __('Branch') ?></th>
                                            <th scope="col" class="text-center"><?= __('Gatherings with Past Due Waivers') ?></th>
                                            <th scope="col" class="text-center"><?= __('Past Due Waivers') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($branchesWithIssues as $branchIssue): ?>
                                            <tr>
                                                <td>
                                                    <button type="button" class="btn btn-link text-start"
                                                        data-bs-toggle="modal" data-bs-target="#branch-past-due-<?= (int)$branchIssue['branch']->id ?>"
                                                        aria-haspopup="dialog">
                                                        <?= h($branchIssue['branch']->name) ?>
                                                        <span class="visually-hidden"><?= __(': view past due waivers') ?></span>
                                                    </button>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-danger"><?= $branchIssue['gathering_count'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span
                                                        class="badge bg-warning text-dark"><?= $branchIssue['total_missing_waivers'] ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php foreach ($branchesWithIssues as $branchIssue): ?>
            <div class="modal fade" id="branch-past-due-<?= (int)$branchIssue['branch']->id ?>" tabindex="-1"
                aria-labelledby="branch-past-due-title-<?= (int)$branchIssue['branch']->id ?>" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="branch-past-due-title-<?= (int)$branchIssue['branch']->id ?>">
                                <?= h(__('Past Due Waivers: {0}', $branchIssue['branch']->name)) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
                        </div>
                        <div class="modal-body">
                            <?= $this->element('Waivers.dashboard_waiver_gatherings', [
                                'gatherings' => $branchIssue['gatherings'],
                                'showDaysUntilStart' => false,
                            ]) ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Close') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Recent Waiver Activity and Waiver Types Summary -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <h5 class="mb-0">
                    <button type="button" class="card-header bg-info text-dark collapsed w-100 text-start"
                        data-bs-toggle="collapse" data-bs-target="#collapse-recent" aria-expanded="false" aria-controls="collapse-recent">
                        <span class="mb-0 d-flex justify-content-between align-items-center w-100">
                        <span>
                            <i class="bi bi-clock-history"></i> <?= __('Recent Activity & Waiver Types') ?>
                        </span>
                        <i class="bi bi-chevron-down"></i>
                    </span>
                    </button>
                </h5>
                <div id="collapse-recent" class="collapse">
                    <div class="card-body">
                        <div class="row">
                            <!-- Recent Activity -->
                            <div class="col-md-8">
                                <h6><i class="bi bi-clock-history"></i> <?= __('Recent Waiver Activity (Last 30 Days)') ?></h6>
                                <?php if (empty($recentActivity)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> <?= __('No recent waiver activity.') ?>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('Date') ?></th>
                                                    <th><?= __('Gathering') ?></th>
                                                    <th><?= __('Branch') ?></th>
                                                    <th><?= __('Type') ?></th>
                                                    <th><?= __('Status') ?></th>
                                                    <th><?= __('Uploaded By') ?></th>
                                                    <th class="text-center"><?= __('Actions') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentActivity as $activity): ?>
                                                    <tr>
                                                        <td class="text-nowrap">
                                                            <?= $this->Timezone->format($activity->created, null, 'M d, Y') ?></td>
                                                        <td><?= h($activity->gathering->name) ?></td>
                                                        <td><?= h($activity->gathering->branch->name) ?></td>
                                                        <td><?= h($activity->waiver_type->name) ?></td>
                                                        <td>
                                                            <?php if ($activity->is_declined): ?>
                                                                <span class="badge bg-danger"><?= __('Declined') ?></span>
                                                            <?php elseif ($activity->status === 'active'): ?>
                                                                <span class="badge bg-success"><?= __('Active') ?></span>
                                                            <?php elseif ($activity->status === 'expired'): ?>
                                                                <span class="badge bg-danger"><?= __('Expired') ?></span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary"><?= h($activity->status) ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= h($activity->created_by_member->sca_name ?? 'Unknown') ?></td>
                                                        <td class="text-center">
                                                            <?= $this->Html->link(
                                                                '',
                                                                ['action' => 'view', $activity->id],
                                                                ['class' => 'btn btn-sm btn-secondary bi bi-binoculars-fill', 'title' => __('View Waiver')]
                                                            ) ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Waiver Types Summary -->
                            <div class="col-md-4">
                                <h6><i class="bi bi-file-earmark-ruled"></i> <?= __('Waiver Types Summary') ?></h6>
                                <?php if (empty($waiverTypesSummary)): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> <?= __('No waiver types configured.') ?>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($waiverTypesSummary as $summary): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <?= h($summary['waiver_type']->name) ?>
                                                <span class="badge bg-primary rounded-pill"><?= number_format($summary['count']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Closed Gatherings -->
    <?php if (!empty($closedGatherings)): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-secondary">
                    <h5 class="mb-0">
                    <button type="button" class="card-header bg-secondary text-white collapsed w-100 text-start"
                        data-bs-toggle="collapse" data-bs-target="#collapse-closed" aria-expanded="false" aria-controls="collapse-closed">
                        <span class="mb-0 d-flex justify-content-between align-items-center w-100">
                            <span>
                                <i class="bi bi-lock-fill"></i>
                                <?= __('Closed Gatherings') ?>
                                <span class="badge bg-light text-dark"><?= count($closedGatherings) ?></span>
                            </span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                </h5>
                    <div id="collapse-closed" class="collapse">
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="bi bi-info-circle"></i>
                                <?= __('Gatherings where waiver collection has been reviewed and closed (last 90 days).') ?>
                            </p>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th><?= __('Gathering') ?></th>
                                            <th><?= __('Branch') ?></th>
                                            <th><?= __('Event Dates') ?></th>
                                            <th><?= __('Closed Date') ?></th>
                                            <th><?= __('Closed By') ?></th>
                                            <th class="actions"><?= __('Actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($closedGatherings as $closure): ?>
                                            <tr>
                                                <td>
                                                    <?= $this->Html->link(
                                                        '<strong>' . h($closure->gathering->name) . '</strong>',
                                                        ['plugin' => null, 'controller' => 'Gatherings', 'action' => 'view', $closure->gathering->public_id],
                                                        ['escape' => false]
                                                    ) ?>
                                                </td>
                                                <td><?= h($closure->gathering->branch->name ?? __('Unknown')) ?></td>
                                                <td>
                                                    <?php
                                                    $startFormatted = $this->Timezone->format($closure->gathering->start_date, $closure->gathering, 'M d, Y');
                                                    $endFormatted = $closure->gathering->end_date ? $this->Timezone->format($closure->gathering->end_date, $closure->gathering, 'M d, Y') : $startFormatted;
                                                    ?>
                                                    <?= h($startFormatted) ?>
                                                    <?php if ($startFormatted !== $endFormatted): ?>
                                                        <br><small class="text-muted">to <?= h($endFormatted) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= $this->Timezone->format($closure->closed_at, $closure->gathering, 'M d, Y') ?>
                                                </td>
                                                <td>
                                                    <?= h($closure->closed_by_member?->sca_name ?? __('Unknown')) ?>
                                                </td>
                                                <td class="actions">
                                                    <?= $this->Html->link(
                                                        '<i class="bi bi-binoculars-fill"></i> ' . __('Review'),
                                                        ['action' => 'index', '?' => ['gathering_id' => $closure->gathering->id]],
                                                        ['class' => 'btn btn-sm btn-secondary', 'escape' => false]
                                                    ) ?>
                                                    <?= $this->Form->postLink(
                                                        '<i class="bi bi-unlock-fill"></i> ' . __('Reopen'),
                                                        ['action' => 'reopen', $closure->gathering->id],
                                                        [
                                                            'class' => 'btn btn-sm btn-outline-warning',
                                                            'escapeTitle' => false,
                                                            'confirm' => __('Reopen waiver collection for "{0}"? This will allow further uploads.', $closure->gathering->name),
                                                        ]
                                                    ) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
