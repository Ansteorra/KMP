<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\member $member
 */
?>
<?php

use App\Model\Entity\Member;

$this->extend("/layout/TwitterBootstrap/dashboard");

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use App\KMP\StaticHelpers;

$needVerification = false;
$needsParentVerification = false;
$needsMemberCardVerification = false;
$user = $this->request->getAttribute("identity");
$aiFormConfig = StaticHelpers::appSettingsStartWith("Member.AdditionalInfo.");
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

?>

<div class="members view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <a href="#" onclick="window.history.back();" class="bi bi-arrow-left-circle"></a>
                <?= h($member->sca_name) ?>
            </h3>
        </div>
        <div class="col text-end">
            <?php if ($user->can("verifyMembership", "Members") && $needVerification) { ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                data-bs-target="#verifyMembershipModal">Verify Membership</button>
            <?php } ?>
            <?php if (
                $user->can("edit", $member) ||
                $user->can("partialEdit", $member)
            ) { ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"
                id='editModalBtn'>Edit</button>
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#passwordModal"
                id='passwordModalBtn'>Change Password</button>
            <?php } ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
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
                $externalLinks = $this->KMP->appSettingsStartWith("Member.ExternalLink.");
                foreach ($externalLinks as $key => $link) {
                    $linkLabel = str_replace("Member.ExternalLink.", "", $key);
                    $linkUrl = StaticHelpers::processTemplate($link, $member, 1, "__missing__");
                    if (substr_count($linkUrl, "__missing__") == 0) {
                        echo "<tr scope='row'><th class='col'>" . $linkLabel . "</th><td class='col-10'><a href='" . $linkUrl . "' target='_blank'>" . $linkUrl . "</a></td></tr>";
                    }
                }
                ?>
        </table>
    </div>
    <nav>
        <div class="nav nav-tabs" id="nav-memberAreas" role="tablist">
            <button class="nav-link active" id="nav-authorizations-tab" data-bs-toggle="tab"
                data-bs-target="#nav-authorizations" type="button" role="tab" aria-controls="nav-authorizations"
                aria-selected="true"><?= __("Authorizations") ?>
            </button>
            <button class="nav-link" id="nav-offices-tab" data-bs-toggle="tab" data-bs-target="#nav-offices"
                type="button" role="tab" aria-controls="nav-offices" aria-selected="false"><?= __("Offices") ?>
            </button>
            <button class="nav-link" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles" type="button"
                role="tab" aria-controls="nav-roles" aria-selected="false"><?= __("Roles") ?>
            </button>
            <button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button"
                role="tab" aria-controls="nav-notes" aria-selected="false"><?= __("Notes") ?>
            </button>
            <?php if (!empty($aiForm)) : ?>
            <button class="nav-link" id="nav-add-info-tab" data-bs-toggle="tab" data-bs-target="#nav-add-info"
                type="button" role="tab" aria-controls="nav-add-info"
                aria-selected="false"><?= __("Additional Info.") ?>
            </button>
            <?php endif; ?>

        </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <div class="related tab-pane fade show active m-3" id="nav-authorizations" role="tabpanel"
            aria-labelledby="nav-authorizations-tab">
            <button type="button" class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal"
                data-bs-target="#requestAuthModal">Request Authorization</button>
            <?= $this->Html->link(
                __("Email Link to Mobile Card"),
                ["controller" => "Members", "action" => "SendMobileCardEmail", $member->id],
                ["class" => "btn btn-sm mb-3 btn-secondary"],
            ) ?>

            <?php if (!empty($member->previous_authorizations) || !empty($member->current_authorizations) || !empty($member->pending_authorizations)) {
                $renewButton = [
                    "type" => "button",
                    "verify" => false,
                    "label" => "Renew",
                    "options" => [
                        "class" => "btn btn-primary",
                        "data-bs-toggle" => "modal",
                        "data-bs-target" => "#renewalModal",
                        "onclick" => "$('#renew_auth__id').val('{{id}}'); $('#renew_auth__auth_type_id').val('{{activity->id}}');$('#renew_auth__auth_type_id').trigger('change');",

                    ],
                ];
                $revokeButton = [
                    "type" => "button",
                    "verify" => true,
                    "label" => "Revoke",
                    "controller" => "Authorizations",
                    "action" => "revoke",
                    "options" => [
                        "class" => "btn btn-danger",
                        "data-bs-toggle" => "modal",
                        "data-bs-target" => "#revokeModal",
                        "onclick" => "$('#revoke_auth__id').val('{{id}}')",
                    ],
                ];
                $activeColumnTemplate = [
                    "Authorization" => "activity->name",
                    "Start Date" => "start_on",
                    "End Date" => "expires_on",
                    "Actions" => [
                        $renewButton,
                        $revokeButton
                    ]
                ];
                $pendingColumnTemplate = [
                    "Authorization" => "activity->name",
                    "Requested Date" => "current_pending_approval->requested_on",
                    "Assigned To" => "current_pending_approval->approver->sca_name",
                ];
                $previousColumnTemplate = [
                    "Authorization" => "activity->name",
                    "Start Date" => "start_on",
                    "End Date" => "expires_on",
                    "Reason" => "revoked_reason",
                ];
                echo $this->element('activeWindowTabs', [
                    'user' => $user,
                    'tabGroupName' => "authorizationTabs",
                    'tabs' => [
                        "active" => [
                            "label" => __("Active"),
                            "id" => "active-authorization",
                            "selected" => true,
                            "columns" => $activeColumnTemplate,
                            "data" => $member->current_authorizations,
                        ],
                        "pending" => [
                            "label" => __("Pending"),
                            "id" => "upcoming-authorization",
                            "badge" => count($member->pending_authorizations),
                            "badgeClass" => "bg-danger",
                            "selected" => false,
                            "columns" => $pendingColumnTemplate,
                            "data" => $member->pending_authorizations,
                        ],
                        "previous" => [
                            "label" => __("Previous"),
                            "id" => "previous-authorization",
                            "selected" => false,
                            "columns" => $previousColumnTemplate,
                            "data" => $member->previous_authorizations,
                        ]
                    ]
                ]);
            } else {
                echo "<p>No Authorizations</p>";
            } ?>
        </div>
        <div class="related tab-pane fade m-3" id="nav-offices" role="tabpanel" aria-labelledby="nav-offices-tab">
            <?php if (!empty($member->previous_officers) || !empty($member->current_officers) || !empty($member->upcoming_officers)) {
                $linkTemplate = [
                    "type" => "link",
                    "verify" => true,
                    "authData" => "office",
                    "label" => "View",
                    "controller" => "Offices",
                    "action" => "view",
                    "id" => "office_id",
                    "options" => ["class" => "btn btn-secondary"],
                ];
                $columnsTemplate = [
                    "Office" => "office->name",
                    "Branch" => "branch->name",
                    "Start Date" => "start_on",
                    "End Date" => "expires_on",
                    "Actions" => [
                        $linkTemplate
                    ],
                ];
                echo $this->element('activeWindowTabs', [
                    'user' => $user,
                    'tabGroupName' => "officeTabs",
                    'tabs' => [
                        "active" => [
                            "label" => __("Active"),
                            "id" => "active-office",
                            "selected" => true,
                            "columns" => $columnsTemplate,
                            "data" => $member->current_officers,
                        ],
                        "upcoming" => [
                            "label" => __("Upcoming"),
                            "id" => "upcoming-office",
                            "selected" => false,
                            "columns" => $columnsTemplate,
                            "data" => $member->upcoming_officers,
                        ],
                        "previous" => [
                            "label" => __("Previous"),
                            "id" => "previous-office",
                            "selected" => false,
                            "columns" => $columnsTemplate,
                            "data" => $member->previous_officers,
                        ]
                    ]
                ]);
            } else {
                echo "<p>No Offices assigned</p>";
            } ?>
        </div>
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
                $columnsTemplate = [
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
                            "columns" => $columnsTemplate,
                            "data" => $member->current_member_roles,
                        ],
                        "upcoming" => [
                            "label" => __("Upcoming"),
                            "id" => "upcoming-roles",
                            "selected" => false,
                            "columns" => $columnsTemplate,
                            "data" => $member->upcoming_member_roles,
                        ],
                        "previous" => [
                            "label" => __("Previous"),
                            "id" => "previous-roles",
                            "selected" => false,
                            "columns" => $columnsTemplate,
                            "data" => $member->previous_member_roles,
                        ]
                    ]
                ]);
            } else {
                echo "<p>No Roles Assigned</p>";
            } ?>
        </div>
        <div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab">
            <div class="accordion mb-3" id="accordionExample">
                <?php if (!empty($member->notes)) : ?>
                <?php foreach ($member->notes as $note) : ?>
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#note_<?= $note->id ?>" aria-expanded="true" aria-controls="collapseOne">
                            <?= h($note->subject) ?> : <?= h(
                                                                    $note->created_on,
                                                                ) ?> - by <?= h($note->author->sca_name) ?>
                            <?= $note->private
                                        ? '<span class="mx-3 badge bg-secondary">Private</span>'
                                        : "" ?>
                        </button>
                    </h2>
                    <div id="note_<?= $note->id ?>" class="accordion-collapse collapse"
                        data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                            <?= $this->Text->autoParagraph(
                                        h($note->body),
                                    ) ?>

                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#note_new" aria-expanded="true" aria-controls="collapseOne">
                                Add a Note
                            </button>
                        </h2>
                        <div id="note_new" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                            <div class="accordion-body">
                                <?= $this->Form->create($newNote, [
                                    "url" => ["action" => "addNote", $member->id],
                                ]) ?>
                                <fieldset>
                                    <legend><?= __("Add Note") ?></legend>
                                    <?php
                                    echo $this->Form->control("subject");
                                    echo $user->can("viewPrivateNotes", $member)
                                        ? $this->Form->control("private", [
                                            "type" => "checkbox",
                                            "label" => "Private",
                                        ])
                                        : "";
                                    echo $this->Form->control("body", [
                                        "label" => "Note",
                                    ]);
                                    ?>
                                </fieldset>
                                <div class='text-end'><?= $this->Form->button(
                                                            __("Submit"),
                                                            ["class" => "btn-primary"],
                                                        ) ?></div>
                                <?= $this->Form->end() ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
    </div>
