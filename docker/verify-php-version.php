<?php
declare(strict_types=1);

/** Check supported PHP branches against the October 2026 critical security fixes. */
$supportsPhpVersion = static function (string $version): bool {
    if (!preg_match('/^(8\.[345])\.\d+$/D', $version, $matches)) {
        return false;
    }

    $minimumVersions = ['8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];

    return version_compare($version, $minimumVersions[$matches[1]], '>=');
};

// Including the policy allows boundary tests without overriding the actual runtime check.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return $supportsPhpVersion;
}

if (!$supportsPhpVersion(PHP_VERSION)) {
    fwrite(STDERR, 'Unsupported or vulnerable PHP ' . PHP_VERSION
        . '; require 8.3.36+, 8.4.27+, or 8.5.12+ within the supported branch'
        . ' (CVE-2026-109618, CVE-2026-109619).' . PHP_EOL);
    exit(1);
}

echo 'PHP security version check passed: ' . PHP_VERSION . PHP_EOL;
