<?php

declare(strict_types=1);

namespace App\KMP;

use Cake\I18n\DateTime;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Timezone Helper - centralized timezone handling for KMP.
 *
 * Manages timezone conversions for display (UTC -> User TZ) and input
 * (User TZ -> UTC) with priority resolution: User -> App Default -> UTC.
 *
 * @package App\KMP
 * @see /docs/10.3-timezone-handling.md For comprehensive documentation
 * @see \App\View\Helper\TimezoneHelper View helper for templates
 */
class TimezoneHelper
{
    /**
     * Default timezone format for display
     */
    public const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const DEFAULT_DATE_FORMAT = 'Y-m-d';
    public const DEFAULT_TIME_FORMAT = 'H:i:s';
    public const DISPLAY_DATETIME_FORMAT = 'F j, Y g:i A';
    public const DISPLAY_DATE_FORMAT = 'F j, Y';
    public const DISPLAY_TIME_FORMAT = 'g:i A';

    /**
     * Common US timezones for kingdom events
     */
    public const COMMON_TIMEZONES = [
        'America/Chicago' => 'Central Time (US)',
        'America/New_York' => 'Eastern Time (US)',
        'America/Denver' => 'Mountain Time (US)',
        'America/Los_Angeles' => 'Pacific Time (US)',
        'America/Phoenix' => 'Arizona (MST - No DST)',
        'America/Anchorage' => 'Alaska Time (US)',
        'Pacific/Honolulu' => 'Hawaii Time (US)',
        'UTC' => 'UTC (Coordinated Universal Time)',
    ];

    /**
     * Cached application default timezone
     */
    private static ?string $appTimezoneCache = null;

    /**
     * Cached timezone list for performance
     */
    private static ?array $timezoneListCache = null;

    /**
     * Get the timezone for a specific user/member
     *
     * Resolves timezone in priority order:
     * 1. Member's timezone field
     * 2. Application default timezone
     * 3. UTC fallback
     *
     * @param \App\Model\Entity\Member|array|null $member Member entity, array with timezone, or null
     * @param string|null $default Default timezone if member has none (overrides app default)
     * @return string Valid timezone identifier
     */
    public static function getUserTimezone($member = null, ?string $default = null): string
    {
        // Try member timezone
        if ($member !== null) {
            $timezone = null;

            if (is_object($member) && isset($member->timezone)) {
                $timezone = $member->timezone;
            } elseif (is_array($member) && isset($member['timezone'])) {
                $timezone = $member['timezone'];
            }

            if (!empty($timezone) && self::isValidTimezone($timezone)) {
                return $timezone;
            }
        }

        // Try provided default
        if ($default !== null && self::isValidTimezone($default)) {
            return $default;
        }

        // Try application default
        $appTimezone = self::getAppTimezone();
        if (!empty($appTimezone)) {
            return $appTimezone;
        }

        // Final fallback to UTC
        return 'UTC';
    }

    /**
     * Get the timezone for a specific gathering/event
     *
     * Resolves timezone in priority order:
     * 1. Gathering's timezone field (event location)
     * 2. Member's timezone field
     * 3. Application default timezone
     * 4. UTC fallback
     *
     * This is useful for displaying event times in the event's local timezone
     * regardless of where the user is located.
     *
     * @param \App\Model\Entity\Gathering|array|null $gathering Gathering entity or array
     * @param \App\Model\Entity\Member|array|null $member Member entity or array
     * @param string|null $default Default timezone override
     * @return string Valid timezone identifier
     */
    public static function getGatheringTimezone($gathering = null, $member = null, ?string $default = null): string
    {
        // Try gathering timezone first (highest priority for event display)
        if ($gathering !== null) {
            $timezone = null;

            if (is_object($gathering) && isset($gathering->timezone)) {
                $timezone = $gathering->timezone;
            } elseif (is_array($gathering) && isset($gathering['timezone'])) {
                $timezone = $gathering['timezone'];
            }

            if (!empty($timezone) && self::isValidTimezone($timezone)) {
                return $timezone;
            }
        }

        // Fall back to user timezone resolution
        return self::getUserTimezone($member, $default);
    }

    /**
     * Get timezone for display purposes with context awareness
     *
     * This method intelligently determines which timezone to use based on context:
     * - If a gathering/event is provided, use its timezone
     * - Otherwise, use the user's timezone
     *
     * @param \App\Model\Entity\Gathering|array|null $gathering Gathering entity or array
     * @param \App\Model\Entity\Member|array|null $member Member entity or array
     * @param string|null $default Default timezone override
     * @return string Valid timezone identifier
     */
    public static function getContextTimezone($gathering = null, $member = null, ?string $default = null): string
    {
        return self::getGatheringTimezone($gathering, $member, $default);
    }

