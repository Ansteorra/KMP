<?php
declare(strict_types=1);

namespace App\Model\Entity;

/** Assignment rule joining a gathering activity to an existing scoped permission. */
class GatheringActivityAssignmentPermission extends BaseEntity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'gathering_activity_id' => true,
        'permission_id' => true,
    ];
}
