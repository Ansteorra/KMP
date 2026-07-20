<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;

/**
 * Authorization policy for bestowal to-do template items.
 *
 * Item edits are authorized through the parent template (see controller item
 * actions, which authorize the owning template with the `edit` action).
 */
class BestowalTodoTemplateItemPolicy extends BasePolicy
{
}
