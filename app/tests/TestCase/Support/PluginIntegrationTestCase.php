<?php

declare(strict_types=1);

namespace App\Test\TestCase\Support;

use Cake\TestSuite\IntegrationTestTrait;

/**
 * PluginIntegrationTestCase
 *
 * Provides plugin auto-loading for plugin-specific HTTP feature tests.
 * Extend this class and implement the `pluginName()` method or override the
 * `PLUGIN_NAME` constant to ensure the required plugin is loaded before
 * requests are dispatched.
 */
abstract class PluginIntegrationTestCase extends HttpIntegrationTestCase
{
    use IntegrationTestTrait;

    /**
     * Override this constant in subclasses if `pluginName()` is not overridden.
     */
    protected const PLUGIN_NAME = '';

    /**
     * Resolve the plugin name for the test case.
     *
     * @return string
     */
    protected function pluginName(): string
    {
        return static::PLUGIN_NAME;
    }

    /**
     * Load the plugin before running HTTP assertions.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $plugin = $this->pluginName();
        if ($plugin !== '') {
            $this->loadPlugins([$plugin]);
        }
    }
}
