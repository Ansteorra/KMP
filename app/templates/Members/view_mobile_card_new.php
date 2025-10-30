<?php

/**
 * Mobile Card View - Using mobile_app Layout
 * 
 * This template shows the member's mobile activity authorization card.
 * All the PWA infrastructure, menu, and styling is provided by the mobile_app layout.
 * This view only contains the card-specific content.
 */

use Cake\I18n\Date;

// Setup card URL for member-mobile-card-profile controller
$cardUrl = $this->Url->build(['controller' => 'Members', 'action' => 'viewMobileCardJson', $member->mobile_card_token]);
$now = Date::now();
?>

<div class="card cardbox mx-3" data-controller="member-mobile-card-profile"
    data-member-mobile-card-profile-url-value="<?= $cardUrl ?>" data-member-mobile-card-profile-pwa-ready-value="false">
    <div class="card-body">
        <h3 class="card-title text-center display-6">
            <?= h($message_variables["kingdom"]) ?><br />
            Activity Authorization
        </h3>
        <div class="text-center" data-member-mobile-card-profile-target="loading">
            <div class="spinner-border" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <dl class="row" hidden data-member-mobile-card-profile-target="memberDetails">
            <dt class="col-6 text-end">Legal Name</dt>
            <dd class="col-6" data-member-mobile-card-profile-target="name"></dd>
            <dt class="col-6 text-end">Society Name</dt>
            <dd class="col-6" data-member-mobile-card-profile-target="scaName"></dd>
            <dt class="col-6 text-end">Branch</dt>
            <dd class="col-6" data-member-mobile-card-profile-target="branchName"></dd>
            <dt class="col-6 text-end">Membership</dt>
            <dd class="col-6" data-member-mobile-card-profile-target="membershipInfo"></dd>
            <dt class="col-6 text-end">Background Check</dt>
            <dd class="col-6" data-member-mobile-card-profile-target="backgroundCheck"></dd>
            <dt class="col-6 text-end">Last Refresh</dt>
            <dd class="col-6" data-member-mobile-card-profile-target="lastUpdate"></dd>
        </dl>
    </div>
</div>
<div id="pluginCards" data-member-mobile-card-profile-target="cardSet"></div>