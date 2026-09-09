<?php
declare(strict_types=1);
?>
<template data-mobile-calendar-target="offlineRsvpForm">
    <div class="modal-header">
        <h2 class="modal-title fs-5" id="mobileRsvpModalLabel">RSVP</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <form id="queued-rsvp-form">
        <div class="modal-body">
            <p class="fw-semibold" data-rsvp-event-name></p>
            <p>We'll save your RSVP and send it with these visibility choices when you're connected and signed in.</p>
            <fieldset aria-describedby="queued-rsvp-visibility-help">
                <legend class="fs-6">Share Information With</legend>
                <p id="queued-rsvp-visibility-help" class="small">
                    The RSVP count is visible to everyone. Choose who can see your society name.
                    Leave all choices off to keep your name private.
                </p>
                <div class="form-check form-switch mb-3" data-rsvp-kingdom-choice>
                    <input type="checkbox" class="form-check-input" id="queued-rsvp-kingdom"
                        name="share_with_kingdom" value="1">
                    <label class="form-check-label" for="queued-rsvp-kingdom">Share with Kingdom</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" class="form-check-input" id="queued-rsvp-hosts"
                        name="share_with_hosting_group" value="1">
                    <label class="form-check-label" for="queued-rsvp-hosts">Share with Hosting Group</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input type="checkbox" class="form-check-input" id="queued-rsvp-crown"
                        name="share_with_crown" value="1">
                    <label class="form-check-label" for="queued-rsvp-crown">Share with Nobility/Crown</label>
                </div>
            </fieldset>
            <p role="status" class="small mb-0" data-rsvp-save-status></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save RSVP</button>
        </div>
    </form>
</template>
