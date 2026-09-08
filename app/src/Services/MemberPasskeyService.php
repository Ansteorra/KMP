<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use App\Services\Security\MemberSessionState;
use Cake\Http\ServerRequest;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Security;
use lbuchs\WebAuthn\WebAuthn;
use RuntimeException;

/** Tenant/session/origin-bound WebAuthn ceremonies with atomic challenge consumption. */
class MemberPasskeyService
{
    use LocatorAwareTrait;

    private WebAuthn $verifier;
    private string $origin;
    private string $rpId;
    private string $binding;

    /** Bind the verifier to the resolved tenant host and current session. */
    public function __construct(ServerRequest $request)
    {
        $uri = $request->getUri();
        $this->rpId = strtolower($uri->getHost());
        $this->origin = $uri->getScheme() . '://' . $uri->getAuthority();
        if (
            MemberSessionState::tenantId() === null || $this->rpId === ''
            || ($uri->getScheme() !== 'https' && $this->rpId !== 'localhost')
        ) {
            throw new RuntimeException('Passkeys require a secure tenant origin.');
        }
        $request->getSession()->start();
        if ($request->getSession()->id() === '') {
            throw new RuntimeException('Passkey session is unavailable.');
        }
        $this->binding = hash_hmac('sha256', json_encode([
            MemberSessionState::tenantId(), $this->origin, $request->getSession()->id(),
        ], JSON_THROW_ON_ERROR), Security::getSalt());
        $this->verifier = new WebAuthn('KMP', $this->rpId, ['none'], true);
    }

