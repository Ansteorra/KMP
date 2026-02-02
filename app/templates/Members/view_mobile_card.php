<?php

/**
 * Mobile Card View - Using mobile_app Layout
 * 
 * This template shows the member's mobile activity authorization card.
 * All the PWA infrastructure, menu, and styling is provided by the mobile_app layout.
 * This view only contains the card-specific content.
 * 
 * The member-mobile-card-profile controller is initialized in the layout.
 */

use Cake\I18n\Date;

$now = Date::now();
?>

<div class="card cardbox mx-3 mt-3" data-section="auth-card">
    <div class="card-body">
        <div class="text-center mb-3">
            <span class="fs-5 text-muted"><?= h($message_variables["kingdom"]) ?> Activity Authorization</span>
        </div>
        <div class="text-center py-4" data-member-mobile-card-profile-target="loading">
            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading your card...</p>
        </div>
        <dl class="row mb-0" hidden data-member-mobile-card-profile-target="memberDetails">
            <dt class="col-5 text-end py-2">Legal Name</dt>
            <dd class="col-7 py-2 fw-medium" data-member-mobile-card-profile-target="name"></dd>
            <dt class="col-5 text-end py-2">Society Name</dt>
            <dd class="col-7 py-2 fw-medium" data-member-mobile-card-profile-target="scaName"></dd>
            <dt class="col-5 text-end py-2">Branch</dt>
            <dd class="col-7 py-2" data-member-mobile-card-profile-target="branchName"></dd>
            <dt class="col-5 text-end py-2">Membership</dt>
            <dd class="col-7 py-2" data-member-mobile-card-profile-target="membershipInfo"></dd>
            <dt class="col-5 text-end py-2">Background Check</dt>
            <dd class="col-7 py-2" data-member-mobile-card-profile-target="backgroundCheck"></dd>
            <dt class="col-5 text-end py-2 text-muted small">Last Refresh</dt>
            <dd class="col-7 py-2 text-muted small" data-member-mobile-card-profile-target="lastUpdate"></dd>
        </dl>
    </div>
</div>
<div id="pluginCards" class="mt-3" data-member-mobile-card-profile-target="cardSet"></div>