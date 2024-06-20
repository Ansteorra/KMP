<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\member $member
 */
?>
<?php

use App\Model\Entity\Member;

$this->extend("/layout/TwitterBootstrap/view_record");

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use App\KMP\StaticHelpers;

$needVerification = false;
$needsParentVerification = false;
$needsMemberCardVerification = false;

$aiFormConfig = $this->KMP->getAppSettingsStartWith("Member.AdditionalInfo.");
$aiForm = [];
if (!empty($aiFormConfig)) {
    foreach ($aiFormConfig as $key => $value) {
        $shortKey = str_replace("Member.AdditionalInfo.", "", $key);
        $aiForm[$shortKey] = $value;
    }
}
switch ($member->status) {
    case Member::STATUS_ACTIVE:
        $needVerification = true;
        $needsMemberCardVerification = true;
        $needsParentVerification = false;
        break;
    case Member::STATUS_UNVERIFIED_MINOR:
        $needVerification = true;
        $needsParentVerification = true;
        $needsMemberCardVerification = true;
        break;
    case Member::STATUS_MINOR_MEMBERSHIP_VERIFIED:
        $needVerification = true;
        $needsMemberCardVerification = false;
        $needsParentVerification = true;
        break;
    case Member::STATUS_MINOR_PARENT_VERIFIED:
        $needVerification = true;
        $needsMemberCardVerification = true;
        $needsParentVerification = false;
        break;
    default:
        $needVerification = false;
        $needsMemberCardVerification = false;
        $needsParentVerification = false;
        break;
}

echo $this->KMP->startBlock("pageTitle") ?>
<?= h($member->sca_name) ?>
<?php $this->KMP->endBlock() ?>
<?= $this->KMP->startBlock("recordActions") ?>
<?php if ($user->can("verifyMembership", "Members") && $needVerification) { ?>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#verifyMembershipModal">Verify Membership</button>
<?php } ?>
<?php if (
    $user->can("edit", $member) ||
    $user->can("partialEdit", $member)
) { ?>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal" id='editModalBtn'>Edit</button>
    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#passwordModal" id='passwordModalBtn'>Change Password</button>
<?php } ?>
<?php $this->KMP->endBlock() ?>

