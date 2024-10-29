<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Member Authorizations Report';
$this->KMP->endBlock(); ?>

<div class="row">
    <div class="col-lg-12">
        <h3>Authorizations</h3>
    </div>
</div>
<div class="row">
    <div class="col-lg-3 col-md-3 columns">
        <h4>Query</h4>
        <?= $this->Form->create(null, ["type" => "get", 'url' => ['controller' => 'reports', 'action' => 'authorizations']]); ?>
        <?= $this->Form->date('validOn', ['default' => $validOn, 'type' => 'date']) ?>
        <?= $this->Form->control('branches', ['options' => $branchesList]) ?>
        <?= $this->Form->control('activities', [
            'type' => 'select',
            'multiple' => 'checkbox',
            'options' => $activitiesList,
            'switch' => true,
            'value' => $activities,
        ]); ?>
        <?= $this->Form->button(__('Submit'), ['class' => 'btn-primary']) ?>
        <?= $this->Form->end() ?>
    </div>
    <div class="col-lg-9 col-md-9 columns">
        <h4>Results</h4>
        <dl>
            <dt>Authorized Members</dt>
            <dd><?= h($distincMemberCount) ?></dd>
            <?php foreach ($memberRollup as $auths) : ?>
                <dt><?= h($auths->auth) ?></dt>
                <dd><?= h($auths->count) ?></dd>
            <?php endforeach; ?>

            <? if ($memberListQuery) : ?>
                <?php $currentAuth = -1 ?>
                <?php foreach ($memberListQuery as $auth) : ?>
                    <?php if ($auth->activity_id != $currentAuth) : ?>
                        <?php if ($currentAuth != -1) : ?>
                            </table>
                        <?php endif; ?>
                        <?php $currentAuth = $auth->activity_id ?>
                        <h3><?= h($auth->activity->name) ?></h3>
                        <table class="table table-striped table-condensed">
                            <tr>
                                <th>SCA Name</th>
                                <th>Member Number</th>
                                <th>Branch</th>
                                <th>Start On</th>
                                <th>End Date</th>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td>
                                <?= $this->Html->link(
                                    h($auth->member->sca_name),
                                    ['plugin' => null, 'controller' => 'Members', 'action' => 'view', $auth->member->id]
                                ) ?>
                            </td>
                            <td><?= h($auth->member->membership_number ? $auth->member->membership_number : "Non Member") ?>
                            </td>
                            <td><?= h($auth->member->branch->name) ?></td>
                            <td><?= h($auth->start_on) ?></td>
                            <td><?= h($auth->expires_on) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($currentAuth != -1) : ?>
                        </table>
                    <?php endif; ?>
                <? endif; ?>



        </dl>
    </div>
</div>

<?php $this->KMP->endBlock(); ?>