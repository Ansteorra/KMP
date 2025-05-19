<?php
declare(strict_types=1);

namespace App\Policy;

class ControllerActionHookPolicy
{
    /**
     * Magic method to allow all actions
     *
     * @param string $name Method name
     * @param array $arguments Arguments
     * @return bool
     */
    public function __call(string $name, array $arguments): bool
    {
        /** @var ?\Authorization\Identity $user */
        return true;
    }
}
