<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;
use RuntimeException;

/**
 * AppSettingsFixture
 */
class BaseTestFixture extends TestFixture
{
    protected function getData(string $seed, ?string $plugin = null): array
    {
        // create path to seed file it should be ../../[plugin]/config/Seeds/[seed].php or ../config/Seeds/[seed].php
        $path = dirname(__DIR__, 2) . '/config/Seeds/' . $seed . '.php';
        if ($plugin) {
            $path = dirname(__DIR__, 2) . '/plugins/' . $plugin . '/config/Seeds/' . $seed . '.php';
        }
        // include the seed file
        if (file_exists($path)) {
            include_once $path;
        } else {
            throw new RuntimeException('Seed file not found: ' . $path);
        }
        //get the class name from the seed file
        $className = $seed;
        // create an instance of the class
        if (class_exists($className)) {
            $seedInstance = new $className();
        } else {
            throw new RuntimeException('Seed class not found: ' . $className);
        }
        // call the getData method and return the data
        if (method_exists($seedInstance, 'getData')) {
            return $seedInstance->getData();
        } else {
            throw new RuntimeException('getData method not found in seed class: ' . $className);
        }
    }
}