<?php $this->KMP->startBlock("recordDetails") ?>
<tr scope="row">
    <th class="col"><?= __("Sca Name") ?></th>
    <td class="col-10"><?= h($member->sca_name) ?></td>
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
            <?= h($member->membership_expires_on) ?>
        <?php } else { ?>
            <?= __('Information Not Available') ?>
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
        <td lass="col-10"><?= h(
                                $member->background_check_expires_on,
                            ) ?></td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Last Login") ?></th>
        <td lass="col-10"><?= $member->last_login ?></td>
    </tr>
    <tr scope="row">
        <th class="col"><?= __("Status") ?></th>
        <td lass="col-10"><?= $member->status ?></td>
    </tr>

    <?php
    $externalLinks = $this->KMP->getAppSettingsStartWith("Member.ExternalLink.");
    foreach ($externalLinks as $key => $link) {
        $linkLabel = str_replace("Member.ExternalLink.", "", $key);
        $linkUrl = StaticHelpers::processTemplate($link, $member, 1, "__missing__");
        if (substr_count($linkUrl, "__missing__") == 0) {
            echo "<tr scope='row'><th class='col'>" . $linkLabel . "</th><td class='col-10'><a href='" . $linkUrl . "' target='_blank'>" . $linkUrl . "</a></td></tr>";
        }
    }
    ?>
    <?php $this->KMP->endBlock() ?>
    <?php $this->KMP->startBlock("tabButtons") ?>
    <button class="nav-link" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles" type="button" role="tab" aria-controls="nav-roles" aria-selected="false"><?= __("Roles") ?>
    </button>
    <button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab" aria-controls="nav-notes" aria-selected="false"><?= __("Notes") ?>
    </button>
    <?php if (!empty($aiForm)) : ?>
        <button class="nav-link" id="nav-add-info-tab" data-bs-toggle="tab" data-bs-target="#nav-add-info" type="button" role="tab" aria-controls="nav-add-info" aria-selected="false"><?= __("Additional Info.") ?>
        </button>
    <?php endif; ?>
    <?php $this->KMP->endBlock() ?>
    <?php $this->KMP->startBlock("tabContent") ?>
    <div class="related tab-pane fade m-3" id="nav-roles" role="tabpanel" aria-labelledby="nav-roles-tab">
        <?php if (!empty($member->previous_member_roles) || !empty($member->current_member_roles) || !empty($member->upcoming_member_roles)) {
            $linkTemplate = [
                "type" => "link",
                "verify" => true,
                "authData" => "role",
                "label" => "View",
                "controller" => "Roles",
                "action" => "view",
                "id" => "role_id",
                "options" => ["class" => "btn btn-secondary"],
            ];
            $removeLinkTemplate = [
                "type" => "postLink",
                "verify" => true,
                "label" => "Deactivate",
                "controller" => "MemberRoles",
                "action" => "deactivate",
                "id" => "id",
                "condition" => ["granting_model" => "Direct Grant"],
                "options" => [
                    "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
                    "class" => "btn btn-danger"
                ],
            ];
            $currentTemplate = [
                "Role" => "role->name",
                "Start Date" => "start_on",
                "End Date" => "expires_on",
                "Approved By" => "approved_by->sca_name",
                "Granted By" => "granting_model",
                "Actions" => [
                    $linkTemplate, $removeLinkTemplate
                ],
            ];
            $previousTemplate = [
                "Role" => "role->name",
                "Start Date" => "start_on",
                "End Date" => "expires_on",
                "Approved By" => "approved_by->sca_name",
                "Granted By" => "granting_model",
                "Actions" => [
                    $linkTemplate
                ],
            ];

            echo $this->element('activeWindowTabs', [
                'user' => $user,
                'tabGroupName' => "roleTabs",
                'tabs' => [
                    "active" => [
                        "label" => __("Active"),
                        "id" => "active-roles",
                        "selected" => true,
                        "columns" => $currentTemplate,
                        "data" => $member->current_member_roles,
                    ],
                    "upcoming" => [
                        "label" => __("Upcoming"),
                        "id" => "upcoming-roles",
                        "selected" => false,
                        "columns" => $currentTemplate,
                        "data" => $member->upcoming_member_roles,
                    ],
                    "previous" => [
                        "label" => __("Previous"),
                        "id" => "previous-roles",
                        "selected" => false,
                        "columns" => $previousTemplate,
                        "data" => $member->previous_member_roles,
                    ]
                ]
            ]);
        } else {
            echo "<p>No Roles Assigned</p>";
        } ?>
    </div>
    <div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab">
        <?= $this->cell('Notes', [
            'topic_id' => $member->id,
            'topic_model' => 'Members',
            'viewPrivate' => $user->can("viewPrivateNotes", "Members"),
        ]) ?>
    </div>
    <?php if (!empty($aiForm)) : ?>
        <div class="related tab-pane fade m-3" id="nav-add-info" role="tabpanel" aria-labelledby="nav-add-info-tab">
            <?php
            $appInfo = $member->additional_info;
            if ($user->can("editAdditionalInfo", $member)) {
                echo $this->Form->create(null, [
                    //"align" => "horizontal",
                    "url" => ["controller" => "Members", "action" => "editAdditionalInfo", $member->id],
                ]);
                foreach ($aiForm as $fieldKey => $fieldType) {
                    if (!isset($appInfo[$fieldKey])) {
                        $appInfo[$fieldKey] = "";
                    }
                    switch ($fieldType) {
                        case "text":
                            echo $this->Form->control($fieldKey, [
                                "type" => 'text',
                                "value" => $appInfo[$fieldKey],
                            ]);
                            break;
                        case "date":
                            echo $this->Form->control($fieldKey, [
                                "type" => $fieldType,
                                "value" => $appInfo[$fieldKey],
                            ]);
                            break;
                        case "number":
                            echo $this->Form->control($fieldKey, [
                                "type" => 'number',
                                "value" => $appInfo[$fieldKey],
                            ]);
                            break;
                        case "bool":
                            if ($appInfo[$fieldKey]) {
                                echo $this->Form->control($fieldKey, ['type' => 'checkbox', 'checked' => 'checked', 'switch' => true]);
                            } else {
                                echo $this->Form->control($fieldKey, ['type' => 'checkbox', 'switch' => true]);
                            }
                            break;
                        default:
                            echo $this->Form->control($fieldKey, [
                                "type" => 'text',
                                "value" => $appInfo[$fieldKey],
                            ]);
                            break;
                    }
                }
                echo $this->Form->button("Submit", [
                    "class" => "btn btn-primary"
                ]);
                echo $this->form->end();
            } else { ?>
                <table class='table table-striped'>
                    <?php foreach ($aiForm as $fieldKey => $fieldType) { ?>
                        <tr scope="row">
                            <th class="col"><?= str_replace("_", " ", $fieldKey) ?></th>
                            <td class="col-10">
                                <?php
                                switch ($fieldType) {
                                    case "text":
                                        echo h($appInfo[$fieldKey]);
                                        break;
                                    case "date":
                                        echo h($appInfo[$fieldKey]);
                                        break;
                                    case "number":
                                        echo h($appInfo[$fieldKey]);
                                        break;
                                    case "bool":
                                        echo $this->KMP->bool($appInfo[$fieldKey], $this->Html);
                                        break;
                                    default:
                                        echo h($appInfo[$fieldKey]);
                                        break;
                                }
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } ?>
        </div>
    <?php endif; ?>
    <?php $this->KMP->endBlock() ?>
    <?php
    echo $this->KMP->startBlock("modals");
    // Start writing to modal block in layout
    echo $this->element('members/editModal', [
        'user' => $user,
    ]);
    echo $this->element('members/changePasswordModal', [
        'user' => $user,
    ]);
    echo $this->element('members/verifyMembershipModal', [
        'user' => $user,
        'needVerification' => $needVerification,
        'needsParentVerification' => $needsParentVerification,
        'needsMemberCardVerification' => $needsMemberCardVerification,
    ]);
    // End writing to modal block in layout
    $this->KMP->endBlock(); ?>

    <?php
    // Add scripts
    echo $this->KMP->startBlock("script"); ?>
    <script>
        class memberView {
            constructor() {
                this.ac = null;

            };
            run() {
                var me = this;
                if ($('#verify_member__sca_name').length > 0) {
                    var searchUrl =
                        '<?= $this->URL->build(['controller' => 'Members', 'action' => 'SearchMembers']) ?>';
                    KMP_utils.configureAutoComplete(me.ac, searchUrl, 'verify_member__sca_name', 'id', 'sca_name',
                        'verify_member__parent_id')
                }
            };
        };
        window.addEventListener('DOMContentLoaded', function() {
            var pageControl = new memberView();
            pageControl.run();
            <?php if ($passwordReset->getErrors()) { ?>
                $("#passwordModalBtn").click();
            <?php } ?>
            <?php if ($memberForm->getErrors()) { ?>
                $("#editModalBtn").click();
            <?php } ?>
        });
    </script>
    <?php $this->KMP->endBlock(); ?>