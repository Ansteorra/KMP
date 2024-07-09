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
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': View Member - ' . h($member->sca_name);
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
<?php $this->KMP->endBlock() ?>

<?php echo $this->KMP->startBlock("recordDetails");
echo $this->element('members/memberDetails', [
    'user' => $user,
]);
$this->KMP->endBlock() ?>
<?php $this->KMP->startBlock("tabButtons") ?>
<button class="nav-link" id="nav-roles-tab" data-bs-toggle="tab" data-bs-target="#nav-roles" type="button" role="tab"
    aria-controls="nav-roles" aria-selected="false"><?= __("Roles") ?>
</button>
<button class="nav-link" id="nav-notes-tab" data-bs-toggle="tab" data-bs-target="#nav-notes" type="button" role="tab"
    aria-controls="nav-notes" aria-selected="false"><?= __("Notes") ?>
</button>
<?php if (!empty($aiForm)) : ?>
<button class="nav-link" id="nav-add-info-tab" data-bs-toggle="tab" data-bs-target="#nav-add-info" type="button"
    role="tab" aria-controls="nav-add-info" aria-selected="false"><?= __("Additional Info") ?>
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
        $userEditableOnly = !$user->can("edit", $member);
        if ($user->can("editAdditionalInfo", $member)) {
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
        $('#edit_entity__email_address').removeAttr('oninput');
        $('#edit_entity__email_address').removeAttr('oninvalid');
        $('#edit_entity__email_address').on('change', function() {
            var email = $('#edit_entity__email_address').val();
            if (email == '') {
                $('#edit_entity__email_address').removeClass('is-invalid');
                $('#edit_entity__email_address').removeClass('is-valid');
                $('#edit_entity__email_address')[0].setCustomValidity('');
                return;
            }
            var original_email = $('#edit_entity__email_address').data('original-value');
            if (email == original_email) {
                $('#edit_entity__email_address').addClass('is-valid');
                $('#edit_entity__email_address').removeClass('is-invalid');
                return;
            }
            var checkEmailUrl =
                '<?= $this->URL->build(['controller' => 'Members', 'action' => 'emailTaken']) ?>' +
                '?email=' + encodeURIComponent(email);
            $.get(checkEmailUrl, {
                email: email
            }, function(data) {
                if (data) {
                    $('#edit_entity__email_address').addClass('is-invalid');
                    $('#edit_entity__email_address').removeClass('is-valid');
                    $('#edit_entity__email_address')[0].setCustomValidity(
                        'This email address is already taken.');
                } else {
                    $('#edit_entity__email_address').addClass('is-valid');
                    $('#edit_entity__email_address').removeClass('is-invalid');
                    $('#edit_entity__email_address')[0].setCustomValidity('');
                }
            });
        });
        //on input this.setCustomValidity('')
        //on invalid this.setCustomValidity(''); if (!this.value) this.setCustomValidity(this.dataset.validityMessage)
    };
};
window.addEventListener('DOMContentLoaded', function() {
    var pageControl = new memberView();
    pageControl.run();
    <?php if ($passwordReset->getErrors()) { ?>
    $("#passwordModalBtn").on('click');
    <?php } ?>
    <?php if ($memberForm->getErrors()) { ?>
    $("#editModalBtn").on('click');
    <?php } ?>
});
</script>
<?php $this->KMP->endBlock(); ?>
<?php if ($memberForm->getErrors()) { ?>
$("#editModalBtn").on('click');
<?php } ?>
});
</script>
<?php $this->KMP->endBlock(); ?>