    /**
     * Get application default timezone from AppSettings
     *
     * @return string|null Application default timezone or null
     */
    public static function getAppTimezone(): ?string
    {
        // Return cached value if available
        if (self::$appTimezoneCache !== null) {
            return self::$appTimezoneCache;
        }

        // Try to get from AppSettings
        try {
            $timezone = StaticHelpers::getAppSetting('KMP.DefaultTimezone', 'America/Chicago');

            if (!empty($timezone) && self::isValidTimezone($timezone)) {
                self::$appTimezoneCache = $timezone;
                return $timezone;
            }
        } catch (\Exception $e) {
            // AppSettings not available yet (e.g., during installation)
            // Fall through to null
        }

        return null;
    }

    /**
     * Convert a UTC datetime to user's timezone (or gathering's timezone if provided)
     *
     * @param \Cake\I18n\DateTime|string|null $datetime UTC datetime
     * @param \App\Model\Entity\Member|array|null $member Member entity or array
     * @param string|null $fallbackTimezone Fallback timezone if member has none
     * @param \App\Model\Entity\Gathering|array|null $gathering Optional gathering for event timezone priority
     * @return \Cake\I18n\DateTime|null Datetime in user's timezone or null if input is null
     */
    public static function toUserTimezone(
        $datetime,
        $member = null,
        ?string $fallbackTimezone = null,
        $gathering = null
    ): ?DateTime {
        if ($datetime === null) {
            return null;
        }

        // Ensure we have a DateTime object
        if (!($datetime instanceof DateTime)) {
            $datetime = new DateTime($datetime);
        }

        // Get timezone based on context (gathering takes priority if provided)
        if ($gathering !== null) {
            $timezone = self::getGatheringTimezone($gathering, $member, $fallbackTimezone);
        } else {
            $timezone = self::getUserTimezone($member, $fallbackTimezone);
        }

        // Convert to target timezone and return as DateTime
        return $datetime->setTimezone(new DateTimeZone($timezone));
    }

