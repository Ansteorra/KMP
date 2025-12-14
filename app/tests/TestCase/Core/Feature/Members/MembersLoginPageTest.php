<?php

declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

/**
 * Smoke-test the public login page to validate routing and rendering.
 */
final class MembersLoginPageTest extends HttpIntegrationTestCase
{
    public function testLoginPageRenders(): void
    {
        $this->get('/members/login');

        $this->assertResponseOk();
        $this->assertResponseContains('Log in');
        $this->assertResponseContains('Sign in');
    }
}
