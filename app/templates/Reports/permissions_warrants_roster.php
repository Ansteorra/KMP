<?php $this->extend('/layout/TwitterBootstrap/dashboard');

use Cake\I18n\Date;

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Permissions Warrant Roster Report';
$this->KMP->endBlock();

$compareDate = new Date($validOn)
?>

<div class="row">
    <?= $this->Form->create(null, ['align' => 'inline', "type" => "get", 'url' => ['controller' => 'reports', 'action' => 'PermissionsWarrantsRoster']]); ?>
    <?= $this->Form->control('validOn', ['default' => $validOn, 'type' => 'date', 'placeholder' => 'Valid On']) ?>
    <?php if ($hide) {
        echo $this->Form->control('hide', ['type' => 'checkbox', 'checked' => 'checked', 'switch' => true, 'label' => 'Hide Expired']);
    } else {
        echo $this->Form->control('hide', ['type' => 'checkbox', 'switch' => true, 'label' => 'Hide Expired']);
    } ?>
    <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
    <?= $this->Form->end() ?>
</div>
<div class="row"> <?php foreach ($permissionsRoster as $permissionName => $users) {
                        //check to see if there are any members to show
                        $show = false;
                        foreach ($users as $assignment) {
                            if ($assignment->membership_expires_on < $compareDate && !$hide) {
                                $show = true;
                            } elseif ($assignment->membership_expires_on > $compareDate) {
                                $show = true;
                            }
                        }
                    ?>
        <h5><?= h($permissionName) ?></h5>
        <?php if ($show) { ?>
            <table class="table table-striped table-condensed">
                <tr scope="row">
                    <th class="col-1">Branch</th>
                    <th class="col-1">SCA Name</th>
                    <th class="col-1">Name</th>
                    <th class="col-2">Email Address</th>
                    <th class="col-1">Phone Number</th>
                    <th class="col-3">Address</th>
                    <th class="col-1">Membership #</th>
                    <th class="col-1">Membership exp</th>
                </tr>
                <?php foreach ($users as $assignment) : ?>
                    <?php if ($assignment->membership_expires_on < $compareDate  && !$hide) : ?>
                        <tr scope="row" class="danger">
                        <?php elseif ($assignment->membership_expires_on > $compareDate) : ?>
                        <tr scope="row">
                        <?php endif; ?>
                        <?php if (($assignment->membership_expires_on < $compareDate  && !$hide) || ($assignment->membership_expires_on > $compareDate)) : ?>
                            <td><?= h($assignment->branch->name) ?></td>
                            <td><?= h($assignment->sca_name) ?></td>
                            <td><?= h($assignment->first_name) ?> <?= h($assignment->last_name) ?></td>
                            <td><?= h($assignment->email_address) ?></td>
                            <td><?= h($assignment->phone_number) ?></td>
                            <td><?= h($assignment->street_address) ?>, <?= h($assignment->city) ?>,
                                <?= h($assignment->state) ?> <?= h($assignment->zip) ?> </td>
                            <td><?= h($assignment->membership_number) ?></td>
                            <td><?= h($assignment->membership_expires_on) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php } else { ?>
                <p>No members assigned to this role</p>
            <?php } ?>
            </table>
        <?php } ?>
</div>

<?php $this->KMP->endBlock(); ?>