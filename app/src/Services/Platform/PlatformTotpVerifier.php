<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use Closure;
use InvalidArgumentException;

class PlatformTotpVerifier implements PlatformTotpVerifierInterface
{
    private Closure $clock;

    /**
     * Constructor.
     */
    public function __construct(
        private readonly SecretStoreInterface $secretStore,
        private readonly int $window = 1,
        private readonly int $period = 30,
        private readonly int $digits = 6,
        private readonly string $algorithm = 'sha1',
        ?callable $clock = null,
    ) {
        if ($this->window < 0 || $this->period < 1 || $this->digits < 6 || $this->digits > 8) {
            throw new InvalidArgumentException('Invalid TOTP verifier timing or digit configuration.');
        }
        $algorithm = strtolower($this->algorithm);
        if (!in_array($algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported TOTP HMAC algorithm "%s".', $this->algorithm));
        }
        $this->clock = $clock !== null ? $clock(...) : static fn(): int => time();
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function verify(string $platformUserId, ?string $totpSecretRef, string $totpCode): bool
    {
        $totpCode = trim($totpCode);
        if ($totpSecretRef === null || !preg_match('/^\d{' . $this->digits . '}$/', $totpCode)) {
            return false;
        }

        $secret = $this->secretStore->get($totpSecretRef);
        if ($secret === null || $secret->isEmpty()) {
            return false;
        }

        $counter = intdiv(($this->clock)(), $this->period);
        for ($offset = -$this->window; $offset <= $this->window; $offset++) {
            $candidateCounter = $counter + $offset;
            if ($candidateCounter < 0) {
                continue;
            }
            $candidate = $this->codeForCounter($secret, $candidateCounter);
            if ($candidate !== null && hash_equals($candidate, $totpCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for test vectors and enrollment validation.
     */
    public function codeForTimestamp(SensitiveString|string $base32Secret, int $timestamp): string
    {
        $secret = $base32Secret instanceof SensitiveString ? $base32Secret : new SensitiveString($base32Secret);

        $code = $this->codeForCounter($secret, intdiv($timestamp, $this->period));
        if ($code === null) {
            throw new InvalidArgumentException('Invalid base32 TOTP secret.');
        }

        return $code;
    }

    /**
     * Generate the HOTP value for a moving factor counter.
     */
    private function codeForCounter(SensitiveString $base32Secret, int $counter): ?string
    {
        $key = $this->decodeBase32($base32Secret->reveal());
        if ($key === '') {
            return null;
        }

        $hash = hash_hmac(
            strtolower($this->algorithm),
            pack('N2', intdiv($counter, 0x100000000), $counter & 0xffffffff),
            $key,
            true,
        );
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        $modulus = 10 ** $this->digits;

        return str_pad((string)($binary % $modulus), $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Decode an RFC 4648 base32 secret without external dependencies.
     */
    private function decodeBase32(string $secret): string
    {
        $secret = strtoupper((string)preg_replace('/[\s-]/', '', trim($secret, " \t\n\r\0\x0B=")));
        if ($secret === '' || preg_match('/[^A-Z2-7]/', $secret)) {
            return '';
        }

        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0, $length = strlen($secret); $i < $length; $i++) {
            $value = strpos($alphabet, $secret[$i]);
            if ($value === false) {
                return '';
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        for ($i = 0, $length = strlen($bits) - (strlen($bits) % 8); $i < $length; $i += 8) {
            $decoded .= chr(bindec(substr($bits, $i, 8)));
        }

        return $decoded;
    }
}
