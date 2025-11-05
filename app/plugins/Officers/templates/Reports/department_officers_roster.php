<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Officers Roster Report';
$this->KMP->endBlock();

use Cake\I18n\Date;

$compareDate = new Date($validOn)
?>

<div class="row">
    <?= $this->Form->create(null, ['align' => 'inline', "type" => "get", 'url' => ['controller' => 'reports', 'action' => 'DepartmentOfficersRoster']]); ?>
    <?= $this->Form->control('validOn', ['default' => $validOn, 'type' => 'date', 'placeholder' => 'Valid On']) ?>
    <?php if ($hide) {
        echo $this->Form->control('hide', ['type' => 'checkbox', 'checked' => 'checked', 'switch' => true, 'label' => 'Hide Expired']);
    } else {
        echo $this->Form->control('hide', ['type' => 'checkbox', 'switch' => true, 'label' => 'Hide Expired']);
    } ?>
    <?php if ($warrantOnly) {
        echo $this->Form->control('warranted', ['type' => 'checkbox', 'checked' => 'checked', 'switch' => true, 'label' => 'Warranted Only']);
    } else {
        echo $this->Form->control('warranted', ['type' => 'checkbox', 'switch' => true, 'label' => 'Warranted Only']);
    } ?>
    <?= $this->Form->control('departments', [
        'id' => 'reportsDepartmentsList',
        'type' => 'select',
        'multiple' => 'checkbox',
        'options' => $departmentList,
        'switch' => true,
        'value' => $departments,
        'container' => ['class' => 'bg-secondary-subtle rounded px-2 pt-1 align-middle', "style" => "display: block !important;"],
    ]); ?>
    <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
    <?= $this->Form->end() ?>
</div>
<div class="row"> <?php foreach ($departmentsData as $data) {
                        //check to see if there are any members to show
                        $show = false;
                        foreach ($data->dept_officers as $officer) {
                            if ($officer->member->membership_expires_on < $compareDate && !$hide) {
                                $show = true;
                            } elseif ($officer->member->membership_expires_on > $compareDate) {
                                $show = true;
                            }
                        }
                    ?>
        <h5><?= h($data->name) ?></h5>
        <?php if ($show) { ?>
            <table class="table table-striped table-condensed">
                <tr scope="row">
                    <th class="col-1">Branch</th>
                    <th class="col-1">Office</th>
                    <th class="col-1">Office End Date</th>
                    <th class="col-1">SCA Name</th>
                    <th class="col-1">Name</th>
                    <th class="col-1">Email Address</th>
                    <th class="col-1">Phone Number</th>
                    <th class="col-1">Address</th>
                    <th class="col-1">Membership #</th>
                    <th class="col-1">Membership exp</th>
                    <th class="col-1">Warrantable</th>
                    <th class="col-1">Current Warrant</th>
                </tr>
                <?php foreach ($data->dept_officers  as $officer) : ?>
                    <?php if (($officer->member->membership_expires_on < $compareDate
                                    || ($officer->office->requires_warrant && (
                                        !$officer->member->warrantable
                                        || $officer->current_warrant == null
                                        || $officer->current_warrant->expires_on->toNative() > $officer->member->membership_expires_on->toNative())))  && !$hide) : ?>
                        <tr scope="row" class="danger">
                        <?php elseif ($officer->member->membership_expires_on > $compareDate) : ?>
                        <tr scope="row">
                        <?php endif; ?>
                        <?php if (($officer->member->membership_expires_on < $compareDate  && !$hide) || ($officer->member->membership_expires_on > $compareDate)) : ?>
                            <td><?= h($officer->branch->name) ?></td>
                            <td><?= h($officer->office->name) ?>
                            <td><?= $this->Timezone->format($officer->expires_on, null, 'M d, Y') ?>
                                <?= $officer->deputy_description != null ? ": " . $officer->deputy_description : "" ?></td>
                            <td><?= h($officer->member->sca_name) ?></td>
                            <td><?= h($officer->member->first_name) ?> <?= h($officer->member->last_name) ?></td>
                            <td><?= h($officer->member->email_address) ?></td>
                            <td><?= h($officer->member->phone_number) ?></td>
                            <td><?= h($officer->member->street_address) ?>, <?= h($officer->member->city) ?>,
                                <?= h($officer->member->state) ?> <?= h($officer->member->zip) ?> </td>
                            <td><?= h($officer->member->membership_number) ?></td>
                            <td>
                                <?= $officer->member->membership_expires_on ? $this->Timezone->format($officer->member->membership_expires_on, null, 'M d, Y') : "N/A" ?>
                                <?php if ($officer->member->membership_expires_on < $compareDate) : ?>
                                    <span class="badge rounded-pill text-bg-warning" data-bs-toggle="tooltip"
                                        data-bs-title="Member will be expired by this date!">!</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$officer->member->warrantable) :
                                        $reasons = implode(' ,', $officer->member->getNonWarrantableReasons()); ?>
                                    <?= $this->Kmp->bool(
                                            $officer->member->warrantable,
                                            $this->Html,
                                            ['data-bs-toggle' => "tooltip", 'data-bs-title' => $reasons],
                                        ) ?>
                        <?php else : ?>
                            <?= $this->Kmp->bool(
                                            $officer->member->warrantable,
                                            $this->Html,
                                        ) ?>
                        <?php endif; ?>
                            </td>
                        <td>

                            <?= $officer->current_warrant ? $this->Timezone->format($officer->current_warrant->expires_on, null, 'M d, Y') : "No Warrant" ?>
                            <?php if (($officer->office->requires_warrant && (
                                        !$officer->member->warrantable
                                        || $officer->current_warrant == null
                                        || $officer->current_warrant->expires_on->toNative() > $officer->member->membership_expires_on->toNative()))) :
                                        $message = [];
                                        if (!$officer->member->warrantable) :
                                            $message[] = "Not Warrantable";
                                        elseif ($officer->current_warrant == null) :
                                            $message[] = "Warrant Required";
                                        elseif ($officer->current_warrant->expires_on->toNative() > $officer->member->membership_expires_on->toNative()) :
                                            $message[] = "Membership Expires Before Warrant";
                                        endif;
                            ?>
                                <span class="badge rounded-pill text-bg-warning" data-bs-toggle="tooltip"
                                    data-bs-title="<?= implode(' ,', $message) ?>">!</span>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php } else { ?>
                <p>No members assigned to this Department</p>
            <?php } ?>
            </table>
        <?php } ?>
</div>

<?php $this->KMP->endBlock(); ?>