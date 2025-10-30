<?php

/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\ActivityGroup $authorizationGroup
 */
?>
<?php $this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Add Award';
$this->KMP->endBlock(); ?>

<div class="activityGroup form content">
    <?= $this->Form->create(
        $award,
        [
            'data-controller' => 'awards-award-form',
        ]
    ) ?>
    <fieldset>
        <legend><a href="#" onclick="window.history.back(); return false;" class="bi bi-arrow-left-circle"></a>
            <?= __('Add Award') ?></legend>
        <?php
        echo $this->Form->control('name');
        echo $this->Form->control('abbreviation');
        echo $this->Form->hidden('specialties', ['id' => 'specialties', 'data-awards-award-form-target' => 'formValue']); ?>

        <div class="mb-3 form-group specialties">
            <label class="form-label" for="specialtyInput">Specialties</label>

            <div data-awards-award-form-target='displayList' class="mb-3"></div>
            <div class="input-group">
                <input type="text" data-awards-award-form-target="new" class="form-control" id="specialtyInput"
                    placeholder="Add Specialty">
                <button type="button" class="btn btn-primary btn-sm" data-action="awards-award-form#add"
                    id="addSpecialty">Add</button>
            </div>
        </div>
        <?php
        echo $this->Form->control('description');
        echo $this->Form->control('insignia');
        echo $this->Form->control('badge');
        echo $this->Form->control('charter');
        echo $this->Form->control('domain_id', ['options' => $awardsDomains]);
        echo $this->Form->control('level_id', ['options' => $awardsLevels]);
        echo $this->Form->control('branch_id', ['options' => $branches]);
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>