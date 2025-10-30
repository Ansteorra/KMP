<?php

/**
 * Gatherings Index - Tabbed Interface for Gathering Management
 *
 * This view provides a tabbed interface for viewing gatherings filtered by
 * temporal state using Turbo Frames for efficient content loading.
 *
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\User $user
 */
?>
<?php
$this->extend("/layout/TwitterBootstrap/dashboard");

echo $this->KMP->startBlock("title");
echo $this->KMP->getAppSetting("KMP.ShortSiteTitle") . ': Gatherings';
$this->KMP->endBlock();
?>
<div class="row align-items-start">
    <div class="col">
        <h3>
            Gatherings
        </h3>
    </div>
    <div class="col text-end">
        <?= $this->Html->link(
            '<i class="bi bi-calendar-event"></i> Calendar View',
            ['action' => 'calendar'],
            ['class' => 'btn btn-info btn-sm', 'escape' => false]
        ) ?>
        <?php
        $gatheringsTable = \Cake\ORM\TableRegistry::getTableLocator()->get("Gatherings");
        $tempGathering = $gatheringsTable->newEmptyEntity();
        if ($branch_id) {
            $tempGathering->branch_id = $branch_id;
        }
        if ($user->checkCan("add", $tempGathering)) :
        ?>
            <?= $this->Html->link(
                ' Add Gathering',
                ['action' => 'add'],
                ['class' => 'btn btn-primary btn-sm bi bi-plus-circle', 'data-turbo-frame' => '_top']
            ) ?>
        <?php endif; ?>
    </div>
</div>

<?php
// Configure tabs for gatherings with temporal filtering
echo $this->element('turboActiveTabs', [
    'user' => $user,
    'tabGroupName' => "gatheringTabs",
    'tabs' => [
        "this_month" => [
            "label" => __("This Month"),
            "id" => "this-month-gatherings",
            "selected" => true,
            "turboUrl" => $this->URL->build([
                "controller" => "Gatherings",
                "action" => "allGatherings",
                "this_month"
            ])
        ],
        "next_month" => [
            "label" => __("Next Month"),
            "id" => "next-month-gatherings",
            "selected" => false,
            "turboUrl" => $this->URL->build([
                "controller" => "Gatherings",
                "action" => "allGatherings",
                "next_month"
            ])
        ],
        "future" => [
            "label" => __("Future"),
            "id" => "future-gatherings",
            "selected" => false,
            "turboUrl" => $this->URL->build([
                "controller" => "Gatherings",
                "action" => "allGatherings",
                "future"
            ])
        ],
        "previous" => [
            "label" => __("Previous"),
            "id" => "previous-gatherings",
            "selected" => false,
            "turboUrl" => $this->URL->build([
                "controller" => "Gatherings",
                "action" => "allGatherings",
                "previous"
            ])
        ]
    ]
]);
?>