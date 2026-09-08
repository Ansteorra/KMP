<?php
declare(strict_types=1);

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;
use App\Services\ImpersonationService;
use App\Services\MemberPasskeyService;
use App\Services\Security\RequestRateLimiter;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Response;
use Throwable;

/** Native member login and management; password login/recovery remains available. */
class PasskeysController extends AppController
{
    /** @inheritDoc */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['loginOptions', 'login']);
    }

    /** List only the signed-in member's credentials. */
    public function index(): ?Response
    {
        $this->request->allowMethod(['get']);
        $member = $this->owner();
        if (!$this->request->getHeaderLine('Turbo-Frame')) {
            return $this->redirect('/members/profile');
        }
        $passkeys = $this->fetchTable('MemberPasskeys')->find()
            ->select(['id', 'label', 'created_at', 'last_used_at', 'auth_version'])
            ->where(['member_id' => $member->id])->orderByDesc('id')->all();
        $this->set(compact('passkeys', 'member'));

        return null;
    }

    /** Password reauthentication is required to add a login credential. */
    public function registerOptions(): Response
    {
        $this->request->allowMethod(['post']);
        $member = $this->owner();

        return $this->ceremony(function (MemberPasskeyService $service) use ($member): array {
            $limiter = new RequestRateLimiter();
            if (!$limiter->attempt($limiter::BUCKET_PASSKEY_REAUTH, (string)$member->id)->allowed) {
                throw new ForbiddenException('Reauthentication limit reached.');
            }
            $password = $this->request->getData('password');
            if (!is_string($password) || !(new DefaultPasswordHasher())->check($password, (string)$member->password)) {
                throw new ForbiddenException('Reauthentication failed.');
            }

            return $service->options($member);
        });
    }

    /** Complete a password-authorized enrollment ceremony. */
    public function register(): Response
    {
        $this->request->allowMethod(['post']);
        $member = $this->owner();

        return $this->ceremony(function (MemberPasskeyService $service) use ($member): array {
            $service->register(
                $member,
                (array)$this->request->getData('credential'),
                (string)$this->request->getData('label'),
            );

            return ['redirect' => '/passkeys'];
        });
    }

    /** Discoverable credentials avoid exposing an account/credential lookup endpoint. */
    public function loginOptions(): Response
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->skipAuthorization();

        return $this->ceremony(fn(MemberPasskeyService $service): array => $service->options());
    }

    /** Create a renewed authenticated session from a verified assertion. */
    public function login(): Response
    {
        $this->request->allowMethod(['post']);
        $this->Authorization->skipAuthorization();

        return $this->ceremony(function (MemberPasskeyService $service): array {
            if ((new ImpersonationService())->isActive($this->request->getSession())) {
                throw new ForbiddenException('End impersonation before sign in.');
            }
            $member = $service->authenticate((array)$this->request->getData('credential'));
            $this->request->getSession()->renew();
            $this->Authentication->setIdentity($member);

            $mobile = StaticHelpers::isMobilePhone($this->request->getHeaderLine('User-Agent'));
            if ($mobile) {
                $this->request->getSession()->write('viewMode', 'mobile');
            }

            return ['redirect' => $mobile ? '/members/view-mobile-card' : '/members/profile'];
        });
    }

    /** Remove only a credential owned by the signed-in member. */
    public function delete(string $id): Response
    {
        $this->request->allowMethod(['post', 'delete']);
        $member = $this->owner();
        if (!ctype_digit($id)) {
            throw new ForbiddenException('Passkey unavailable.');
        }
        $table = $this->fetchTable('MemberPasskeys');
        $table->getConnection()->transactional(function () use ($table, $member, $id): void {
            if ($table->deleteAll(['id' => $id, 'member_id' => $member->id]) !== 1) {
                throw new ForbiddenException('Passkey unavailable.');
            }
        });
        if (str_contains($this->request->getHeaderLine('Accept'), 'application/json')) {
            return $this->json(['removed' => true]);
        }
        $this->Flash->success(__('Passkey removed.'));

        return $this->redirect(['action' => 'index']);
    }

    /** Manage only the current member, with no impersonated enrollment. */
    private function owner(): Member
    {
        $member = $this->request->getAttribute('identity');
        if (!$member instanceof Member || (new ImpersonationService())->isActive($this->request->getSession())) {
            throw new ForbiddenException('Sign in as yourself to manage passkeys.');
        }
        $this->Authorization->authorize($member, 'partialEdit');

        return $member;
    }

    /** Keep credential contents and parser exceptions out of logs and responses. */
    private function ceremony(callable $operation): Response
    {
        try {
            $limiter = new RequestRateLimiter();
            if (!$limiter->attempt($limiter::BUCKET_PASSKEY, $this->request->clientIp())->allowed) {
                return $this->json(['error' => 'Too many attempts. Wait a few minutes or use password login.'], 429);
            }
            if (strlen((string)$this->request->getBody()) > 100000) {
                return $this->json(['error' => 'Passkey response is too large.'], 400);
            }

            return $this->json($operation(new MemberPasskeyService($this->request)));
        } catch (Throwable) {
            return $this->json(['error' => 'Passkey verification failed. Retry or sign in with your password.'], 400);
        }
    }

    /** Return non-cacheable ceremony data. */
    private function json(array $data, int $status = 200): Response
    {
        return $this->response->withType('application/json')->withStatus($status)
            ->withHeader('Cache-Control', 'no-store')
            ->withStringBody(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
