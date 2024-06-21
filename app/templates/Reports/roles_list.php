<?php $this->extend('/layout/TwitterBootstrap/dashboard');


echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle", "KMP") . ': Role Assignments Report';
$this->KMP->endBlock();


use Cake\I18n\Date;


$compareDate = new Date($validOn)

?>
<h3>Role Assignments</h3>
<div class="row mb-4">
    <?= $this->Form->create(null, ['align' => 'inline', "type" => "get", 'url' => ['controller' => 'reports', 'action' => 'rolesList']]); ?>
    <?= $this->Form->control('validOn', ['default' => $validOn, 'type' => 'date', 'placeholder' => 'Valid On']) ?>
    <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
    <?= $this->Form->end() ?>
</div>
<div class="row">
    <?php foreach ($roles as $role) : ?>
    <h5><?= h($role->name) ?></h5>
    <?php if ($role->member_roles) : ?>
    <table class="table table-striped table-condensed">
        <tr scope="row">
            <th class="col-1">Branch</th>
            <th class="col-2">Name</th>
            <th class="col-2">Membership #</th>
            <th class="col-2">Membership exp</th>
            <th class="col-1">Started on</th>
            <th class="col-1">Expires on</th>
            <th class="col-2">Approved by</th>
        </tr>
        <?php foreach ($role->member_roles as $assignment) {
                    $memberExpDate = $assignment->member->membership_expires_on ? $assignment->member->membership_expires_on->format('m/d/y') : "";
                    $roleExpDate = $assignment->expires_on ? $assignment->expires_on->format('m/d/y') : "";
                    $memExpired = $assignment->member->membership_expires_on < $compareDate;
                ?>
        <?php if ($memExpired) : ?>
        <tr scope="row" class="danger">
            <?php else : ?>
        <tr scope="row">
            <?php endif; ?>
            <td><?= h($assignment->member->branch->name) ?></td>
            <td><?= h($assignment->member->sca_name) ?></td>
            <td><?= h($assignment->member->membership_number) ?></td>
            <td><?= h($memberExpDate) ?></td>
            <td><?= h($assignment->start_on->format("m/d/y")) ?></td>
            <td><?= h($roleExpDate) ?></td>
            <td><?= h($assignment->approved_by->sca_name) ?></td>
        </tr>
        <?php } ?>
    </table>
    <?php else : ?>
    <p>No members assigned to this role</p>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php $this->KMP->endBlock(); ?>