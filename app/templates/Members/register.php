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
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': New Member Register';
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
                echo $this->Form->control("sca_name");
                echo $this->Form->control("branch_id", [
                    "options" => $treeList,
                    "required" => true,
                ]);
                echo $this->Form->control("first_name", ["required" => true]);
                echo $this->Form->control("middle_name");
                echo $this->Form->control("last_name", ["required" => true]);
                echo $this->Form->control("street_address");
                echo $this->Form->control("city");
                echo $this->Form->control("state");
                echo $this->Form->control("zip");
                echo $this->Form->control("phone_number");
                echo $this->Form->control("email_address", [
                    "required" => true,
                    "type" => "email",
                    "nestedInput" => true,
                    "labelOptions" => ["class" => "input-group-text"],
                ]);
                ?>
                <div class="mb-3 form-group select row">
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
                <div class="row" id="upload-images">
                </div>
            </fieldset>
            <?= $this->Form->button(__("Submit")) ?>
            <?= $this->Form->end() ?>
        </div>
    </div>
</div>


<?php

echo $this->KMP->startBlock("script");
?>
<script>
class memberRegister {
    constructor($) {
        this.$ = $;
    };
    //onInput for Autocomplete
    run(rootPath) {
        var me = this;
        me.$(function() {
            //$("#upload-images").laiImagePreview();
            me.$("#upload-images").laiImagePreview({
                columns: "col-sm-6 col-md-3",
                inputFileName: "member_card",
                imageCaption: false,
                imageLimit: 1,
                label: "Picture of Membership Card (Optional)",
                maxFileSize: 2000000,
            });
        });
    }
}
window.addEventListener('DOMContentLoaded', function() {
    var pageControl = new memberRegister(window.$);
    pageControl.run();
});
</script>
<?php echo $this->KMP->endBlock(); ?>