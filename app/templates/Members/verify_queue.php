<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member[]|\Cake\Collection\CollectionInterface $Members
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");


echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': New Members Verifcation Queue';
$this->KMP->endBlock();

$pendingYouth = [];
$pendingWithCard = [];
$pendingWithoutCard = [];

// put all members under 18 in the $pendingYouth array
foreach ($Members as $Member) {
    if ($Member->age < 18 && ($Member->status == "unverified minor" || $Member->status == "< 18 member verified")) {
        $pendingYouth[] = $Member;
    } elseif ($Member->membership_card_path != null && strlen($Member->membership_card_path) > 0) {
        $pendingWithCard[] = $Member;
    } else {
        $pendingWithoutCard[] = $Member;
    }
}

?>
<h3>
    Verification Queue
</h3>
<div data-controller="detail-tabs">
    <nav>
        <div class="nav nav-tabs" id="nav-tab" role="tablist">
            <button class="nav-link" id="nav-youth-tab-btn" data-bs-toggle="tab" data-bs-target="#nav-youth-tab"
                type="button" role="tab" aria-controls="nav-youth-tab" aria-selected=" true"
                data-detail-tabs-target='tabBtn' data-detail-tabs-target='tabBtn'>Youth
                <?php if (count($pendingYouth) > 0) { ?>
                    <span class="badge bg-danger"><?= count($pendingYouth) ?></span>
                <?php } ?>
            </button>
            <button class="nav-link" id="nav-pendingCard-tab-btn" data-bs-toggle="tab"
                data-bs-target="#nav-pendingCard-tab" type="button" role="tab" aria-controls="#nav-pendingCard-tab"
                aria-selected="false" data-detail-tabs-target='tabBtn' data-detail-tabs-target='tabBtn'>Unverified With
                Card
                <?php if (count($pendingWithCard) > 0) { ?>
                    <span class="badge bg-danger"><?= count($pendingWithCard) ?></span>
                <?php } ?>
            </button>
            <button class="nav-link" id="nav-pendingNoCard-btn" data-bs-toggle="tab"
                data-bs-target="#nav-pendingNoCard-tab" type="button" role="tab" aria-controls="nav-pendingNoCard-tab"
                aria-selected="false" data-detail-tabs-target='tabBtn' data-detail-tabs-target='tabBtn'>Unverified
                Without
                Card</button>
        </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <div class="tab-pane fade" id="nav-youth-tab" role="tabpanel" aria-labelledby="nav-youth-tab-btn" tabindex=" 0"
            data-detail-tabs-target="tabContent">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Status</th>
                            <th scope="col">SCA Name</th>
                            <th scope="col">Branch</th>
                            <th scope="col">First Name</th>
                            <th scope="col">Last Name</th>
                            <th scope="col">Email Address</th>
                            <th scope="col" class="text-center">Card</th>
                            <th scope="col" class="actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingYouth as $Member) : ?>
                            <tr>

                                <td><?= h($Member->status) ?></td>
                                <td><?= h($Member->sca_name) ?></td>
                                <td><?= h($Member->branch->name) ?></td>
                                <td><?= h($Member->first_name) ?></td>
                                <td><?= h($Member->last_name) ?></td>
                                <td><?= h($Member->email_address) ?></td>
                                <td class="text-center fs-4 align-top">
                                    <?php if ($Member->membership_card_path != null && strlen($Member->membership_card_path) > 0) {
                                        echo $this->Html->icon("card-heading");
                                    } ?>
                                </td>
                                <td class="actions text-end text-nowrap">
                                    <?= $this->Html->link(
                                        __(""),
                                        ["action" => "view", $Member->id],
                                        ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="nav-pendingCard-tab" role="tabpanel" aria-labelledby="nav-pendingCard-tab-btn"
            tabindex=" 1" data-detail-tabs-target="tabContent">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Status</th>
                            <th scope="col">SCA Name</th>
                            <th scope="col">Branch</th>
                            <th scope="col">First Name</th>
                            <th scope="col">Last Name</th>
                            <th scope="col">Email Address</th>
                            <th scope="col" class="text-center">Card</th>
                            <th scope="col" class="actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingWithCard as $Member) : ?>
                            <tr>

                                <td><?= h($Member->status) ?></td>
                                <td><?= h($Member->sca_name) ?></td>
                                <td><?= h($Member->branch->name) ?></td>
                                <td><?= h($Member->first_name) ?></td>
                                <td><?= h($Member->last_name) ?></td>
                                <td><?= h($Member->email_address) ?></td>
                                <td class="text-center fs-4 align-top">
                                    <?php if ($Member->membership_card_path != null && strlen($Member->membership_card_path) > 0) {
                                        echo $this->Html->icon("card-heading");
                                    } ?>
                                </td>
                                <td class="actions text-end text-nowrap">
                                    <?= $this->Html->link(
                                        __(""),
                                        ["action" => "view", $Member->id],
                                        ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="tab-pane fade" id="nav-pendingNoCard-tab" role="tabpanel"
            aria-labelledby="nav-pendingNoCard-tab-btn" tabindex="2" data-detail-tabs-target="tabContent">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Status</th>
                            <th scope="col">SCA Name</th>
                            <th scope="col">Branch</th>
                            <th scope="col">First Name</th>
                            <th scope="col">Last Name</th>
                            <th scope="col">Email Address</th>
                            <th scope="col" class="text-center">Card</th>
                            <th scope="col" class="actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingWithoutCard as $Member) : ?>
                            <tr>

                                <td><?= h($Member->status) ?></td>
                                <td><?= h($Member->sca_name) ?></td>
                                <td><?= h($Member->branch->name) ?></td>
                                <td><?= h($Member->first_name) ?></td>
                                <td><?= h($Member->last_name) ?></td>
                                <td><?= h($Member->email_address) ?></td>
                                <td class="text-center fs-4 align-top">
                                    <?php if ($Member->membership_card_path != null && strlen($Member->membership_card_path) > 0) {
                                        echo $this->Html->icon("card-heading");
                                    } ?>
                                </td>
                                <td class="actions text-end text-nowrap">
                                    <?= $this->Html->link(
                                        __(""),
                                        ["action" => "view", $Member->id],
                                        ["title" => __("View"), "class" => "btn-sm btn btn-secondary bi bi-binoculars-fill"],
                                    ) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>