    /**
     * Convert a datetime from a specific timezone to UTC
     *
     * Used when receiving user input that's in their local timezone
     *
     * @param \Cake\I18n\DateTime|string|null $datetime Datetime in source timezone this field should not be timezone aware
     * as it should be a user input.
     * @param string|null $sourceTimezone Source timezone (defaults to user timezone)
     * @param \App\Model\Entity\Member|array|null $member Member for timezone resolution
     * @return \Cake\I18n\DateTime|null Datetime converted to UTC or null if input is null
     */
    public static function toUtc($datetime, ?string $sourceTimezone = null, $member = null): ?DateTime
    {
        if ($datetime === null) {
            return null;
        }

        // Resolve source timezone
        if ($sourceTimezone === null) {
            $sourceTimezone = self::getUserTimezone($member);
        } elseif (!self::isValidTimezone($sourceTimezone)) {
            $sourceTimezone = self::getUserTimezone($member);
        }

        // If datetime is a string, parse it in the source timezone
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime, new DateTimeZone($sourceTimezone));
        } elseif ($datetime instanceof DateTime) {
            // If it's already a DateTime object, ensure it has the correct source timezone
            $datetime = new DateTime($datetime->format('Y-m-d H:i:s'), new DateTimeZone($sourceTimezone));
        }

        // Convert to UTC and return
        return $datetime->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Convert datetime between timezones
     *
     * Note: DateTime instances retain their original timezone (are cloned and preserved),
     * while strings are parsed using $fromTimezone.
     *
     * @param \Cake\I18n\DateTime|string $datetime Source datetime
     * @param string $fromTimezone Source timezone (only used when $datetime is a string)
     * @param string $toTimezone Target timezone
     * @return \Cake\I18n\DateTime Converted datetime
     * @throws \InvalidArgumentException If timezone is invalid
     */
    public static function convertBetweenTimezones($datetime, string $fromTimezone, string $toTimezone): DateTime
    {
        if (!self::isValidTimezone($fromTimezone)) {
            throw new InvalidArgumentException("Invalid source timezone: {$fromTimezone}");
        }

        if (!self::isValidTimezone($toTimezone)) {
            throw new InvalidArgumentException("Invalid target timezone: {$toTimezone}");
        }

        // Parse datetime - preserve timezone if already a DateTime, use $fromTimezone for strings
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime, new DateTimeZone($fromTimezone));
        } elseif ($datetime instanceof DateTime) {
            // Clone to preserve the original DateTime's timezone information
            $datetime = clone $datetime;
        }

        // Convert to target timezone
        return $datetime->setTimezone(new DateTimeZone($toTimezone));
    }

    /**
     * Format a datetime for display in user's timezone
     *
     * @param \Cake\I18n\DateTime|string|null $datetime UTC datetime
     * @param \App\Model\Entity\Member|array|null $member Member entity
     * @param string $format PHP date format string
     * @param bool $includeTimezone Include timezone abbreviation
     * @return string Formatted datetime string
     */
    public static function formatForDisplay(
        $datetime,
        $member = null,
        string $format = self::DISPLAY_DATETIME_FORMAT,
        bool $includeTimezone = false
    ): string {
        if ($datetime === null) {
            return '';
        }

        $converted = self::toUserTimezone($datetime, $member);

        if ($converted === null) {
            return '';
        }

        $formatted = $converted->format($format);

        if ($includeTimezone) {
            $timezone = self::getUserTimezone($member);
            $abbr = self::getTimezoneAbbreviation($converted, $timezone);
            $formatted .= ' ' . $abbr;
        }

        return $formatted;
    }

    /**
     * Format datetime with standard format
     *
     * @param \Cake\I18n\DateTime|null $datetime Datetime to format
     * @param string $format PHP date format string
     * @return string Formatted string
     */
    public static function formatDateTime($datetime, string $format = self::DEFAULT_DATETIME_FORMAT): string
    {
        if ($datetime === null) {
            return '';
        }

        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }

        return $datetime->format($format);
    }

    /**
     * Format date only (no time)
     *
     * @param \Cake\I18n\DateTime|null $datetime Datetime to format
     * @param string $format PHP date format string
     * @return string Formatted date string
     */
    public static function formatDate($datetime, string $format = self::DISPLAY_DATE_FORMAT): string
    {
        return self::formatDateTime($datetime, $format);
    }

    /**
     * Format time only (no date)
     *
     * @param \Cake\I18n\DateTime|null $datetime Datetime to format
     * @param string $format PHP date format string
     * @return string Formatted time string
     */
    public static function formatTime($datetime, string $format = self::DISPLAY_TIME_FORMAT): string
    {
        return self::formatDateTime($datetime, $format);
    }

    /**
     * Get timezone abbreviation (e.g., CDT, EST, PST)
     *
     * @param \Cake\I18n\DateTime|string $datetime Datetime in the timezone or string to convert
     * @param string $timezone Timezone identifier
     * @return string Timezone abbreviation
     */
    public static function getTimezoneAbbreviation(DateTime|string $datetime, string $timezone): string
    {
        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        }

        $dt = $datetime->setTimezone(new DateTimeZone($timezone));
        return $dt->format('T');
    }

    /**
     * Validate if a string is a valid timezone identifier
     *
     * @param string $timezone Timezone identifier to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidTimezone(string $timezone): bool
    {
        try {
            new DateTimeZone($timezone);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get list of all valid PHP timezones
     *
     * @param bool $grouped Group by region
     * @return array List of timezones
     */
    public static function getTimezoneList(bool $grouped = false): array
    {
        // Return cached list if available
        if (self::$timezoneListCache !== null && !$grouped) {
            return self::$timezoneListCache;
        }

        $identifiers = DateTimeZone::listIdentifiers();

        if (!$grouped) {
            $list = array_combine($identifiers, $identifiers);
            self::$timezoneListCache = $list;
            return $list;
        }

        // Group by region
        $groupedList = [];
        foreach ($identifiers as $identifier) {
            $parts = explode('/', $identifier, 2);
            $region = $parts[0];
            $groupedList[$region][$identifier] = $identifier;
        }

        return $groupedList;
    }

    /**
     * Get list of common timezones for UI dropdowns
     *
     * @return array Common timezone identifiers with display names
     */
    public static function getCommonTimezones(): array
    {
        return self::COMMON_TIMEZONES;
    }

    /**
     * Get timezone offset in hours
     *
     * @param string $timezone Timezone identifier
     * @param \Cake\I18n\DateTime|null $datetime Optional datetime (for DST calculation)
     * @return float Offset in hours (e.g., -6.0 for CST, -5.0 for CDT)
     */
    public static function getTimezoneOffset(string $timezone, ?DateTime $datetime = null): float
    {
        if (!self::isValidTimezone($timezone)) {
            return 0.0;
        }

        $dt = $datetime ?? new DateTime();
        $tz = new DateTimeZone($timezone);
        $offset = $tz->getOffset($dt->setTimezone($tz));

        return $offset / 3600; // Convert seconds to hours
    }

    /**
     * Clear cached values (useful for testing)
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$appTimezoneCache = null;
        self::$timezoneListCache = null;
    }
}
