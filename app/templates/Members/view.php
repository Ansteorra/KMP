<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\member $member
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

use Cake\I18n\Date;
use Cake\I18n\DateTime;

$user = $this->request->getAttribute("identity");
?>

<div class="members view large-9 medium-8 columns content">
    <div class="row align-items-start">
        <div class="col">
            <h3>
                <?php if (count($backUrl) > 0) { ?>
                <?= $this->Html->link(
                        "",
                        $backUrl,
                        ["class" => "bi bi-arrow-left-circle"],
                    ) ?>
                <?php } ?>
                <?= h($member->sca_name) ?>
            </h3>
        </div>
        <div class="col text-end">
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
                    <?php if (strlen($member->membership_number) > 0) { ?>
                    <?= h($member->membership_number) ?> Exp:
                    <?= h($member->membership_expires_on) ?>
                    <?php } else { ?>
                    Information Not Available
                    <?php if ($user->can("verifyMembership", "Members")) { ?>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#verifyMembershipModal">Verify Membership</button>
                    <?php } ?>
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
                    h($member->parent_name) .
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
        </table>
    </div>
    <div class="related">
        <h4>
            <h><?= __("Authorization") ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                    data-bs-target="#requestAuthModal">Request Authorization</button>
        </h4>
        <?php if (!empty($member->authorizations)) {

            $pending = [];
            $approved = [];
            $expired = [];
            $exp_date = Date::now();
            foreach ($member->authorizations as $auth) {
                if ($auth->expires_on === null) {
                    $pending[] = $auth;
                } elseif ($auth->expires_on < $exp_date) {
                    $expired[] = $auth;
                } else {
                    $approved[] = $auth;
                }
            }
        ?>
        <nav>
            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                <button class="nav-link active" id="nav-active-approvals-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-active-approvals" type="button" role="tab" aria-controls="nav-active-approvals"
                    aria-selected="true">Approved</button>
                <button class="nav-link" id="nav-expired-approvals-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-expired-approvals" type="button" role="tab"
                    aria-controls="nav-expired-approvals" aria-selected="false">Inactive</button>
                <button class="nav-link" id="nav-pending-approvals-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-pending-approvals" type="button" role="tab"
                    aria-controls="nav-pending-approvals" aria-selected="false">Pending
                    <?php if (count($pending) > 0) { ?>
                    <span class="badge bg-danger"><?= count($pending) ?></span>
                    <?php } ?>
                </button>
            </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active" id="nav-active-approvals" role="tabpanel"
                aria-labelledby="nav-active-approvals-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Authorization") ?></th>
                            <th scope="col"><?= __("Start On") ?></th>
                            <th scope="col"><?= __("Expires On") ?></th>
                            <th scope="col" class="actions"><?= __(
                                                                    "Actions",
                                                                ) ?></th>
                        </tr>
                        <?php foreach ($approved as $auth) : ?>
                        <tr>
                            <td><?= h(
                                            $auth->authorization_type->name,
                                        ) ?></td>
                            <td><?= h($auth->start_on) ?></td>
                            <td><?= h($auth->expires_on) ?></td>
                            <td>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#renewalModal"
                                    onclick="$('#renew_auth__id').val('<?= $auth->id ?>'); $('#renew_auth__auth_type_id').val('<?= $auth->authorization_type->id ?>');$('#renew_auth__auth_type_id').trigger('change');">Renew</button>
                                <?php if ($user->can("revoke", "Authorizations")) { ?>
                                <button type="button" class="btn btn-danger " data-bs-toggle="modal"
                                    data-bs-target="#revokeModal"
                                    onclick="$('#revoke_auth__id').val('<?= $auth->id ?>')">Revoke</button>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="nav-expired-approvals" role="tabpanel"
                aria-labelledby="nav-expired-approvals-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Authorization") ?></th>
                            <th scope="col"><?= __("Start On") ?></th>
                            <th scope="col"><?= __("Expires On") ?></th>
                            <th scope="col"><?= __("Reason") ?></th>
                        </tr>
                        <?php foreach ($expired as $auth) {
                                $assigned_to = ""; ?>
                        <tr>
                            <td><?= h(
                                            $auth->authorization_type->name,
                                        ) ?></td>
                            <td><?= h($auth->start_on) ?></td>
                            <td><?= h($auth->expires_on) ?></td>
                            <td><?= h(
                                            $auth->status == "approved"
                                                ? "expired"
                                                : $auth->status,
                                        ) ?>
                                <?php if ($auth->status == "revoked") {
                                            echo " - " .
                                                h($auth->revoker->sca_name) .
                                                " on " .
                                                h($auth->revoked_on) .
                                                " note: " .
                                                h($auth->revoked_reason);
                                        } ?>
                            </td>
                        </tr>
                        <?php
                            } ?>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="nav-pending-approvals" role="tabpanel"
                aria-labelledby="nav-pending-approvals-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Authorization") ?></th>
                            <th scope="col"><?= __("Requested On") ?></th>
                            <th scope="col"><?= __("Assigned To") ?></th>
                        </tr>
                        <?php foreach ($pending as $auth) : ?>
                        <tr>
                            <td><?= h(
                                            $auth->authorization_type->name,
                                        ) ?></td>
                            <td><?= h($auth->requested_on) ?></td>
                            <td>
                                <?php if (
                                            !empty($auth->authorization_approvals)
                                        ) : ?>
                                <?= h(
                                                $auth->authorization_approvals[0]
                                                    ->approver->sca_name,
                                            ) ?></td>
                            <?php else : ?>
                            Unassigned
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php
        } ?>
        <?php if (!empty($member->member_roles)) {

            $active = [];
            $inactive = [];
            foreach ($member->member_roles as $role) {
                if (
                    $role->expires_on === null ||
                    $role->expires_on > DateTime::now()
                ) {
                    $active[] = $role;
                } else {
                    $inactive[] = $role;
                }
            }
            // sort $active by start_on
            usort($active, function ($a, $b) {
                return $a->start_on <=> $b->start_on;
            });
            // sort $inactive by expires_on
            usort($inactive, function ($a, $b) {
                return $a->expires_on <=> $b->expires_on;
            });
        ?>
        <div class="related">
            <h4><?= __("Roles") ?></h4>

            <nav>
                <div class="nav nav-tabs" id="nav-tab" role="tablist">
                    <button class="nav-link active" id="nav-active-members-tab" data-bs-toggle="tab"
                        data-bs-target="#nav-active-members" type="button" role="tab" aria-controls="nav-active-members"
                        aria-selected="true">Active</button>
                    <button class="nav-link" id="nav-deactivated-members-tab" data-bs-toggle="tab"
                        data-bs-target="#nav-deactivated-members" type="button" role="tab"
                        aria-controls="nav-pdeactivated-members" aria-selected="false">Deactivated</button>
                </div>
            </nav>
            <div class="tab-content" id="nav-tabContent">
                <div class="tab-pane fade show active" id="nav-active-members" role="tabpanel"
                    aria-labelledby="nav-active-members-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <tr>
                                <th scope="col"><?= __("Role") ?></th>
                                <th scope="col"><?= __(
                                                        "Assignment Date",
                                                    ) ?></th>
                                <th scope="col"><?= __(
                                                        "Expire Date",
                                                    ) ?></th>
                                <th scope="col"><?= __(
                                                        "Approved By",
                                                    ) ?></th>
                                <?php if ($user->can("view", "Roles")) { ?>
                                <th scope="col" class="actions"><?= __(
                                                                            "Actions",
                                                                        ) ?></th>
                                <?php } ?>
                            </tr>
                            <?php foreach ($active as $memberRole) : ?>
                            <tr>
                                <td><?= h(
                                                $memberRole->role->name,
                                            ) ?></td>
                                <td><?= h($memberRole->start_on) ?></td>
                                <td><?= h($memberRole->expires_on) ?></td>
                                <td><?= h(
                                                $memberRole->approved_by->sca_name,
                                            ) ?></td>
                                <?php if (
                                            $user->can(
                                                "view",
                                                $memberRole->role,
                                            )
                                        ) { ?>
                                <td class="actions">
                                    <?= $this->Html->link(
                                                    __("View"),
                                                    [
                                                        "controller" => "Roles",
                                                        "action" => "view",
                                                        $memberRole->role_id,
                                                    ],
                                                    [
                                                        "class" =>
                                                        "btn btn-secondary",
                                                    ],
                                                ) ?>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="nav-deactivated-members" role="tabpanel"
                    aria-labelledby="nav-deactivated-members-tab" tabindex="0">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <tr>
                                <th scope="col"><?= __("Role") ?></th>
                                <th scope="col"><?= __(
                                                        "Assignment Date",
                                                    ) ?></th>
                                <th scope="col"><?= __(
                                                        "Expire Date",
                                                    ) ?></th>
                                <th scope="col"><?= __(
                                                        "Approved By",
                                                    ) ?></th>
                            </tr>
                            <?php foreach ($inactive as $memberRole) : ?>
                            <tr>
                                <td><?= h(
                                                $memberRole->role->name,
                                            ) ?></td>
                                <td><?= h($memberRole->start_on) ?></td>
                                <td><?= h($memberRole->expires_on) ?></td>
                                <td><?= h(
                                                $memberRole->approved_by->sca_name,
                                            ) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
        } ?>
    <div class="related">
        <h4><?= __("Notes") ?></h4>
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
                <div id="note_<?= $note->id ?>" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <?= $this->Text->autoParagraph(
                                h($note->body),
                            ) ?>
                    </div>
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

    <?php // Start writing to modal block in layout

$this->start("modals"); ?>
    <?php echo $this->Modal->create("Edit " . $member->sca_name, [
    "id" => "editModal",
    "close" => true,
]); ?>
    <fieldset>
        <?php if ($user->can("edit", $member)) {
        echo $this->Form->create($memberForm, [
            "url" => [
                "controller" => "Members",
                "action" => "edit",
                $member->id,
            ],
            "id" => "edit_entity",
        ]);
        echo $this->Form->control("sca_name");
        echo $this->Form->control("membership_number");
        echo $this->Form->control("membership_expires_on", [
            "empty" => true,
        ]);
        echo $this->Form->control("branch_id", ["options" => $treeList]);
        echo $this->Form->control("first_name");
        echo $this->Form->control("middle_name");
        echo $this->Form->control("last_name");
        echo $this->Form->control("street_address");
        echo $this->Form->control("city");
        echo $this->Form->control("state");
        echo $this->Form->control("zip");
        echo $this->Form->control("phone_number");
        echo $this->Form->control("email_address");
        echo $this->Form->control("background_check_expires_on", [
            "empty" => true,
        ]);
        echo $member->age < 18 ? $this->Form->control("parent_name") : "";
        echo $this->Form->control("birth_month");
        echo $this->Form->control("birth_year");
        echo $this->Form->control("hidden");
    } else {
        if ($user->can("partialEdit", $member)) {
            echo $this->Form->create($memberForm, [
                "url" => [
                    "controller" => "Members",
                    "action" => "partialEdit",
                    $member->id,
                ],
                "id" => "edit_entity",
            ]);
            echo $this->Form->control("sca_name");
            echo $this->Form->control("branch_id", [
                "options" => $treeList,
            ]);
            echo $this->Form->control("first_name");
            echo $this->Form->control("middle_name");
            echo $this->Form->control("last_name");
            echo $this->Form->control("street_address");
            echo $this->Form->control("city");
            echo $this->Form->control("state");
            echo $this->Form->control("zip");
            echo $this->Form->control("phone_number");
            echo $this->Form->control("email_address");
            echo $member->age < 18
                ? $this->Form->control("parent_name")
                : "";
        }
    } ?>
    </fieldset>
    <?= $this->Form->end() ?>
    <?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "edit_entity__submit",
        "onclick" => '$("#edit_entity").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>

    <?php echo $this->Modal->create("Edit " . $member->sca_name, [
    "id" => "passwordModal",
    "close" => true,
]); ?>
    <?= $this->Form->create($passwordReset, [
    "id" => "change_password",
    "url" => [
        "controller" => "Members",
        "action" => "changePassword",
        $member->id,
    ],
]) ?>
    <fieldset>
        <legend><?= __("Change Password") ?></legend>
        <?php
    echo $this->Form->control("new_password", [
        "type" => "password",
    ]);
    echo $this->Form->control("confirm_password", [
        "type" => "password",
    ]);
    ?>
    </fieldset>
    <?= $this->Form->end() ?>
    <?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "change_password__submit",
        "onclick" => '$("#change_password").submit();',
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>


    <?php echo $this->Modal->create("Request Authorization", [
    "id" => "requestAuthModal",
    "close" => true,
]); ?>
    <fieldset>
        <?php
    echo $this->Form->create(null, [
        "id" => "request_auth__form",
        "url" => ["controller" => "Authorizations", "action" => "add"],
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "value" => $member->id,
        "id" => "request_auth__member_id",
    ]);
    echo $this->Form->control("authorization_type", [
        "options" => $authorization_types,
        "empty" => true,
        "id" => "request_auth__auth_type_id",
        "label" => "Authorization",
    ]);
    echo $this->Form->control("approver_id", [
        "type" => "select",
        "options" => [],
        "id" => "request_auth__approver_id",
        "label" => "Send Request To",
        "disabled" => "disabled",
    ]);
    echo $this->Form->end();
    ?>
    </fieldset>
    <?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "request_auth__submit",
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]); ?>

    <?php if ($user->can("revoke", "Authorizations")) {
    echo $this->Modal->create("Revoke Authorization", [
        "id" => "revokeModal",
        "close" => true,
    ]); ?>
    <fieldset>
        <?php
        echo $this->Form->create(null, [
            "url" => ["controller" => "Authorizations", "action" => "revoke"],
            "id" => "revoke_auth",
        ]);
        echo $this->Form->control("id", [
            "type" => "hidden",
            "id" => "revoke_auth__id",
        ]);
        echo $this->Form->control("revoked_reason", [
            "label" => "Reason for Revocation",
            "onkeypress" => '$("#revoke_auth__submit").removeAttr("disabled");',
        ]);
        echo $this->Form->end();
        ?>
    </fieldset>
    <?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "revoke_auth__submit",
            "onclick" => '$("#revoke_auth").submit();',
            "disabled" => "disabled",
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]);
} ?>

    <?php
