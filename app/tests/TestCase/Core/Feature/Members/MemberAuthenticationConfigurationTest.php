<?php
declare(strict_types=1);

namespace App\Test\TestCase\Core\Feature\Members;

use App\Application;
use App\Authenticator\MemberSessionAuthenticator;
use App\Identifier\KMPBruteForcePasswordIdentifier;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Http\ServerRequest;

class MemberAuthenticationConfigurationTest extends HttpIntegrationTestCase
{
    public function testWebAuthenticationUsesProtectedPasswordIdentifier(): void
    {
        $this->get('/members/login');
        $this->assertResponseOk();
        $app = new Application(CONFIG);
        $service = $app->getAuthenticationService(new ServerRequest(['url' => '/members/login']));
        $authenticators = $service->authenticators();

        $this->assertInstanceOf(MemberSessionAuthenticator::class, $authenticators->get('MemberSession'));
        $form = $authenticators->get('Form');
        $identifier = $form->getIdentifier();
        $this->assertInstanceOf(KMPBruteForcePasswordIdentifier::class, $identifier);
        $fields = ['username' => 'email_address', 'password' => 'password'];
        $this->assertSame($fields, $form->getConfig('fields'));
        $this->assertSame($fields, $identifier->getConfig('fields'));
        $this->assertSame('Authentication.Fallback', $identifier->getConfig('passwordHasher.className'));
    }
}
