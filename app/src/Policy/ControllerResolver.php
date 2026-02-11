<?php

declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */

namespace App\Policy;

use Authorization\Policy\Exception\MissingPolicyException;
use Authorization\Policy\ResolverInterface;
use Cake\Controller\Controller;
use Cake\Core\App;
use Cake\Http\ServerRequest;

/**
 * Policy resolver that applies conventions based policy classes
 * for CakePHP ORM Tables, Entities and Queries.
 */
class ControllerResolver implements ResolverInterface
{
    /**
     * Application namespace.
     *
     * @var string
     */
    protected string $appNamespace = 'App';

    /**
     * Plugin name overrides.
     *
     * @var array<string, string>
     */
    protected array $overrides = [];

    /**
     * Constructor
     *
     * @param string $appNamespace The application namespace
     * @param array<string, string> $overrides A list of plugin name overrides.
     */
    public function __construct(
        string $appNamespace = 'App',
        array $overrides = [],
    ) {
        $this->appNamespace = $appNamespace;
        $this->overrides = $overrides;
    }

    /**
     * Get a policy for an ORM Table, Entity or Query.
     *
     * @param mixed $resource The resource.
     * @return mixed
     * @throws \Authorization\Policy\Exception\MissingPolicyException When a policy for the
     *   resource has not been defined or cannot be resolved.
     */
    public function getPolicy(mixed $resource): mixed
    {
        if ($resource instanceof Controller) {
            return $this->getControllerPolicy($resource);
        }
        if ($resource instanceof ServerRequest) {
            return $this->getControllerPolicyByRequest($resource);
        }
        if (is_string($resource)) {
            return $this->getControllerPolicyByName($resource);
        }
        if (Is_Array($resource)) {
            $controller = $resource['controller'];
            // Handle plugin parameter - convert false to null
            // CakePHP routing uses false to indicate no plugin
            $plugin = $resource['plugin'] ?? null;
            if ($plugin === false) {
                $plugin = null;
            }
            $prefix = $resource['prefix'] ?? null;

            return $this->getControllerPolicyByName($controller, $plugin, $prefix);
        }
        throw new MissingPolicyException([get_debug_type($resource)]);
    }

    /**
     * Get a policy for an entity
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity to get a policy for
     * @return mixed
     */
    protected function getControllerPolicy(Controller $controller): mixed
    {
        $class = get_class($controller);
        $controllerNamespace = "\Controller\\";
        $namespace = str_replace(
            '\\',
            '/',
            substr($class, 0, (int)strpos($class, $controllerNamespace)),
        );
        /** @psalm-suppress PossiblyFalseOperand */
        $name = substr(
            $class,
            strpos($class, $controllerNamespace) + strlen($controllerNamespace),
        );

        return $this->findPolicy($class, $name, $namespace);
    }

    /**
     * Get a policy for a table
     *
     * @param \Cake\Datasource\RepositoryInterface $table The table/repository to get a policy for.
     * @return mixed
     */
    protected function getControllerPolicyByName(
        string $controller,
        ?string $plugin = null,
        ?string $prefix = null,
    ): mixed {
        $class = $this->getControllerClass($controller, $plugin, $prefix);

        // Handle case when controller class cannot be resolved
        if ($class === null) {
            throw new MissingPolicyException([$controller]);
        }

        $controllerNamespace = "\Controller\\";
        $namespace = str_replace(
            '\\',
            '/',
            substr($class, 0, (int)strpos($class, $controllerNamespace)),
        );
        /** @psalm-suppress PossiblyFalseOperand */
        $name = substr(
            $class,
            strpos($class, $controllerNamespace) + strlen($controllerNamespace),
        );

        return $this->findPolicy($class, $name, $namespace);
    }

    /**
     * Get a the policy based on the controller
     *
     * @param \Cake\Datasource\RepositoryInterface $table The table/repository to get a policy for.
     * @return mixed
     */
    protected function getControllerPolicyByRequest(
        ServerRequest $request,
    ): mixed {
        $controller = $request->getParam('controller');
        $plugin = $request->getParam('plugin');
        $prefix = $request->getParam('prefix');

        return $this->getControllerPolicyByName($controller, $plugin, $prefix);
    }

    /**
     * Locate a policy class using conventions
     *
     * @param string $class The full class name.
     * @param string $name The name suffix of the resource.
     * @param string $namespace The namespace to find the policy in.
     * @throws \Authorization\Policy\Exception\MissingPolicyException When a policy for the
     *   resource has not been defined.
     * @return mixed
     */
    protected function findPolicy(
        string $class,
        string $name,
        string $namespace,
    ): mixed {
        $namespace = $this->getNamespace($namespace);
        $policyClass = null;

        // plugin entities can have application overrides defined.
        if ($namespace !== $this->appNamespace) {
            $policyClass = App::className(
                $name,
                $namespace . '\\Policy',
                'Policy',
            );
        }

        // Check the application/plugin.
        if ($policyClass === null) {
            $policyClass = App::className(
                $namespace . '.' . $name,
                'Policy',
                'Policy',
            );
        }

        if ($policyClass === null) {
            throw new MissingPolicyException([$class]);
        }

        return new $policyClass();
    }

    /**
     * Returns plugin namespace override if exists.
     *
     * @param string $namespace The namespace to find the policy in.
     * @return string
     */
    protected function getNamespace(string $namespace): string
    {
        if (isset($this->overrides[$namespace])) {
            return $this->overrides[$namespace];
        }

        return $namespace;
    }

    /**
     * Determine the controller class name based on current request and controller param
     *
     * @param \Cake\Http\ServerRequest $request The request to build a controller for.
     * @return class-string<\Cake\Controller\Controller>|null
     */
    public function getControllerClass(
        string $controller,
        ?string $plugin = null,
        ?string $prefix = null,
    ): ?string {
        $pluginPath = '';
        $namespace = 'Controller';
        if ($plugin) {
            $pluginPath = $plugin . '.';
        }
        if ($prefix) {
            $namespace .= '/' . $prefix;
        }
        $firstChar = substr($controller, 0, 1);

        // Disallow plugin short forms, / and \\ from
        // controller names as they allow direct references to
        // be created.
        if (
            str_contains($controller, '\\') ||
            str_contains($controller, '/') ||
            str_contains($controller, '.') ||
            $firstChar === strtolower($firstChar)
        ) {
            throw new MissingPolicyException([$controller]);
        }

        /** @var class-string<\Cake\Controller\Controller>|null */
        return App::className(
            $pluginPath . $controller,
            $namespace,
            'Controller',
        );
    }
}
