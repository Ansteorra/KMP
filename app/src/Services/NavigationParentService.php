<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/** Policy-checked list destination for directly opened records; no server history stack. */
final class NavigationParentService
{
    /** Keep fallback destinations on this tenant and within the user's read permissions. */
    public function url(ServerRequest $request): string
    {
        $identity = $request->getAttribute('identity');
        if ($identity === null || $request->getParam('prefix')) {
            return Router::url('/');
        }
        foreach (UiUpdateRegistry::definitions() as $alias => $definition) {
            $plugin = str_contains($alias, '.') ? explode('.', $alias, 2)[0] : null;
            if (
                ($definition['controller'] ?? null) !== $request->getParam('controller')
                || $plugin !== $request->getParam('plugin')
            ) {
                continue;
            }
            $listActions = $definition['listActions'] ?? ['index' => 'index'];
            $action = array_key_first($listActions);
            if (
                $action === null
                || !$identity->can($listActions[$action], TableRegistry::getTableLocator()->get($alias))
            ) {
                return Router::url('/');
            }

            return Router::url(['plugin' => $plugin, 'controller' => $definition['controller'], 'action' => $action]);
        }

        return Router::url('/');
    }
}
