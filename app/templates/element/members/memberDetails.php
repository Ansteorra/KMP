<?php

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use Cake\I18n\Date;

$today = new Date();
$user = $user ?? $this->request->getAttribute('identity');
$canViewPii = $canViewPii ?? ($user && method_exists($user, 'checkCan') ? $user->checkCan('viewPii', $member) : false);
$canManageMember = $canManageMember ?? ($user && method_exists($user, 'canManageMember') ? $user->canManageMember($member) : false);
$canEditProfilePhoto = $user && method_exists($user, 'checkCan') && (
    $user->checkCan('edit', $member) || $user->checkCan('partialEdit', $member)
);
$piiHiddenText = __('Hidden (requires View PII permission)');
$profilePhotoUrl = !empty($member->profile_photo_document_id)
    ? $this->Url->build(['controller' => 'Members', 'action' => 'profilePhoto', $member->id])
    : null;
$hasProfilePhoto = $profilePhotoUrl !== null;
$photoRowspan = 5;
$detailsColspanAttr = $hasProfilePhoto ? ' colspan="2"' : '';
?>
<tr scope="row">
    <th class="col"><?= __("Title") ?></th>
    <td class="col-10"><?= h($member->title) ?></td>
    <?php if ($hasProfilePhoto): ?>
    <td class="member-profile-photo-cell text-center align-top" rowspan="<?= $photoRowspan ?>">
        <div class="member-profile-photo-wrapper">
            <div class="member-profile-photo-frame">
                <img src="<?= h($profilePhotoUrl) ?>" alt="<?= __('Profile photo for {0}', $member->sca_name) ?>"
                    class="img-thumbnail member-profile-photo-image" />
                <?php if ($canEditProfilePhoto): ?>
                <div class="member-profile-photo-actions">
                    <?= $this->Form->postLink(
                                __('remove'),
                                ['controller' => 'Members', 'action' => 'removeProfilePhoto', $member->id],
                                [
                                    'class' => 'btn btn-sm member-profile-photo-remove-btn btn-secondary',
                                    'confirm' => __('Remove this profile photo?'),
                                    'title' => __('Remove profile photo'),
                                ],
                            ) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </td>
    <?php endif; ?>
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
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?php if ($member->membership_number != null && strlen($member->membership_number) > 0): ?>
        <?= h($member->membership_number) ?>
        <?= __('Exp:') ?>
        <?= $member->membership_expires_on ? $this->Timezone->format($member->membership_expires_on, 'Y-m-d', false) : 'N/A' ?>
        <?php if ($member->membership_expires_on && $member->membership_expires_on < $today): ?>
        <span class="badge text-bg-warning">Expired</span> <?php if ($canManageMember): ?>- <a
            href="https://sca.app.neoncrm.com/login"> log into SCA
            portal to renew your membership</a><?php endif; ?>
        <?php endif; ?>
        <?php else: ?>
        <?= __('Information Not Available') ?>
        <?php endif; ?>
        <?php if ($member->membership_card_path != null && strlen($member->membership_card_path) > 0 && $canManageMember): ?>
        <small class="mx-1 text-muted">Your Membership Information has been received and is being processed.</small>
        <?php endif; ?>
    </td>
</tr>
<?php if ($canViewPii): ?>
<tr scope="row">
    <th class="col"><?= __("Legal Name") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= h($member->first_name) ?>
        <?= h($member->middle_name) ?>
        <?= h($member->last_name) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Address") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= h($member->street_address) ?><br />
        <?= h($member->city) ?><?php if ($member->city && ($member->state || $member->zip)) : ?>,<?php endif; ?>
        <?= h($member->state) ?> <?= h($member->zip) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Phone Number") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= h($member->phone_number) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Email Address") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= h($member->email_address) ?>
    </td>
</tr>
<?php if ($member->age < 18): ?>
<tr scope="row">
    <th class="col"><?= __("Parent Name") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?php if ($member->parent): ?>
        <?= $this->Html->link($member->parent->sca_name, ["controller" => "members", "action" => "view", $member->parent->id]) ?>
        <?php else: ?>
        <?= __('No parent assigned') ?>
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
<tr scope="row">
    <th class="col"><?= __("Birth Date") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= h($member->birth_month) ?> / <?= h($member->birth_year) ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Background Exp.") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= $member->background_check_expires_on ? $this->Timezone->format($member->background_check_expires_on, 'Y-m-d', false) : '' ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Last Login") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= $member->last_login ? $this->Timezone->format($member->last_login, 'F j, Y g:i A', true) : 'Never' ?>
    </td>
</tr>
<?php endif; ?>
<tr scope="row">
    <th class="col"><?= __("Status") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= $member->status ?>
        <?php if (($member->status == Member::STATUS_ACTIVE || $member->status == Member::STATUS_MINOR_PARENT_VERIFIED) && $canManageMember): ?>
        <br><small class="text-secondary">To verify your account please reach out to the site Secretary at <a
                href="mailto:<?= $this->KMP->getAppSetting("Members.AccountVerificationContactEmail") ?>"><?= $this->KMP->getAppSetting("Members.AccountVerificationContactEmail") ?></a>
            with
            your account email address and a picture of your membership card</small>
        <?php endif; ?>
    </td>
</tr>
<tr scope="row">
    <th class="col"><?= __("Warrantable") ?></th>
    <td class="col-10" <?= $detailsColspanAttr ?>>
        <?= $member->warrantable ? "Yes" : "No" ?>
        <?php if (!$member->warrantable && $canViewPii) : ?>
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
    if (substr_count($linkUrl, "__missing__") === 0 && filter_var($linkUrl, FILTER_VALIDATE_URL)) {
        echo "<tr scope='row'><th class='col'>" . h($linkLabel) . "</th><td class='col-10'" . $detailsColspanAttr . "><a href='" . h($linkUrl) . "' target='_blank' rel='noopener noreferrer'>" . h($linkUrl) . "</a></td></tr>";
    }
}
$privateExternalLinks = $this->KMP->getAppSettingsStartWith("Member.PrivateExternalLink.");
foreach ($privateExternalLinks as $key => $link) {
    $linkLabel = str_replace("Member.PrivateExternalLink.", "", $key);
    $linkUrl = StaticHelpers::processTemplate($link, $member, 1, "__missing__");
    if (substr_count($linkUrl, "__missing__") === 0 && filter_var($linkUrl, FILTER_VALIDATE_URL)) {
        echo "<tr scope='row'><th class='col'>" . h($linkLabel) . "</th><td class='col-10'" . $detailsColspanAttr . "><a href='" . h($linkUrl) . "' target='_blank' rel='noopener noreferrer'>" . h($linkUrl) . "</a></td></tr>";
    }
}
?>
