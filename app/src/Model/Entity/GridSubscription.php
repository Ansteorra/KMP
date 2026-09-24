<?php
declare(strict_types=1);

namespace App\Model\Entity;

/** Tenant-local recurring grid summary owned by a single member. */
class GridSubscription extends BaseEntity
{
    protected array $_accessible = ['*' => false];
}
