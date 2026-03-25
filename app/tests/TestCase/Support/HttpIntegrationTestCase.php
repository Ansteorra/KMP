<?php
declare(strict_types=1);

namespace App\Test\TestCase\Support;

use App\Test\TestCase\BaseTestCase;
use App\Test\TestCase\TestAuthenticationHelperTrait;
use Cake\TestSuite\IntegrationTestTrait;

/**
 * HttpIntegrationTestCase
 *
 * Base class for HTTP feature tests. Provides Stimulus-friendly session helpers,
 * automatic transaction handling, and a seeded database via BaseTestCase.
 */
abstract class HttpIntegrationTestCase extends BaseTestCase
{
    use IntegrationTestTrait;
    use TestAuthenticationHelperTrait;

    /**
     * Ensure the Cake router runs in HTTP mode for Turbo/Stimulus rendering.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }
}
