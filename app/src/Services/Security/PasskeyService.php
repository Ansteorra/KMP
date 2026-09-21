<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\Model\Entity\Member;
use App\Services\ImpersonationService;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\ServerRequest;
use Cake\ORM\Locator\LocatorAwareTrait;
use lbuchs\WebAuthn\Attestation\AttestationObject;
use lbuchs\WebAuthn\Attestation\AuthenticatorData;

/** Verify WebAuthn ceremonies against single-use, tenant/origin/session-bound challenges. */
final class PasskeyService
{
    use LocatorAwareTrait;

    /** Issue registration only after the controller has verified the current password. */
    public function registrationOptions(ServerRequest $request, Member $member): array
    {
        $challenge = $this->challenge($request, 'registration', MemberSessionState::fromMember($member));

        return ['challenge' => $challenge, 'rpId' => $request->getUri()->getHost()];
    }

    /** Public challenge contains no member/credential lookup and uses ordinary CSRF protection. */
    public function authenticationOptions(ServerRequest $request): array
    {
        return [
            'challenge' => $this->challenge($request, 'authentication'),
            'csrfToken' => $request->getAttribute('csrfToken'),
        ];
    }

    /** Parse attestation with the WebAuthn library; persist only validated public credentials. */
    public function register(ServerRequest $request): array
    {
        $pending = $this->consume($request, 'registration');
        $identity = $request->getAttribute('identity');
        $member = $identity ? $this->fetchTable('Members')->get($identity->getIdentifier()) : null;
        if (!$member || !MemberSessionState::matches($pending['member'] ?? null, $member)) {
            throw new ForbiddenException('Confirm your password and try passkey setup again.');
        }
        $data = (array)$request->getData();
        $client = $this->clientData($data['clientDataJSON'] ?? null, $pending, 'webauthn.create');
        $attestation = new AttestationObject(self::decode($data['attestationObject'] ?? null), ['none']);
        $auth = $attestation->getAuthenticatorData();
        $this->authenticator($auth, $request);
        if (!$attestation->validateAttestation(hash('sha256', $client, true))) {
            throw new ForbiddenException('Passkey registration could not be verified.');
        }
        $credentialId = self::decode($data['credentialId'] ?? null, 2048);
        if (!hash_equals($auth->getCredentialId(), $credentialId)) {
            throw new ForbiddenException('Passkey credential did not match.');
        }
        $pem = $auth->getPublicKeyPem();
        $key = openssl_pkey_get_public($pem);
        $details = $key ? openssl_pkey_get_details($key) : false;
        $algorithm = match ($details['type'] ?? null) {
            OPENSSL_KEYTYPE_EC => ($details['ec']['curve_name'] ?? '') === 'prime256v1' ? -7 : null,
            OPENSSL_KEYTYPE_RSA => ($details['bits'] ?? 0) >= 2048 ? -257 : null,
            default => null,
        };
        if ($algorithm === null) {
            throw new ForbiddenException('This passkey algorithm is not supported.');
        }
        $table = $this->fetchTable('MemberPasskeys');
        $credential = $table->newEmptyEntity();
        $credential->patch([
            'member_id' => $member->id,
            'auth_version' => $member->auth_version,
            'credential_hash' => hash('sha256', $credentialId),
            'credential_id' => base64_encode($credentialId),
            'public_key' => $pem,
            'origin' => $pending['origin'],
            'sign_count' => $auth->getSignCount(),
        ], ['guard' => false]);
        $table->saveOrFail($credential);

        return [
            'credentialId' => base64_encode($credentialId),
            'publicKey' => preg_replace('/-----[^-]+-----|\s/', '', $pem),
            'algorithm' => $algorithm,
            'origin' => $pending['origin'],
            'rpId' => $request->getUri()->getHost(),
        ];
    }

    /** Verify a signature and atomically advance its counter before creating a session. */
    public function authenticate(ServerRequest $request): Member
    {
        $pending = $this->consume($request, 'authentication');
        $data = (array)$request->getData();
        $client = $this->clientData($data['clientDataJSON'] ?? null, $pending, 'webauthn.get');
        $credentialId = self::decode($data['credentialId'] ?? null, 2048);
        $table = $this->fetchTable('MemberPasskeys');
        $credential = $table->find()->where(['credential_hash' => hash('sha256', $credentialId)])->first();
        if (!$credential || $credential->origin !== $pending['origin']) {
            throw new ForbiddenException('Passkey sign-in failed. Sign in with your password.');
        }
        $member = $this->fetchTable('Members')->get($credential->member_id);
        if (!MemberSessionState::eligible($member) || !hash_equals($member->auth_version, $credential->auth_version)) {
            throw new ForbiddenException('Passkey sign-in failed. Sign in with your password.');
        }
        $binary = self::decode($data['authenticatorData'] ?? null);
        $auth = new AuthenticatorData($binary);
        $this->authenticator($auth, $request);
        $valid = openssl_verify(
            $binary . hash('sha256', $client, true),
            self::decode($data['signature'] ?? null, 2048),
            $credential->public_key,
            OPENSSL_ALGO_SHA256,
        );
        $count = $auth->getSignCount();
        $previous = (int)$credential->sign_count;
        if ($valid !== 1 || (($count !== 0 || $previous !== 0) && $count <= $previous)) {
            throw new ForbiddenException('Passkey verification failed.');
        }
        if ($count !== 0 || $previous !== 0) {
            $changed = $table->updateAll(['sign_count' => $count], [
                'id' => $credential->id, 'sign_count' => $previous, 'auth_version' => $member->auth_version,
            ]);
            if ($changed !== 1) {
                throw new ForbiddenException('Passkey changed. Please try again.');
            }
        }

        return $member;
    }

