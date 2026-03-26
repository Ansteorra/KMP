<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * Manages quick-login device enrollment, metadata collection, and removal.
 *
 * Covers device PIN persistence, user-agent parsing for OS/browser detection,
 * and geolocation hint extraction from proxy headers.
 * Controller-layer concerns (session, flash, authorization) remain in MembersController.
 */
class QuickLoginDeviceService
{
    use LocatorAwareTrait;

    /**
     * Persist or update a quick-login device PIN and metadata for a member.
     *
     * @param \App\Model\Entity\Member $member Member enabling quick login.
     * @param string $deviceId Stable browser/device identifier.
     * @param string $pin Raw numeric PIN to hash and store.
     * @param array<string, string|null> $metadata Enrollment metadata.
     * @return bool True when the device record was saved.
     */
    public function saveDevicePin(
        Member $member,
        string $deviceId,
        string $pin,
        array $metadata = [],
    ): bool {
        if (
            $deviceId === '' ||
            !preg_match('/^[a-zA-Z0-9_-]{16,128}$/', $deviceId) ||
            !preg_match('/^\d{4,10}$/', $pin)
        ) {
            return false;
        }

        /** @var \App\Model\Table\MemberQuickLoginDevicesTable $quickLoginDevices */
        $quickLoginDevices = $this->fetchTable('MemberQuickLoginDevices');
        $device = $quickLoginDevices->find()
            ->where([
                'device_id' => $deviceId,
            ])
            ->first();

        if ($device === null) {
            $device = $quickLoginDevices->newEmptyEntity();
        }

        $device->member_id = (int)$member->id;
        $device->device_id = $deviceId;
        $device->pin_hash = (new DefaultPasswordHasher())->hash($pin);
        $device->failed_attempts = 0;
        $device->last_failed_login = null;
        $device->last_used = DateTime::now();
        $device->configured_ip_address = $metadata['configured_ip_address'] ?? null;
        $device->configured_location_hint = $metadata['configured_location_hint'] ?? null;
        $device->configured_os = $metadata['configured_os'] ?? null;
        $device->configured_browser = $metadata['configured_browser'] ?? null;
        $device->configured_user_agent = $metadata['configured_user_agent'] ?? null;
        $device->last_used_ip_address = $metadata['last_used_ip_address'] ?? null;
        $device->last_used_location_hint = $metadata['last_used_location_hint'] ?? null;

        return (bool)$quickLoginDevices->save($device);
    }

    /**
     * Build metadata for quick-login device enrollment from request context.
     *
     * @param string|null $userAgent Raw User-Agent header value.
     * @param string|null $clientIp Client IP address.
     * @param array<string, string> $headers Proxy headers for location hints.
     * @return array<string, string|null>
     */
    public function collectDeviceMetadata(
        ?string $userAgent,
        ?string $clientIp,
        array $headers = [],
    ): array {
        $userAgent = $this->truncateString($userAgent, 512);
        $ipAddress = $this->truncateString($clientIp, 45);
        $locationHint = $this->extractLocationHint($headers);

        return [
            'configured_ip_address' => $ipAddress,
            'configured_location_hint' => $locationHint,
            'configured_os' => $this->detectOperatingSystem($userAgent ?? ''),
            'configured_browser' => $this->detectBrowser($userAgent ?? ''),
            'configured_user_agent' => $userAgent,
            'last_used_ip_address' => $ipAddress,
            'last_used_location_hint' => $locationHint,
        ];
    }

    /**
     * Collect usage metadata (IP and location) for quick-login events.
     *
     * @param string|null $clientIp Client IP address.
     * @param array<string, string> $headers Proxy headers for location hints.
     * @return array<string, string|null>
     */
    public function collectUsageMetadata(?string $clientIp, array $headers = []): array
    {
        return [
            'last_used_ip_address' => $this->truncateString($clientIp, 45),
            'last_used_location_hint' => $this->extractLocationHint($headers),
        ];
    }

    /**
     * Derive a friendly operating-system label from a user-agent string.
     *
     * @param string $userAgent HTTP user-agent header value.
     * @return string|null Detected OS label or null when unavailable.
     */
    public function detectOperatingSystem(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $signatures = [
            'iPhone' => 'iOS (iPhone)',
            'iPad' => 'iPadOS',
            'Android' => 'Android',
            'Windows NT 10.0' => 'Windows 10/11',
            'Windows NT 6.3' => 'Windows 8.1',
            'Windows NT 6.1' => 'Windows 7',
            'Mac OS X' => 'macOS',
            'CrOS' => 'ChromeOS',
            'Linux' => 'Linux',
        ];
        foreach ($signatures as $needle => $label) {
            if (stripos($userAgent, $needle) !== false) {
                return $label;
            }
        }

        return 'Unknown';
    }

    /**
     * Derive a friendly browser label from a user-agent string.
     *
     * @param string $userAgent HTTP user-agent header value.
     * @return string|null Detected browser label or null when unavailable.
     */
    public function detectBrowser(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $signatures = [
            'Edg/' => 'Microsoft Edge',
            'SamsungBrowser/' => 'Samsung Internet',
            'CriOS/' => 'Google Chrome (iOS)',
            'Chrome/' => 'Google Chrome',
            'FxiOS/' => 'Mozilla Firefox (iOS)',
            'Firefox/' => 'Mozilla Firefox',
            'Safari/' => 'Safari',
        ];
        foreach ($signatures as $needle => $label) {
            if (stripos($userAgent, $needle) !== false) {
                return $label;
            }
        }

        return 'Unknown';
    }

    /**
     * Build a best-effort location hint from available proxy headers.
     *
     * @param array<string, string> $headers Map of header names to values.
     * @return string|null Location summary or null when unavailable.
     */
    public function extractLocationHint(array $headers = []): ?string
    {
        $city = $this->truncateString($headers['CloudFront-Viewer-City'] ?? null, 60);
        $region = $this->truncateString($headers['CloudFront-Viewer-Country-Region'] ?? null, 20);

        $country = null;
        foreach (['CloudFront-Viewer-Country', 'CF-IPCountry', 'X-AppEngine-Country'] as $headerName) {
            $headerValue = $this->truncateString($headers[$headerName] ?? null, 20);
            if ($headerValue !== null) {
                $country = strtoupper($headerValue);
                break;
            }
        }

        $parts = array_values(array_filter(
            [$city, $region, $country],
            static fn(?string $part): bool => $part !== null,
        ));
        if (empty($parts)) {
            return null;
        }

        return $this->truncateString(implode(', ', $parts), 120);
    }

    /**
     * Trim and truncate an optional string to the configured maximum length.
     *
     * @param string|null $value Raw string value.
     * @param int $maxLength Maximum allowed length.
     * @return string|null Normalized string or null when empty.
     */
    private function truncateString(?string $value, int $maxLength): ?string
    {
        $normalized = trim((string)$value);
        if ($normalized === '') {
            return null;
        }
        if (strlen($normalized) <= $maxLength) {
            return $normalized;
        }

        return substr($normalized, 0, $maxLength);
    }
}
