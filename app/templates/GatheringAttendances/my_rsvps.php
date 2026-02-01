<?php
/**
 * My RSVPs Mobile View Template
 * 
 * Shows the current user's upcoming RSVPs in a mobile-optimized list.
 * 
 * @var \App\View\AppView $this
 * @var \Cake\ORM\ResultSet $attendances
 * @var string $authCardUrl
 */

// Set mobile layout variables
$this->set('mobileTitle', 'My RSVPs');
$this->set('mobileBackUrl', $authCardUrl);
$this->set('mobileHeaderColor', '#0d6efd'); // Blue for RSVPs

$currentUser = $this->request->getAttribute('identity');
$userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);
?>

<div class="my-rsvps-container mx-3 mt-3" data-controller="my-rsvps">
    <?php if ($attendances->isEmpty()): ?>
        <!-- Empty State -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-check text-muted d-block fs-1 mb-3"></i>
                <h3 class="h5 mb-2">No Upcoming RSVPs</h3>
                <p class="text-muted mb-4">
                    You haven't RSVPed to any upcoming gatherings yet.
                </p>
                <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'mobileCalendar']) ?>" 
                   class="btn btn-primary">
                    <i class="bi bi-calendar me-2"></i>Browse Calendar
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- RSVP List -->
        <div class="rsvp-list">
            <?php foreach ($attendances as $attendance): 
                $gathering = $attendance->gathering;
                $startLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, $userTimezone);
                $endLocal = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, $userTimezone);
                $typeColor = $gathering->gathering_type ? $gathering->gathering_type->color : '#6c757d';
            ?>
                <div class="rsvp-card card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h4 class="rsvp-event-name h6 mb-1">
                                    <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'view', $gathering->public_id]) ?>" 
                                       class="text-decoration-none">
                                        <?= h($gathering->name) ?>
                                    </a>
                                </h4>
                                <?php if ($gathering->gathering_type): ?>
                                    <span class="badge" style="background-color: <?= h($typeColor) ?>; color: white;">
                                        <?= h($gathering->gathering_type->name) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($gathering->cancelled_at): ?>
                                <span class="badge bg-danger">Cancelled</span>
                            <?php else: ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>RSVPed</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="rsvp-event-details text-muted small">
                            <p class="mb-1">
                                <i class="bi bi-calendar me-2"></i>
                                <?= $startLocal->format('D, M j, Y') ?>
                                at <?= $startLocal->format('g:i A') ?>
                            </p>
                            <?php if ($gathering->location): ?>
                                <p class="mb-1">
                                    <i class="bi bi-geo-alt me-2"></i>
                                    <?= h($gathering->location) ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($gathering->branch): ?>
                                <p class="mb-1">
                                    <i class="bi bi-building me-2"></i>
                                    <?= h($gathering->branch->name) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($attendance->is_shared): ?>
                            <div class="rsvp-sharing mt-2 pt-2 border-top">
                                <small class="text-muted">
                                    <i class="bi bi-share me-1"></i>
                                    Shared with: <?= h($attendance->sharing_description) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$gathering->cancelled_at): ?>
                            <div class="rsvp-actions mt-3 d-flex gap-2">
                                <?php if ($gathering->public_page_enabled): ?>
                                    <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'publicLanding', $gathering->public_id, '?' => ['from' => 'mobile']]) ?>" 
                                       class="btn btn-outline-primary btn-sm flex-grow-1">
                                        <i class="bi bi-eye me-1"></i>View Details
                                    </a>
                                <?php endif; ?>
                                <button type="button" 
                                        class="btn btn-outline-secondary btn-sm flex-grow-1"
                                        data-action="click->my-rsvps#editRsvp"
                                        data-gathering-id="<?= h($gathering->id) ?>"
                                        data-attendance-id="<?= h($attendance->id) ?>">
                                    <i class="bi bi-pencil me-1"></i>Edit RSVP
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Quick Link to Calendar -->
    <div class="text-center mt-4 mb-5">
        <a href="<?= $this->Url->build(['controller' => 'Gatherings', 'action' => 'mobileCalendar']) ?>" 
           class="btn btn-outline-secondary">
            <i class="bi bi-calendar me-2"></i>View Calendar
        </a>
    </div>
    
    <!-- RSVP Modal -->
    <div class="modal fade" id="rsvpModal" tabindex="-1" aria-labelledby="rsvpModalLabel" aria-hidden="true" data-my-rsvps-target="modal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rsvpModalLabel">Edit RSVP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" data-my-rsvps-target="modalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.my-rsvps-container {
    padding-bottom: 80px;
}

.rsvp-card {
    border-radius: 12px;
    overflow: hidden;
}

.rsvp-event-name a {
    color: inherit;
}

.rsvp-event-name a:hover {
    color: #0d6efd;
}

.rsvp-event-details p:last-child {
    margin-bottom: 0;
}

.rsvp-actions .btn {
    padding: 8px 12px;
}
</style>
