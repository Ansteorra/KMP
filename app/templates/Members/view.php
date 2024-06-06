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

$needVerification = false;
$needsParentVerification = false;
$needsMemberCardVerification = false;
$user = $this->request->getAttribute("identity");
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
                    <?php if (strlen($member->membership_number) > 0) { ?>
                    <?= h($member->membership_number) ?> Exp:
                    <?= h($member->membership_expires_on) ?>
                    <?php } else { ?>
                    Information Not Available
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
        </table>
    </div>
    <div class="related">
        <h4><?= __("Authorization") ?>
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
                                            $auth->activity->name,
                                        ) ?></td>
                            <td><?= h($auth->start_on) ?></td>
                            <td><?= h($auth->expires_on) ?></td>
                            <td>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#renewalModal"
                                    onclick="$('#renew_auth__id').val('<?= $auth->id ?>'); $('#renew_auth__auth_type_id').val('<?= $auth->activity->id ?>');$('#renew_auth__auth_type_id').trigger('change');">Renew</button>
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
                                            $auth->activity->name,
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
                                            $auth->activity->name,
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
    <?php } ?>
    <?php if (!empty($member->officers)) {

    $currentOffices = [];
    $upcomingOffices = [];
    $previousOffices = [];
    $exp_date = Date::now();
    foreach ($member->officers as $officer) {
        if ($officer->start_on > Date::now()) {
            $upcomingOffices[] = $officer;
        } elseif ($officer->expires_on < $exp_date) {
            $previousOffices[] = $officer;
        } else {
            $currentOffices[] = $officer;
        }
    }
    // sort $active by start_on
    usort($currentOffices, function ($a, $b) {
        return $a->start_on <=> $b->start_on;
    });
    usort($upcomingOffices, function ($a, $b) {
        return $a->start_on <=> $b->start_on;
    });
    // sort $inactive by expires_on
    usort($previousOffices, function ($a, $b) {
        return $a->expires_on <=> $b->expires_on;
    });
?>
    <div class="related">
        <h4><?= __("Offices") ?></h4>

        <nav>
            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                <button class="nav-link active" id="nav-current-officer-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-current-officer" type="button" role="tab" aria-controls="nav-current-officer"
                    aria-selected="true">Current</button>
                <button class="nav-link" id="nav-upcoming-officer-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-upcoming-officer" type="button" role="tab" aria-controls="nav-upcoming-officer"
                    aria-selected="false">Upcoming</button>
                <button class="nav-link" id="nav-previous-officer-tab" data-bs-toggle="tab"
                    data-bs-target="#nav-previous-officer" type="button" role="tab" aria-controls="nav-previous-officer"
                    aria-selected="false">Previous</button>
            </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active" id="nav-current-officer" role="tabpanel"
                aria-labelledby="nav-current-officer-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Office") ?></th>
                            <th scope="col"><?= __("Branch") ?></th>
                            <th scope="col"><?= __("Start Date") ?></th>
                            <th scope="col"><?= __("End Date") ?></th>
                            <?php if ($user->can("view", "Offices")) { ?>
                            <th scope="col" class="actions"><?= __(
                                                                    "Actions",
                                                                ) ?></th>
                            <?php } ?>
                        </tr>
                        <?php foreach ($currentOffices as $office) : ?>
                        <tr>
                            <td><?= h($office->office->name) ?></td>
                            <td><?= h($office->branch->name) ?></td>
                            <td><?= h($office->start_on) ?></td>
                            <td><?= h($office->expires_on) ?></td>
                            <?php if (
                                    $user->can(
                                        "view",
                                        $office->office,
                                    )
                                ) { ?>
                            <td class="actions">
                                <?= $this->Html->link(
                                            __("View"),
                                            [
                                                "controller" => "Offices",
                                                "action" => "view",
                                                $office->office_id,
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
            <div class="tab-pane fade" id="nav-upcoming-officer" role="tabpanel"
                aria-labelledby="nav-upcoming-officer-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Office") ?></th>
                            <th scope="col"><?= __("Branch") ?></th>
                            <th scope="col"><?= __("Start Date") ?></th>
                            <th scope="col"><?= __("End Date") ?></th>
                            <?php if ($user->can("view", "Offices")) { ?>
                            <th scope="col" class="actions"><?= __(
                                                                    "Actions",
                                                                ) ?></th>
                            <?php } ?>
                        </tr>
                        <?php foreach ($upcomingOffices as $office) : ?>
                        <tr>
                            <td><?= h($office->office->name) ?></td>
                            <td><?= h($office->branch->name) ?></td>
                            <td><?= h($office->start_on) ?></td>
                            <td><?= h($office->expires_on) ?></td>
                            <?php if (
                                    $user->can(
                                        "view",
                                        $office->office,
                                    )
                                ) { ?>
                            <td class="actions">
                                <?= $this->Html->link(
                                            __("View"),
                                            [
                                                "controller" => "Offices",
                                                "action" => "view",
                                                $office->office_id,
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
            <div class="tab-pane fade" id="nav-previous-officer" role="tabpanel"
                aria-labelledby="nav-previous-officer-tab" tabindex="0">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <tr>
                            <th scope="col"><?= __("Office") ?></th>
                            <th scope="col"><?= __("Branch") ?></th>
                            <th scope="col"><?= __("Start Date") ?></th>
                            <th scope="col"><?= __("End Date") ?></th>
                            <?php if ($user->can("view", "Offices")) { ?>
                            <th scope="col" class="actions"><?= __(
                                                                    "Actions",
                                                                ) ?></th>
                            <?php } ?>
                        </tr>
                        <?php foreach ($previousOffices as $office) : ?>
                        <tr>
                            <td><?= h($office->office->name) ?></td>
                            <td><?= h($office->branch->name) ?></td>
                            <td><?= h($office->start_on) ?></td>
                            <td><?= h($office->expires_on) ?></td>
                            <?php if (
                                    $user->can(
                                        "view",
                                        $office->office,
                                    )
                                ) { ?>
                            <td class="actions">
                                <?= $this->Html->link(
                                            __("View"),
                                            [
                                                "controller" => "Offices",
                                                "action" => "view",
                                                $office->office_id,
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
        </div>
    </div>
</div>
<?php } ?>
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