echo $this->Modal->create("Renew Authorization", [
    "id" => "renewalModal",
    "close" => true,
]); ?>
    <fieldset>
        <?php
    echo $this->Form->create(null, [
        "url" => ["controller" => "Authorizations", "action" => "renew"],
        "id" => "renew_auth__form",
    ]);
    echo $this->Form->control("id", [
        "type" => "hidden",
        "id" => "renew_auth__id",
    ]);
    echo $this->Form->control("member_id", [
        "type" => "hidden",
        "value" => $member->id,
        "id" => "renew_auth__member_id",
    ]);
    echo $this->Form->control("authorization_type", [
        "type" => "hidden",
        "id" => "renew_auth__auth_type_id"
    ]);
    echo $this->Form->control("approver_id", [
        "type" => "select",
        "options" => [],
        "id" => "renew_auth__approver_id",
        "label" => "Send Request To",
        "disabled" => "disabled",
    ]);
    echo $this->Form->end();
    ?>
    </fieldset>
    <?php echo $this->Modal->end([
    $this->Form->button("Submit", [
        "class" => "btn btn-primary",
        "id" => "renew_auth__submit",
        "onclick" => '$("#renew_auth__form").submit();',
        "disabled" => "disabled",
    ]),
    $this->Form->button("Close", [
        "data-bs-dismiss" => "modal",
    ]),
]);


