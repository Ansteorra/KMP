<?php $this->extend('/layout/TwitterBootstrap/dashboard');

use Cake\I18n\Date;

$compareDate = new Date($validOn)
?>

<div class="row">
    <?= $this->Form->create(null, ['align' => 'inline', "type" => "get", 'url' => ['controller' => 'reports', 'action' => 'ActivityWarrantsRoster']]); ?>
    <?= $this->Form->control('validOn', ['default' => $validOn, 'type' => 'date', 'placeholder' => 'Valid On']) ?>
    <?php if ($hide) {
        echo $this->Form->control('hide', ['type' => 'checkbox', 'checked' => 'checked', 'switch' => true, 'label' => 'Hide Expired']);
    } else {
        echo $this->Form->control('hide', ['type' => 'checkbox', 'switch' => true, 'label' => 'Hide Expired']);
    } ?>
    <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
    <?= $this->Form->end() ?>
</div>
<div class="row"> <?php foreach ($roles as $role) {
                        //check to see if there are any members to show
                        $show = false;
                        foreach ($role->member_roles as $assignment) {
                            if ($assignment->member->membership_expires_on < $compareDate && !$hide) {
                                $show = true;
                            } elseif ($assignment->member->membership_expires_on > $compareDate) {
                                $show = true;
                            }
                        }
                    ?>
    <h5><?= h($role->name) ?></h5>
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
        <?php foreach ($role->member_roles as $assignment) : ?>
        <?php if ($assignment->member->membership_expires_on < $compareDate  && !$hide) : ?>
        <tr scope="row" class="danger">
            <?php elseif ($assignment->member->membership_expires_on > $compareDate) : ?>
        <tr scope="row">
            <?php endif; ?>
            <?php if (($assignment->member->membership_expires_on < $compareDate  && !$hide) || ($assignment->member->membership_expires_on > $compareDate)) : ?>
            <td><?= h($assignment->member->branch->name) ?></td>
            <td><?= h($assignment->member->sca_name) ?></td>
            <td><?= h($assignment->member->first_name) ?> <?= h($assignment->member->last_name) ?></td>
            <td><?= h($assignment->member->email_address) ?></td>
            <td><?= h($assignment->member->phone_number) ?></td>
            <td><?= h($assignment->member->street_address) ?>, <?= h($assignment->member->city) ?>,
                <?= h($assignment->member->state) ?> <?= h($assignment->member->zip) ?> </td>
            <td><?= h($assignment->member->membership_number) ?></td>
            <td><?= h($assignment->member->membership_expires_on) ?></td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php } else { ?>
        <p>No members assigned to this role</p>
        <?php } ?>
    </table>
    <?php } ?>
</div>

<?php $this->end(); ?>