    /** Generate options after controller password reauthentication for enrollment. */
    public function options(?Member $member = null): array
    {
        $credentials = $this->fetchTable('MemberPasskeys');
        if ($member !== null) {
            if (!MemberSessionState::eligible($member)) {
                throw new RuntimeException('Account unavailable.');
            }
            $existing = $credentials->find()->where(['member_id' => $member->id, 'rp_id' => $this->rpId])->all();
            if ($existing->count() >= 20) {
                throw new RuntimeException('Remove an old passkey before adding another.');
            }
            $exclude = [];
            foreach ($existing as $credential) {
                $exclude[] = self::decode((string)$credential->credential_id);
            }
            $args = $this->verifier->getCreateArgs(
                $this->userHandle($member),
                (string)$member->email_address,
                (string)$member->sca_name,
                45,
                true,
                true,
                null,
                $exclude,
            );
        } else {
            $args = $this->verifier->getGetArgs([], 45, true, true, true, true, true, true);
        }
        $challenge = $this->verifier->getChallenge()->getBinaryString();
        $challenges = $this->fetchTable('PasskeyChallenges');
        $challenges->deleteAll(['expires_at <' => time()]);
        // One pending ceremony per session, so superseded prompts cannot authenticate.
        $challenges->deleteAll(['binding' => $this->binding]);
        $record = $challenges->newEmptyEntity();
        $record->patch([
            'id' => hash('sha256', $challenge),
            'binding' => $this->binding,
            'operation' => $member === null ? 'login' : 'register',
            'member_state' => $member === null
                ? null : json_encode(MemberSessionState::fromMember($member), JSON_THROW_ON_ERROR),
            'expires_at' => time() + 120,
        ], ['guard' => false]);
        $challenges->saveOrFail($record);

        return json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 32, JSON_THROW_ON_ERROR);
    }

    /** Verify origin exactly (the library additionally checks the RP ID hash). */
    private function consume(array $response, string $operation): array
    {
        $clientData = self::decode($response['clientDataJSON'] ?? null);
        $client = json_decode($clientData, true, 16, JSON_THROW_ON_ERROR);
        if (
            !is_array($client) || ($client['origin'] ?? null) !== $this->origin
            || ($client['crossOrigin'] ?? false) !== false || isset($client['topOrigin'])
        ) {
            throw new RuntimeException('Invalid passkey origin.');
        }
        $challenge = self::decode($client['challenge'] ?? null);
        $conditions = [
            'id' => hash('sha256', $challenge), 'binding' => $this->binding,
            'operation' => $operation, 'expires_at >=' => time(),
        ];
        $table = $this->fetchTable('PasskeyChallenges');
        $record = $table->find()->where($conditions)->first();
        if ($record === null || $table->deleteAll($conditions) !== 1) {
            throw new RuntimeException('Passkey request expired or already used.');
        }

        return [$clientData, $challenge, $record->member_state];
    }

    /** Registration binds a verified public key to the reauthenticated member epoch. */
    public function register(Member $member, array $response, string $label): void
    {
        [$clientData, $challenge, $state] = $this->consume($response, 'register');
        $fresh = $this->fetchTable('Members')->get($member->id);
        if (!MemberSessionState::matches(json_decode((string)$state, true), $fresh)) {
            throw new RuntimeException('Account changed. Sign in again.');
        }
        $data = $this->verifier->processCreate(
            $clientData,
            self::decode($response['attestationObject'] ?? null),
            $challenge,
            true,
            true,
        );
        if (!hash_equals($data->credentialId, self::decode($response['id'] ?? null))) {
            throw new RuntimeException('Credential identifier mismatch.');
        }
        $table = $this->fetchTable('MemberPasskeys');
        $record = $table->newEmptyEntity();
        $record->patch([
            'member_id' => $fresh->id,
            'credential_hash' => hash('sha256', $data->credentialId),
            'credential_id' => self::encode($data->credentialId),
            'public_key' => $data->credentialPublicKey,
            'user_handle' => self::encode($this->userHandle($fresh)),
            'auth_version' => (string)$fresh->auth_version,
            'rp_id' => $this->rpId,
            'signature_counter' => (int)$data->signatureCounter,
            'revision' => bin2hex(random_bytes(32)),
            'label' => mb_substr(trim($label) ?: 'Passkey', 0, 80),
            'created_at' => time(),
        ], ['guard' => false]);
        $table->saveOrFail($record);
    }

    /** Only a valid, single-use, UV assertion for an active credential creates identity. */
    public function authenticate(array $response): Member
    {
        [$clientData, $challenge] = $this->consume($response, 'login');
        $table = $this->fetchTable('MemberPasskeys');
        $id = self::decode($response['id'] ?? null);
        $record = $table->find()
            ->where(['credential_hash' => hash('sha256', $id), 'rp_id' => $this->rpId])->firstOrFail();
        $member = $this->fetchTable('Members')->get($record->member_id);
        if (
            !MemberSessionState::eligible($member)
            || !hash_equals((string)$member->auth_version, (string)$record->auth_version)
            || !hash_equals(self::decode((string)$record->user_handle), self::decode($response['userHandle'] ?? null))
        ) {
            throw new RuntimeException('Credential unavailable.');
        }
        $this->verifier->processGet(
            $clientData,
            self::decode($response['authenticatorData'] ?? null),
            self::decode($response['signature'] ?? null),
            $record->public_key,
            $challenge,
            (int)$record->signature_counter,
            true,
            true,
        );
        // CAS also protects authenticators whose synchronized counters remain zero.
        if (
            $table->updateAll([
            'signature_counter' => (int)$this->verifier->getSignatureCounter(),
            'revision' => bin2hex(random_bytes(32)), 'last_used_at' => time(),
            ], ['id' => $record->id, 'revision' => $record->revision, 'auth_version' => $member->auth_version]) !== 1
        ) {
            throw new RuntimeException('Credential changed. Retry sign in.');
        }
        $fresh = $this->fetchTable('Members')->get($member->id);
        if (!MemberSessionState::matches(MemberSessionState::fromMember($member), $fresh)) {
            throw new RuntimeException('Account changed. Sign in again.');
        }

        return $fresh;
    }

    /** Opaque stable account handle scoped to the immutable tenant ID. */
    private function userHandle(Member $member): string
    {
        $scope = json_encode([MemberSessionState::tenantId(), (int)$member->id], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $scope, Security::getSalt(), true);
    }

    /** Encode a binary WebAuthn field for JSON transport. */
    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** Reject oversized, ambiguous or malformed binary transport fields. */
    public static function decode(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 65536 || !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
            throw new RuntimeException('Invalid passkey response.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false || self::encode($decoded) !== $value) {
            throw new RuntimeException('Invalid passkey encoding.');
        }

        return $decoded;
    }
}