if ($user->can("verifyMembership", "Members")) {
    echo $this->Modal->create("Verify Membership", [
        "id" => "verifyMembershipModal",
        "close" => true,
    ]); ?>
    <fieldset>
        <?php
        echo $this->Form->create(null, [
            "url" => ["controller" => "Members", "action" => "verifyMembership", $member->id],
            "id" => "verify__form",
        ]);
        if (strlen($member->membership_card_path) > 0) {
            echo $this->Glide->image($member->membership_card_path, [], ['width' => '400']);
        }
        echo $this->Form->control("member_id", [
            "type" => "hidden",
            "value" => $member->id,
            "id" => "verify__member_id",
        ]);
        echo $this->Form->control("membership_number", [
            "id" => "verify__membership_number"
        ]);
        echo $this->Form->control("membership_expires_on", [
            "type" => "date",
            "id" => "verify__membership_expires_on",
            "empty" => true,
        ]);
        echo $this->Form->end();
        ?>
    </fieldset>
    <?php echo $this->Modal->end([
        $this->Form->button("Submit", [
            "class" => "btn btn-primary",
            "id" => "verify__submit",
            "onclick" => '$("#verify__form").submit();',
        ]),
        $this->Form->button("Close", [
            "data-bs-dismiss" => "modal",
        ]),
    ]);
}
?>


    <?php // finish writing to modal block in layout

$this->end(); ?>

    <?php
$this->append("script", $this->Html->script(["app/members/view.js"]));
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