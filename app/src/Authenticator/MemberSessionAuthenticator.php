<?php
declare(strict_types=1);

namespace App\Authenticator;

use App\Model\Entity\Member;
use App\Services\ImpersonationService;
use App\Services\Security\MemberSessionState;
use Authentication\Authenticator\Result;
use Authentication\Authenticator\ResultInterface;
use Authentication\Authenticator\SessionAuthenticator;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\TableRegistry;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Revalidate tenant, account eligibility and credential epoch on every request. */
class MemberSessionAuthenticator extends SessionAuthenticator
{
    /** @inheritDoc */
    public function authenticate(ServerRequestInterface $request): ResultInterface
    {
        $session = $request->getAttribute('session');
        $state = $session->read('Auth');
        if (!$state) {
            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }
        // Login submissions must prove the submitted credentials, including PIN enrollment.
        if (
            $request->getMethod() === 'POST'
            && ($request->getAttribute('params')['controller'] ?? null) === 'Members'
            && ($request->getAttribute('params')['action'] ?? null) === 'login'
        ) {
            $session->delete('Auth');
            $session->delete('Impersonation');
            $session->delete('QuickLoginSetup');

            return new Result(null, Result::FAILURE_IDENTITY_NOT_FOUND);
        }
        if (
            is_array($state) && ($state['version'] ?? null) === 1
            && MemberSessionState::tenantId() !== null
            && ($state['tenant_id'] ?? null) === MemberSessionState::tenantId()
            && is_int($state['member_id'] ?? null)
        ) {
            try {
                $member = TableRegistry::getTableLocator()->get('Members')->get($state['member_id']);
                if (
                    MemberSessionState::matches($state, $member)
                    && (new ImpersonationService())->isValid($session)
                ) {
                    return new Result($member, Result::SUCCESS);
                }
            } catch (RecordNotFoundException) {
                // A deleted identity is no longer a credential.
            }
        }
        $session->destroy();

        return new Result(null, Result::FAILURE_CREDENTIALS_INVALID);
    }

    /** @inheritDoc */
    public function persistIdentity(ServerRequestInterface $request, ResponseInterface $response, $identity): array
    {
        $member = $identity instanceof Member ? $identity : $identity->getOriginalData();
        if (!$member instanceof Member) {
            throw new InvalidArgumentException('Member session requires a member identity.');
        }
        $session = $request->getAttribute('session');
        if (!MemberSessionState::matches($session->read('Auth'), $member)) {
            $session->renew();
            $session->write('Auth', MemberSessionState::fromMember($member));
        }

        return ['request' => $request, 'response' => $response];
    }
}
