<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\member $member
 */
?>
<?php

use App\Model\Entity\Member;

$this->extend("/layout/TwitterBootstrap/view_record");
echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': View Member - ' . h($member->sca_name);
$this->KMP->endBlock();

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
<?php if ($user->checkCan("verifyMembership", "Members") && $needVerification) { ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
    data-bs-target="#verifyMembershipModal">Verify Membership</button>
<?php } ?>
<?php if (
    $user->checkCan("edit", $member) ||
    $user->checkCan("partialEdit", $member)
) { ?>
<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal"
    id='editModalBtn'>Edit</button>
<button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#passwordModal"
    id='passwordModalBtn'>Change Password</button>
<?php } ?>
<?php $this->KMP->endBlock() ?>

<?php echo $this->KMP->startBlock("recordDetails");
echo $this->element('members/memberDetails', [
    'user' => $user,
]);
$this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles" type="button" role="tab"
    aria-controls="nav-roles" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Roles") ?>
</button>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false" data-detail-tabs-target='tabBtn'><?= __("Notes") ?>
</button>
<?php if (!empty($aiForm)) : ?>
<button class=" nav-link" id="nav-add-info-tab" data-bs-toggle="tab" data-bs-target="#nav-add-info" type="button"
    role="tab" aria-controls="nav-add-info" aria-selected="false" data-detail-tabs-target='tabBtn'>
    <?= __("Additional Info") ?>
</button>
<?php endif; ?>
<?php $this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabContent") ?>
<div class="related tab-pane fade m-3" id="nav-roles" role="tabpanel" aria-labelledby="nav-roles-tab"
    data-detail-tabs-target="tabContent">
    <?php if (!empty($member->previous_member_roles) || !empty($member->current_member_roles) || !empty($member->upcoming_member_roles)) {
        $linkTemplate = [
            "type" => "link",
            "verify" => true,
            "authData" => "role",
            "label" => "",
            "controller" => "Roles",
            "action" => "view",
            "id" => "role_id",
            "options" => ["class" => "btn-sm btn btn-secondary bi-binoculars-fill"],
        ];
        $removeLinkTemplate = [
            "type" => "postLink",
            "verify" => true,
            "label" => "Deactivate",
            "controller" => "MemberRoles",
            "action" => "deactivate",
            "id" => "id",
            "condition" => ["entity_type" => "Direct Grant"],
            "options" => [
                "confirm" => "Are you sure you want to deactivate for {{member->sca_name}}?",
                "class" => "btn-sm btn btn-danger"
            ],
        ];
        $currentTemplate = [
            "Role" => "role->name",
            "Start Date" => "start_on_to_string",
            "End Date" => "expires_on_to_string",
            "Approved By" => "approved_by->sca_name",
            "Granted By" => "entity_type",
            "Scope" => "branch->name",
            "Actions" => [
                $linkTemplate,
                $removeLinkTemplate
            ],
        ];
        $previousTemplate = [
            "Role" => "role->name",
            "Start Date" => "start_on_to_string",
            "End Date" => "expires_on_to_string",
            "Approved By" => "approved_by->sca_name",
            "Granted By" => "entity_type",
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
<div class="related tab-pane fade m-3" id="nav-notes" role="tabpanel" aria-labelledby="nav-notes-tab"
    data-detail-tabs-target="tabContent">
    <?= $this->cell('Notes', [
        'entity_id' => $member->id,
        'entity_type' => 'Members',
        'viewPrivate' => $user->checkCan("viewPrivateNotes", "Members"),
    ]) ?>
</div>
<?php if (!empty($aiForm)) : ?>
<div class="related tab-pane fade m-3" id="nav-add-info" role="tabpanel" aria-labelledby="nav-add-info-tab"
    data-detail-tabs-target="tabContent">
    <?php
        $appInfo = $member->additional_info;
        $userEditableOnly = !$user->checkCan("edit", $member);
        if ($user->checkCan("editAdditionalInfo", $member)) {
            echo $this->Form->create(null, [
                //"align" => "horizontal",
                "url" => ["controller" => "Members", "action" => "editAdditionalInfo", $member->id],
            ]);
            foreach ($aiForm as $fieldKey => $fieldType) {
                if (!isset($appInfo[$fieldKey])) {
                    $appInfo[$fieldKey] = "";
                }
                //check if the field contains a pipe
                $pipePos = strpos($fieldType, "|");
                $managerOnly = false;
                $userEditable = false;
                if ($pipePos !== false) {
                    $fieldSecDetails = explode("|", $fieldType);
                    $fieldType = $fieldSecDetails[0];
                    $userEditable = $fieldSecDetails[1] == "user";
                    $editorOnly = $fieldSecDetails[1] == "manager_only";
                }
                $disabled = false;
                if ($userEditableOnly && !$userEditable) {
                    $disabled = true;
                }
                if ($managerOnly && $userEditableOnly) {
                    continue;
                }
                //check if the fieldType contains a :
                $colonPos = strpos($fieldType, ":");
                $aiOptions = [];
                if ($colonPos !== false) {
                    $fieldDetails = explode(":", $fieldType);
                    $fieldType =  $fieldDetails[0];
                    $aiOptions = explode(",", $fieldDetails[1]);
                }
                switch ($fieldType) {
                    case "text":
                        echo $this->Form->control($fieldKey, [
                            "type" => 'text',
                            "value" => $appInfo[$fieldKey],
                            "disabled" => $disabled,
                        ]);
                        break;
                    case "date":
                        echo $this->Form->control($fieldKey, [
                            "type" => $fieldType,
                            "value" => $appInfo[$fieldKey],
                            "disabled" => $disabled,
                        ]);
                        break;
                    case "number":
                        echo $this->Form->control($fieldKey, [
                            "type" => 'number',
                            "value" => $appInfo[$fieldKey],
                            "disabled" => $disabled,
                        ]);
                        break;
                    case "bool":
                        if ($appInfo[$fieldKey]) {
                            echo $this->Form->control($fieldKey, [
                                'type' => 'checkbox',
                                'checked' => 'checked',
                                'switch' => true,
                                "disabled" => $disabled,
                            ]);
                        } else {
                            echo $this->Form->control($fieldKey, [
                                'type' => 'checkbox',
                                'switch' => true,
                                "disabled" => $disabled,
                            ]);
                        }
                        break;
                    case "select":
                        $selectOptions = [];
                        foreach ($aiOptions as $option) {
                            $selectOptions[$option] = $option;
                        }
                        echo $this->Form->control($fieldKey, [
                            "type" => 'select',
                            'empty' => true,
                            "options" => $selectOptions,
                            "value" => $appInfo[$fieldKey],
                            "disabled" => $disabled,
                        ]);
                        break;
                    default:
                        echo $this->Form->control($fieldKey, [
                            "type" => 'text',
                            "value" => $appInfo[$fieldKey],
                            "disabled" => $disabled,
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
                            $pipePos = strpos($fieldType, "|");
                            $managerOnly = false;
                            $userEditable = false;
                            if ($pipePos !== false) {
                                $fieldSecDetails = explode("|", $fieldType);
                                $fieldType = $fieldSecDetails[0];
                                $editorOnly = $fieldSecDetails[1] == "manager_only";
                            }
                            $disabled = false;
                            if ($managerOnly && $userEditableOnly) {
                                continue;
                            }
                            //check if the fieldType contains a :
                            $colonPos = strpos($fieldType, ":");
                            $aiOptions = [];
                            if ($colonPos !== false) {
                                $fieldDetails = explode(":", $fieldType);
                                $fieldType =  $fieldDetails[0];
                            }
                            switch ($fieldType) {
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