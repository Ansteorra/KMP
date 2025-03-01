<?php $this->extend('/layout/TwitterBootstrap/dashboard');

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Create Officers Warrant Roster';
$this->KMP->endBlock();


?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Create Officer Roster for Warranting
        </h3>
    </div>
</div>

<div class="row" data-controller="officer-roster-search">
    <?= $this->Form->create(null, ['align' => 'inline', "type" => "get", 'url' => ['controller' => 'rosters', 'action' => 'add']]); ?>
    <?= $this->Form->control('warrantPeriod', [
        'id' => 'warrantPeriod',
        'type' => 'select',
        'options' => $warrantPeriods,
        'value' => $warrantPeriod,
        'data-officer-roster-search-target' => 'warrantPeriods',
        'data-action' => 'officer-roster-search#checkEnable',
    ]); ?>

    <?= $this->Form->control('department', [
        'id' => 'department',
        'type' => 'select',
        'options' => $departmentList,
        'value' => $department,
        'data-officer-roster-search-target' => 'departments',
        'data-action' => 'officer-roster-search#checkEnable',
    ]); ?>
    <?= $this->Form->button(__('Show'), ['class' => 'btn-primary', 'data-officer-roster-search-target' => 'showBtn', 'disabled' => 'disabled']) ?>
    <?= $this->Form->end() ?>
</div>
<div class="row"> <?php foreach ($departmentsData as $data) :
                        //check to see if there are any members to show
                    ?>

    <?php if (count($data->dept_officers) > 0) : ?>
    <div class="row mb-3">
        <div class="col-12 text-end">
            <?php echo $this->Form->create(null, [
                                "id" => "createRoster",
                                "url" => [
                                    "controller" => "rosters",
                                    "action" => "createRoster",
                                ],
                            ]);
                    ?>
            <input type="hidden" name="department" value="<?= $data->id ?>">
            <input type="hidden" name="warrantPeriod" value="<?= $warrantPeriod ?>">
            <button type="submit" class="btn btn-primary btn-sm roster-submit-btn" data-controller="outlet-btn"
                data-outlet-btn-require-data-value="false" data-action="click->outlet-btn#fireNotice">Submit</button>
            </form>
        </div>
    </div>
    <?php if ($data->hasDanger) : ?>
    <div class="alert alert-danger" role="alert">
        <?= __("Warrants will not be created for users with issues") ?>
    </div>
    <?php endif; ?>
    <div data-controller='officer-roster-table' data-officer-roster-table-outlet-btn-outlet=".roster-submit-btn"
        data-officer-roster-table-check-list-outlet=".roster-check-list">
        <table class="table table-striped table-condensed">
            <tr scope="row">

                <th scope="col"></th>
                <th class="col">Branch</th>
                <th class="col">Office</th>
                <th class="col">Office End Date</th>
                <th class="col">SCA Name</th>
                <th class="col">Email Address</th>
                <th class="col">Phone Number</th>
                <th class="col">Membership exp</th>
                <th class="col">Warrantable</th>
                <th class="col">Warrant Start</th>
                <th class="col">Warrant End</th>
            </tr>
            <?php foreach ($data->dept_officers  as $officer) : ?>
            <?php if ($officer->danger): ?>
            <tr scope="row" class="danger">
                <td></td>
                <?php else: ?>
            <tr scope="row">
                <td><input type="checkbox" name="check_list[]" value=<?= h($officer->id) ?> form="createRoster"
                        data-action="officer-roster-table#rowChecked" checked
                        data-officer-roster-table-target="rowCheckbox">
                </td>
                <?php endif; ?>
                <td><?= h($officer->branch->name) ?></td>
                <td><?= h($officer->office->name) ?><?= $officer->deputy_description != null ? ": " . $officer->deputy_description : "" ?>
                </td>
                <td><?= h($officer->expires_on_to_string) ?></td>
                <td><?= h($officer->member->sca_name) ?></td>
                <td><?= h($officer->member->email_address) ?></td>
                <td><?= h($officer->member->phone_number) ?></td>
                <td>
                    <?= $officer->member->membership_expires_on ? h($officer->member->membership_expires_on_to_string) : "N/A" ?>
                </td>
                <td>
                    <?php if (!$officer->member->warrantable) : ?>
                    <?= $this->Kmp->bool(
                                        $officer->member->warrantable,
                                        $this->Html,
                                        ['data-bs-toggle' => "tooltip", 'data-bs-title' => $officer->warrant_message],
                                    ) ?></td>
                <?php else : ?>
                <?= $this->Kmp->bool(
                                        $officer->member->warrantable,
                                        $this->Html,
                                    ) ?>
                <?php endif; ?>
                <td>
                    <?= $officer->new_warrant_start_date ? h($officer->new_warrant_start_date->toDateString()) : "N/A" ?>
                    <?php if (count($officer->start_date_message) > 0) : ?>
                    <span class="badge rounded-pill text-bg-warning" data-bs-toggle="tooltip"
                        data-bs-title="<?= implode(', ', $officer->start_date_message) ?>">!</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?= $officer->new_warrant_exp_date ? h($officer->new_warrant_exp_date->toDateString()) : "N/A" ?>
                    <?php if (count($officer->end_date_message) > 0) : ?>
                    <span class="badge rounded-pill text-bg-warning" data-bs-toggle="tooltip"
                        data-bs-title="<?= implode(', ', $officer->end_date_message) ?>">!</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php else : ?>
    <div class="alert alert-info" role="alert">
        <?= __("No members found for this department") ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php $this->KMP->endBlock(); ?>