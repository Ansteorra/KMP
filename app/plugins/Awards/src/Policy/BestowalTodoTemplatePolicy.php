<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * Authorization policy for bestowal to-do templates.
 *
 * Concrete can* methods are inherited from BasePolicy and resolve against the
 * permission_policies mapping seeded by the template permission migration.
 */
class BestowalTodoTemplatePolicy extends BasePolicy
{
}
