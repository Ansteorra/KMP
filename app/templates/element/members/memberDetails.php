<?php

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use Cake\I18n\Date;

$today = new Date();
?>
<tr scope="row">
    <th class="col"><?= __("Title") ?></th>
    <td class="col-10"><?= h($member->title) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Sca Name") ?></th>
    <td class="col-10"><?= h($member->sca_name) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Pronunciation") ?></th>
    <td class="col-10"><?= h($member->pronunciation) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Pronouns") ?></th>
    <td class="col-10"><?= h($member->pronouns) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Branch") ?></th>
    <td class="col-10"><?= h($member->branch->name) ?></td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Membership") ?></th>
    <td lass="col-10">
        <?php if ($member->membership_number != null && strlen($member->membership_number) > 0) { ?>
            <?= h($member->membership_number) ?> Exp:
            <?= $member->membership_expires_on ? $this->Timezone->format($member->membership_expires_on, 'Y-m-d', false) : 'N/A' ?>
            <?php if ($member->membership_expires_on && $member->membership_expires_on < $today) : ?>
                <span class="badge text-bg-warning">Expired</span> - <a href="https://sca.app.neoncrm.com/login"> log into SCA
                    portal to renew your membership</a>
            <?php endif; ?>
        <?php } else { ?>
            <?= __('Information Not Available') ?>
        <?php } ?>
        <?php if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0) { ?>
            <small class="mx-1 text-muted">Your Membership Information has been received and is being processed.</small>
        <?php } ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Legal Name") ?></th>
    <td lass="col-10"><?= h($member->first_name) ?>
        <?= h($member->middle_name) ?>
        <?= h($member->last_name) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Address") ?></th>
    <td lass="col-10"><?= h($member->street_address) ?></td>
</tr>
<t scope="row">
    <th class="col"></th>
    <td lass="col-10"><?= h($member->city) ?>, <?= h(
                                                    $member->state,
                                                ) ?> <?= h($member->zip) ?></td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Phone Number") ?></th>
        <td lass="col-10"><?= h($member->phone_number) ?></td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Email Address") ?></th>
        <td lass="col-10"><?= h($member->email_address) ?> </td>
    </tr>
    <?= $member->age < 18
        ? '<tr scope="row">
                <th class="col">' .
        __("Parent Name") .
        '</th>
                <td lass="col-10">' .
        ($member->parent ?
            $this->Html->link($member->parent->sca_name, ["controller" => "members", "action" => "view", $member->parent->id]) :
            "no parent assigned") .
        '</td>
            </tr>'
        : "" ?>
    <tr scope="row">
        <th class="col"><?= __("Birth Date") ?></th>
        <td lass="col-10"><?= h($member->birth_month) ?> / <?= h(
                                                                $member->birth_year,
                                                            ) ?></td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Background Exp.") ?></th>
        <td lass="col-10"><?= $member->background_check_expires_on ? $this->Timezone->format($member->background_check_expires_on, 'Y-m-d', false) : '' ?></td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Last Login") ?></th>
        <td lass="col-10"><?= $member->last_login ? $this->Timezone->format($member->last_login, 'F j, Y g:i A', true) : 'Never' ?></td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Status") ?></th>
        <td lass="col-10">
            <?= $member->status ?>
            <?php if ($member->status == Member::STATUS_ACTIVE || $member->status == Member::STATUS_MINOR_PARENT_VERIFIED): ?>
                <br><small class="text-secondary">To verify your account please reach out to the site Secretary at <a
                        href="mailto:<?= $this->KMP->getAppSetting("Members.AccountVerificationContactEmail") ?>"><?= $this->KMP->getAppSetting("Members.AccountVerificationContactEmail") ?></a>
                    with
                    your account email address and a picture of your membership card</small>
            <?php endif; ?>
        </td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Warrantable") ?></th>
        <td lass="col-10">
            <?= $member->warrantable ? "Yes" : "No" ?>
            <?php if (!$member->warrantable) : ?>
                <br><small class="text-secondary">
                    (<?= implode(' ,', $member->getNonWarrantableReasons()) ?>)
                </small>
            <?php endif; ?>
        </td>
    </tr>

    <?php
    $externalLinks = $this->KMP->getAppSettingsStartWith("Member.ExternalLink.");
    foreach ($externalLinks as $key => $link) {
        $linkLabel = str_replace("Member.ExternalLink.", "", $key);
        $linkUrl = StaticHelpers::processTemplate($link, $member->toArray(), 1, "__missing__");
        if (substr_count($linkUrl, "__missing__") == 0) {
            echo "<tr scope='row'><th class='col'>" . $linkLabel . "</th><td class='col-10'><a href='" . $linkUrl . "' target='_blank'>" . $linkUrl . "</a></td></tr>";
        }
    }
    $privateExternalLinks = $this->KMP->getAppSettingsStartWith("Member.PrivateExternalLink.");
    foreach ($privateExternalLinks as $key => $link) {
        $linkLabel = str_replace("Member.PrivateExternalLink.", "", $key);
        $linkUrl = StaticHelpers::processTemplate($link, $member, 1, "__missing__");
        if (substr_count($linkUrl, "__missing__") == 0) {
            echo "<tr scope='row'><th class='col'>" . $linkLabel . "</th><td class='col-10'><a href='" . $linkUrl . "' target='_blank'>" . $linkUrl . "</a></td></tr>";
        }
    }
    ?>