<?php
declare(strict_types=1);

namespace App\Model\Table;

class GatheringActivityAssignmentPermissionsTable extends BaseTable
{
    /**
     * @param array $config Table configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('gathering_activity_assignment_permissions');
        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->belongsTo('GatheringActivities');
        $this->belongsTo('Permissions');
    }
}
