<?php
declare(strict_types=1);

namespace App\Services\Security;

/** Trust only configured peers, or the explicitly selected Azure managed HTTP ingress. */
final class TrustedProxy
{
    /**
     * @param bool $enabled Whether proxy handling is enabled
     * @param list<string> $peers Trusted proxy IPs or CIDR ranges
     * @param bool $azureIngress Managed Azure HTTP ingress mode
     */
    public function __construct(
        private readonly bool $enabled = false,
        private readonly array $peers = [],
        private readonly bool $azureIngress = false,
    ) {
    }

    /** Read server-owned configuration; request headers never select a trust mode. */
    public static function fromEnvironment(): self
    {
        $value = static function (string $name): string {
            $value = getenv($name);

            return $value === false ? (string)($_ENV[$name] ?? '') : $value;
        };

        return new self(
            filter_var($value('TRUST_PROXY'), FILTER_VALIDATE_BOOLEAN),
            array_values(array_filter(array_map('trim', explode(',', $value('TRUSTED_PROXY_IPS'))))),
            $value('KMP_PROXY_MODE') === 'azure-container-apps' && $value('CONTAINER_APP_NAME') !== '',
        );
    }

    /** Require an explicit proxy peer or a private managed-ingress peer. */
    public function isTrustedPeer(string $peer): bool
    {
        if (!$this->enabled || filter_var($peer, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($this->matchesPeers($peer)) {
            return true;
        }

        return $this->azureIngress && $this->matchesRanges($peer, [
            '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.0/8', '169.254.0.0/16',
            '100.64.0.0/10', '::1/128', 'fc00::/7', 'fe80::/10',
        ]);
    }

    /** Return null for missing/malformed chains; never trust alternate client-IP headers. */
    public function clientAddress(string $peer, string $forwarded): ?string
    {
        if (!$this->isTrustedPeer($peer) || $forwarded === '' || strlen($forwarded) > 4096) {
            return null;
        }
        $addresses = array_map('trim', explode(',', $forwarded));
        if (count($addresses) > 32) {
            return null;
        }
        // Azure appends the managed value at the right; earlier values remain untrusted.
        if ($this->azureIngress) {
            $last = end($addresses);

            return filter_var($last, FILTER_VALIDATE_IP) === false ? null : $last;
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                return null;
            }
        }
        foreach (array_reverse($addresses) as $address) {
            if (!$this->matchesPeers($address)) {
                return $address;
            }
        }

        return null;
    }

    /** Match only the configured proxy addresses. */
    private function matchesPeers(string $address): bool
    {
        return $this->matchesRanges($address, $this->peers);
    }

    /** @param list<string> $ranges Trusted IPs or CIDR ranges */
    private function matchesRanges(string $address, array $ranges): bool
    {
        $binary = inet_pton($address);
        if ($binary === false) {
            return false;
        }
        foreach ($ranges as $range) {
            $parts = explode('/', $range, 2);
            if (filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $network = inet_pton($parts[0]);
            if ($network === false || strlen($network) !== strlen($binary)) {
                continue;
            }
            $bits = isset($parts[1]) && ctype_digit($parts[1]) ? (int)$parts[1] : strlen($binary) * 8;
            if ((isset($parts[1]) && !ctype_digit($parts[1])) || $bits <= 0 || $bits > strlen($binary) * 8) {
                continue;
            }
            $bytes = intdiv($bits, 8);
            $remainder = $bits % 8;
            if (substr($binary, 0, $bytes) !== substr($network, 0, $bytes)) {
                continue;
            }
            $mask = 255 << 8 - $remainder;
            if ($remainder === 0 || ((ord($binary[$bytes]) ^ ord($network[$bytes])) & $mask) === 0) {
                return true;
            }
        }

        return false;
    }
}