    /** Revoking this browser credential requires an authenticated owner and an exact credential match. */
    public function remove(ServerRequest $request): void
    {
        $identity = $request->getAttribute('identity');
        if (!$identity || (new ImpersonationService())->isActive($request->getSession())) {
            throw new ForbiddenException();
        }
        $id = self::decode($request->getData('credentialId'), 2048);
        $this->fetchTable('MemberPasskeys')->deleteAll([
            'member_id' => $identity->getIdentifier(), 'credential_hash' => hash('sha256', $id),
        ]);
    }

    /** Issue a bounded nonce tied to this session and tenant. */
    private function challenge(ServerRequest $request, string $purpose, ?array $member = null): string
    {
        if (MemberSessionState::tenantId() === null || (new ImpersonationService())->isActive($request->getSession())) {
            throw new ForbiddenException();
        }
        $challenge = base64_encode(random_bytes(32));
        $request->getSession()->write('Passkeys.' . $purpose, [
            'challenge' => $challenge, 'expires' => time() + 300, 'member' => $member,
            'tenant' => MemberSessionState::tenantId(), 'origin' => $this->origin($request),
        ]);

        return $challenge;
    }

    /** Consume the nonce even when verification fails. */
    private function consume(ServerRequest $request, string $purpose): array
    {
        $session = $request->getSession();
        $pending = $session->read('Passkeys.' . $purpose);
        $session->delete('Passkeys.' . $purpose);
        if (
            !is_array($pending) || ($pending['expires'] ?? 0) < time()
            || ($pending['tenant'] ?? null) !== MemberSessionState::tenantId()
            || ($pending['origin'] ?? null) !== $this->origin($request)
            || (new ImpersonationService())->isActive($session)
        ) {
            throw new ForbiddenException('Passkey challenge expired. Please try again.');
        }

        return $pending;
    }

    /** Validate exact ceremony type, origin, and challenge. */
    private function clientData(mixed $encoded, array $pending, string $type): string
    {
        $binary = self::decode($encoded, 8192);
        $client = json_decode($binary, true, 32, JSON_THROW_ON_ERROR);
        if (
            !is_array($client) || ($client['type'] ?? null) !== $type
            || ($client['origin'] ?? null) !== $pending['origin']
            || ($client['crossOrigin'] ?? false) !== false || isset($client['topOrigin'])
            || !hash_equals(self::decode($pending['challenge']), self::decode($client['challenge'] ?? null, 128))
        ) {
            throw new ForbiddenException('Passkey request did not match this sign-in.');
        }

        return $binary;
    }

    /** Require user presence, verification, and the current relying party. */
    private function authenticator(AuthenticatorData $auth, ServerRequest $request): void
    {
        if (
            !$auth->getUserPresent() || !$auth->getUserVerified()
            || !hash_equals(hash('sha256', $request->getUri()->getHost(), true), $auth->getRpIdHash())
            || ($auth->getIsBackup() && !$auth->getIsBackupEligible())
        ) {
            throw new ForbiddenException('Passkey user verification failed.');
        }
    }

    /** Allow HTTPS and browser-secure localhost development origins. */
    private function origin(ServerRequest $request): string
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        if (
            $uri->getScheme() !== 'https'
            && !($uri->getScheme() === 'http' && ($host === 'localhost' || str_ends_with($host, '.localhost')))
        ) {
            throw new ForbiddenException('Passkeys require a secure connection.');
        }

        return $uri->getScheme() . '://' . $uri->getAuthority();
    }

    /** Strict, bounded decoding accepts WebAuthn base64url and the app's base64 serialization. */
    private static function decode(mixed $value, int $limit = 32768): string
    {
        if (
            !is_string($value) || $value === '' || strlen($value) > $limit
            || !preg_match('/^[A-Za-z0-9_+\/-]+={0,2}$/D', $value)
        ) {
            throw new ForbiddenException('Invalid passkey response.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new ForbiddenException('Invalid passkey response.');
        }

        return $decoded;
    }
}