</div>
<?php
$this->start("modals");
// Start writing to modal block in layout

echo $this->element('members/editModal', [
    'user' => $user,
]);
echo $this->element('members/changePasswordModal', [
    'user' => $user,
]);
echo $this->element('members/requestAuthorizationModal', [
    'user' => $user,
]);
echo $this->element('members/revokeAuthorizationModal', [
    'user' => $user,
]);
echo $this->element('members/renewAuthorizationModal', [
    'user' => $user,
]);
echo $this->element('members/verifyMembershipModal', [
    'user' => $user,
    'needVerification' => $needVerification,
    'needsParentVerification' => $needsParentVerification,
    'needsMemberCardVerification' => $needsMemberCardVerification,
]);
// End writing to modal block in layout
$this->end(); ?>

<?php
// Add scripts
$this->append("script", $this->Html->script(["app/autocomplete.js"]));
$this->append("script", $this->Html->script(["app/members/view.js"]));
$this->append("script", $this->Html->scriptBlock("
        var pageControl = new memberView();
        pageControl.run('" . $this->Url->webroot("") . "');
"));
if ($passwordReset->getErrors()) {
    $this->append(
        "script",
        $this->Html->scriptBlock('$("#passwordModalBtn").click();'),
    );
}
if ($memberForm->getErrors()) {
    $this->append(
        "script",
        $this->Html->scriptBlock('$("#editModalBtn").click()'),
    );
}
?>