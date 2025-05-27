<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Member $Member
 * @var \App\Model\Entity\MemberActivity[]|\Cake\Collection\CollectionInterface $MemberActivities
 * @var \App\Model\Entity\PendingAuthorization[]|\Cake\Collection\CollectionInterface $pendingAuthorizations
 * @var \App\Model\Entity\Role[]|\Cake\Collection\CollectionInterface $roles
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/register");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': New Member Register';
$this->KMP->endBlock(); ?>
<div class="container-sm">
    <?= $this->Form->create($member, ["type" => "file"]) ?>
    <div class="card mb-3">
        <div class="card-body">

            <fieldset>
                <div class="text-center mt-3"><?= $this->Html->image($headerImage, [
                                                    "alt" => "site logo",
                                                    'class' => "img-fluid w-25"
                                                ]) ?></div>
                <legend class="text-center">
                    <h5 class="card-title"><?= __("Register") ?></h5>
                </legend>
                <?php
                echo $this->Form->control("title");
                echo $this->Form->control("sca_name");
                echo $this->Form->control("pronunciation");
                echo $this->Form->control("pronouns");
                echo $this->KMP->comboBoxControl(
                    $this->Form,
                    'branch_name',
                    'branch_id',
                    $treeList,
                    "Branch",
                    true,
                    false,
                    []
                );
                echo $this->Form->control("first_name", ["required" => true]);
                echo $this->Form->control("middle_name");
                echo $this->Form->control("last_name", ["required" => true]);
                echo $this->Form->control("street_address");
                echo $this->Form->control("city");
                echo $this->Form->control("state");
                echo $this->Form->control("zip");
                echo $this->Form->control("phone_number");
                echo $this->Form->control("email_address", [
                    'type' => 'email',
                    "required" => true,
                    "type" => "email",
                    "nestedInput" => true,
                    "labelOptions" => ["class" => "input-group-text"],
                    'data-controller' => 'member-unique-email',
                    'data-member-unique-email-url-value' => $this->URL->build([
                        'controller' => 'Members',
                        'action' => 'emailTaken',
                        'plugin' => null,
                    ]),
                ]);
                ?>
                <div class="mb-3 form-group select required row">
                    <label class="form-label" for="birth-month">Birth Date</label>
                    <div class="col-2">
                        <select name="birth_month" id="birth-month" class="form-select" required="required">
                            <option value=""></option>
                            <?php foreach ($months as $index => $value) : ?>
                            <option value="<?= $index ?>"><?= $value ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-2">
                        <select name="birth_year" id="birth-year" class="form-select" required="required">
                            <option value=""></option>
                            <?php foreach ($years as $index => $value) : ?>
                            <option value="<?= $index ?>"><?= $value ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3 form-group">
                    <label class="form-label">Upload Membership Card (optional)</label>
                    <div class="card col-3" data-controller="image-preview">
                        <div class="card-body text-center">
                            <svg class="bi bi-card-image text-secondary text-center" width="200" height="200"
                                fill="currentColor" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"
                                data-image-preview-target="loading">
                                <path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0" />
                                <path
                                    d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2zm13 1a.5.5 0 0 1 .5.5v6l-3.775-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12v.54L1 12.5v-9a.5.5 0 0 1 .5-.5z" />
                            </svg>
                            <img src="#" hidden alt="Image Preview" class="w-100" data-image-preview-target="preview">
                        </div>
                        <div class="card-footer">
                            <input type="file" name="member_card" class="form-control" accept="image/*"
                                data-image-preview-target="file" data-action="change->image-preview#preview">
                        </div>
                    </div>
            </fieldset>
            <?= $this->Form->button(__("Submit"), ["class" => "btn-primary"]) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>