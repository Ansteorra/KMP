<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Gathering;
use Cake\I18n\DateTime;

/**
 * iCalendar Service
 *
 * Generates iCalendar (.ics) files for gatherings that can be imported into
 * various calendar applications (Google Calendar, Outlook, iOS Calendar, etc.)
 *
 * Implements RFC 5545 (iCalendar) format.
 * Supports single-event downloads and multi-event subscription feeds.
 */
class ICalendarService
{
    /**
     * Generate iCalendar content for a gathering
     *
     * @param \App\Model\Entity\Gathering $gathering The gathering to create calendar entry for
     * @param string|null $baseUrl Optional base URL for the event (for public links)
     * @return string iCalendar formatted content
     */
    public function generateICalendar(Gathering $gathering, ?string $baseUrl = null): string
    {
        $lines = [];

        // Begin calendar
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//KMP//Gathering Calendar//EN';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';

        // Determine timezone for the event
        // Priority: gathering timezone > UTC fallback
        $timezone = !empty($gathering->timezone) ? $gathering->timezone : 'UTC';

        // Add VTIMEZONE component for non-UTC timezones
        if ($timezone !== 'UTC') {
            $lines = array_merge($lines, $this->generateVTimezone($timezone));
        }

        // Begin event
        $lines[] = 'BEGIN:VEVENT';

        // Unique identifier for this event
        $uid = 'gathering-' . $gathering->id . '@' . ($_SERVER['HTTP_HOST'] ?? 'kmp.local');
        $lines[] = 'UID:' . $this->escapeText($uid);

        // Timestamps
        $now = DateTime::now();
        $lines[] = 'DTSTAMP:' . $this->formatDateTime($now);

        // Convert UTC stored dates to gathering's timezone for proper display
        $startInTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
        $endInTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);

        // Event dates - use full date-time format with timezone
        if ($timezone !== 'UTC') {
            $lines[] = 'DTSTART;TZID=' . $timezone . ':' . $startInTz->format('Ymd\THis');
            $lines[] = 'DTEND;TZID=' . $timezone . ':' . $endInTz->format('Ymd\THis');
        } else {
            // For UTC, use the Z suffix format
            $lines[] = 'DTSTART:' . $this->formatDateTime($gathering->start_date);
            $lines[] = 'DTEND:' . $this->formatDateTime($gathering->end_date);
        }

        // Event title
        $lines[] = 'SUMMARY:' . $this->escapeText($gathering->name);

        // Description
        $description = $this->buildDescription($gathering, $baseUrl);
        $lines[] = 'DESCRIPTION:' . $this->escapeText($description);

        // Location
        if (!empty($gathering->location)) {
            $lines[] = 'LOCATION:' . $this->escapeText($gathering->location);

            // Add geographic coordinates if available
            if (!empty($gathering->latitude) && !empty($gathering->longitude)) {
                $lines[] = 'GEO:' . $gathering->latitude . ';' . $gathering->longitude;
            }
        }

        // URL to the gathering view
        if ($baseUrl) {
            $lines[] = 'URL:' . $baseUrl;
        }

        // Status
        $lines[] = 'STATUS:CONFIRMED';

        // Organizer (using branch name)
        if (!empty($gathering->branch)) {
            $organizerName = $this->escapeText($gathering->branch->name);
            $lines[] = 'ORGANIZER;CN=' . $organizerName . ':noreply@' . ($_SERVER['HTTP_HOST'] ?? 'kmp.local');
        }

        // Categories
        if (!empty($gathering->gathering_type)) {
            $lines[] = 'CATEGORIES:' . $this->escapeText($gathering->gathering_type->name);
        }

        // End event
        $lines[] = 'END:VEVENT';

        // End calendar
        $lines[] = 'END:VCALENDAR';

        // Join with CRLF as per RFC 5545
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Generate a multi-event iCalendar feed for calendar subscription.
     *
     * @param iterable<\App\Model\Entity\Gathering> $gatherings Gatherings to include
     * @param string $calendarName Display name for the calendar feed
     * @param string|null $baseUrl Base URL for building event links
     * @return string iCalendar formatted content with multiple VEVENTs
     */
    public function generateFeed(iterable $gatherings, string $calendarName, ?string $baseUrl = null): string
    {
        $lines = [];

        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//KMP//Gathering Calendar//EN';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . $this->escapeText($calendarName);
        // Hint to calendar apps: refresh every 6 hours
        $lines[] = 'REFRESH-INTERVAL;VALUE=DURATION:PT6H';
        $lines[] = 'X-PUBLISHED-TTL:PT6H';

        // Collect unique timezones across all events
        $timezones = [];
        $events = [];
        foreach ($gatherings as $gathering) {
            $tz = !empty($gathering->timezone) ? $gathering->timezone : 'UTC';
            if ($tz !== 'UTC' && !isset($timezones[$tz])) {
                $timezones[$tz] = $this->generateVTimezone($tz);
            }
            $events[] = $this->generateVEvent($gathering, $baseUrl);
        }

        // Add all VTIMEZONE components
        foreach ($timezones as $tzLines) {
            $lines = array_merge($lines, $tzLines);
        }

        // Add all VEVENT components
        foreach ($events as $eventLines) {
            $lines = array_merge($lines, $eventLines);
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Generate VEVENT lines for a single gathering.
     *
     * @param \App\Model\Entity\Gathering $gathering The gathering
     * @param string|null $baseUrl Base URL for event link
     * @return array<string> iCalendar lines for one VEVENT
     */
    protected function generateVEvent(Gathering $gathering, ?string $baseUrl = null): array
    {
        $lines = [];
        $timezone = !empty($gathering->timezone) ? $gathering->timezone : 'UTC';

        $lines[] = 'BEGIN:VEVENT';

        $uid = 'gathering-' . $gathering->id . '@' . ($_SERVER['HTTP_HOST'] ?? 'kmp.local');
        $lines[] = 'UID:' . $this->escapeText($uid);

        $now = DateTime::now();
        $lines[] = 'DTSTAMP:' . $this->formatDateTime($now);

        $startInTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
        $endInTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);

        if ($timezone !== 'UTC') {
            $lines[] = 'DTSTART;TZID=' . $timezone . ':' . $startInTz->format('Ymd\THis');
            $lines[] = 'DTEND;TZID=' . $timezone . ':' . $endInTz->format('Ymd\THis');
        } else {
            $lines[] = 'DTSTART:' . $this->formatDateTime($gathering->start_date);
            $lines[] = 'DTEND:' . $this->formatDateTime($gathering->end_date);
        }

        $lines[] = 'SUMMARY:' . $this->escapeText($gathering->name);

        $eventUrl = null;
        if ($baseUrl && $gathering->public_page_enabled) {
            $eventUrl = $baseUrl . '/gatherings/public-landing/' . $gathering->public_id;
        }

        $description = $this->buildDescription($gathering, $eventUrl);
        $lines[] = 'DESCRIPTION:' . $this->escapeText($description);

        if (!empty($gathering->location)) {
            $lines[] = 'LOCATION:' . $this->escapeText($gathering->location);
            if (!empty($gathering->latitude) && !empty($gathering->longitude)) {
                $lines[] = 'GEO:' . $gathering->latitude . ';' . $gathering->longitude;
            }
        }

        if ($eventUrl) {
            $lines[] = 'URL:' . $eventUrl;
        }

        $lines[] = 'STATUS:CONFIRMED';

        if (!empty($gathering->branch)) {
            $organizerName = $this->escapeText($gathering->branch->name);
            $lines[] = 'ORGANIZER;CN=' . $organizerName . ':noreply@' . ($_SERVER['HTTP_HOST'] ?? 'kmp.local');
        }

        if (!empty($gathering->gathering_type)) {
            $lines[] = 'CATEGORIES:' . $this->escapeText($gathering->gathering_type->name);
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /**
     * Build event description from gathering details
     *
     * @param \App\Model\Entity\Gathering $gathering The gathering
     * @param string|null $baseUrl Optional URL to include
     * @return string Event description
     */
    protected function buildDescription(Gathering $gathering, ?string $baseUrl = null): string
    {
        $parts = [];

        // Gathering type
        if (!empty($gathering->gathering_type)) {
            $parts[] = 'Event Type: ' . $gathering->gathering_type->name;
        }

        // Branch
        if (!empty($gathering->branch)) {
            $parts[] = 'Hosted by: ' . $gathering->branch->name;
        }

        // Show formatted date/time in gathering's timezone
        if (!empty($gathering->timezone)) {
            $startInTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->start_date, null, null, $gathering);
            $endInTz = \App\KMP\TimezoneHelper::toUserTimezone($gathering->end_date, null, null, $gathering);

            $parts[] = '';
            $parts[] = 'Start: ' . $startInTz->format('l, F j, Y g:i A T');
            $parts[] = 'End: ' . $endInTz->format('l, F j, Y g:i A T');
            $parts[] = 'Timezone: ' . $gathering->timezone;
        }

        // Description
        if (!empty($gathering->description)) {
            $parts[] = '';
            $parts[] = strip_tags($gathering->description);
        }

        // Activities
        if (!empty($gathering->gathering_activities)) {
            $parts[] = '';
            $parts[] = 'Activities:';
            foreach ($gathering->gathering_activities as $activity) {
                $parts[] = '- ' . $activity->name;
            }
        }

        // Staff (Stewards)
        if (!empty($gathering->gathering_staff)) {
            $stewards = [];
            foreach ($gathering->gathering_staff as $staff) {
                if ($staff->is_steward && !empty($staff->member)) {
                    $stewards[] = $staff->member->sca_name;
                }
            }
            if (!empty($stewards)) {
                $parts[] = '';
                $parts[] = 'Event Steward(s): ' . implode(', ', $stewards);
            }
        }

        // Add URL if provided
        if ($baseUrl) {
            $parts[] = '';
            $parts[] = 'More information: ' . $baseUrl;
        }

        return implode("\n", $parts);
    }

    /**
     * Format DateTime to iCalendar format (UTC)
     *
     * @param \Cake\I18n\DateTime $dateTime DateTime to format
     * @return string Formatted datetime string (YYYYMMDDTHHMMSSZ)
     */
    protected function formatDateTime(DateTime $dateTime): string
    {
        // Convert to UTC for iCalendar standard
        $utc = $dateTime->setTimezone(new \DateTimeZone('UTC'));
        return $utc->format('Ymd\THis\Z');
    }

    /**
     * Escape text for iCalendar format
     *
     * Escapes special characters according to RFC 5545:
     * - Backslash (\) -> \\
     * - Semicolon (;) -> \;
     * - Comma (,) -> \,
     * - Newline (\n) -> \n
     *
     * Also implements line folding for lines longer than 75 octets
     *
     * @param string $text Text to escape
     * @return string Escaped and folded text
     */
    protected function escapeText(string $text): string
    {
        // Replace special characters
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace("\r\n", '\n', $text);
        $text = str_replace("\n", '\n', $text);
        $text = str_replace("\r", '\n', $text);

        // Implement line folding (lines should be max 75 octets)
        // We fold at 73 characters to account for CRLF
        return $this->foldLine($text);
    }

    /**
     * Fold long lines according to RFC 5545
     *
     * Lines longer than 75 octets should be split with CRLF followed by a space
     *
     * @param string $text Text to fold
     * @param int $maxLength Maximum line length (default 73)
     * @return string Folded text
     */
    protected function foldLine(string $text, int $maxLength = 73): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        $result = '';
        $line = '';

        // Split by escaped newlines to preserve them
        $parts = explode('\n', $text);

        foreach ($parts as $i => $part) {
            if ($i > 0) {
                $line .= '\n';
            }

            while (strlen($part) > 0) {
                $remaining = $maxLength - strlen($line);

                if (strlen($part) <= $remaining) {
                    $line .= $part;
                    $part = '';
                } else {
                    $chunk = substr($part, 0, $remaining);
                    $line .= $chunk;
                    $part = substr($part, $remaining);

                    // Add line with folding
                    $result .= $line . "\r\n ";
                    $line = '';
                }
            }
        }

        $result .= $line;

        return $result;
    }

    /**
     * Get appropriate filename for the calendar file
     *
     * @param \App\Model\Entity\Gathering $gathering The gathering
     * @return string Filename (without extension)
     */
    public function getFilename(Gathering $gathering): string
    {
        // Create a safe filename from the gathering name
        $name = preg_replace('/[^a-zA-Z0-9-_]/', '-', $gathering->name);
        $name = preg_replace('/-+/', '-', $name);
        $name = trim($name, '-');

        return strtolower($name) . '-' . $gathering->start_date->format('Y-m-d');
    }

    /**
     * Generate VTIMEZONE component for a given timezone
     *
     * This creates a simplified VTIMEZONE component for iCalendar files.
     * Modern calendar applications can usually handle TZID references without
     * the full VTIMEZONE definition, but including it ensures maximum compatibility.
     *
     * @param string $timezoneId IANA timezone identifier (e.g., 'America/Chicago')
     * @return array Array of iCalendar lines for the VTIMEZONE component
     */
    protected function generateVTimezone(string $timezoneId): array
    {
        $lines = [];

        try {
            $tz = new \DateTimeZone($timezoneId);

            // Get transitions for the current year and next year to handle DST properly
            $now = new \DateTime('now', $tz);
            $startOfYear = new \DateTime('first day of january this year', $tz);
            $endOfNextYear = new \DateTime('last day of december next year', $tz);

            $transitions = $tz->getTransitions(
                $startOfYear->getTimestamp(),
                $endOfNextYear->getTimestamp()
            );

            if (empty($transitions)) {
                // No transitions (no DST), create a simple STANDARD component
                $offset = $tz->getOffset($now);
                $offsetStr = $this->formatTimezoneOffset($offset);

                $lines[] = 'BEGIN:VTIMEZONE';
                $lines[] = 'TZID:' . $timezoneId;
                $lines[] = 'BEGIN:STANDARD';
                $lines[] = 'DTSTART:19700101T000000';
                $lines[] = 'TZOFFSETFROM:' . $offsetStr;
                $lines[] = 'TZOFFSETTO:' . $offsetStr;
                $lines[] = 'END:STANDARD';
                $lines[] = 'END:VTIMEZONE';
            } else {
                // Has DST transitions
                $lines[] = 'BEGIN:VTIMEZONE';
                $lines[] = 'TZID:' . $timezoneId;

                // Group transitions by DST status
                $standard = null;
                $daylight = null;

                foreach ($transitions as $i => $transition) {
                    if ($i === 0) {
                        continue; // Skip the first entry (it's a reference point)
                    }

                    $dt = new \DateTime($transition['time']);
                    $isDst = $transition['isdst'];
                    $offset = $transition['offset'];
                    $prevOffset = $transitions[$i - 1]['offset'];

                    if ($isDst && $daylight === null) {
                        $daylight = [
                            'dtstart' => $dt->format('Ymd\THis'),
                            'offsetfrom' => $this->formatTimezoneOffset($prevOffset),
                            'offsetto' => $this->formatTimezoneOffset($offset),
                            'tzname' => $transition['abbr']
                        ];
                    } elseif (!$isDst && $standard === null) {
                        $standard = [
                            'dtstart' => $dt->format('Ymd\THis'),
                            'offsetfrom' => $this->formatTimezoneOffset($prevOffset),
                            'offsetto' => $this->formatTimezoneOffset($offset),
                            'tzname' => $transition['abbr']
                        ];
                    }
                }

                // Add STANDARD component
                if ($standard) {
                    $lines[] = 'BEGIN:STANDARD';
                    $lines[] = 'DTSTART:' . $standard['dtstart'];
                    $lines[] = 'TZOFFSETFROM:' . $standard['offsetfrom'];
                    $lines[] = 'TZOFFSETTO:' . $standard['offsetto'];
                    $lines[] = 'TZNAME:' . $standard['tzname'];
                    $lines[] = 'END:STANDARD';
                }

                // Add DAYLIGHT component
                if ($daylight) {
                    $lines[] = 'BEGIN:DAYLIGHT';
                    $lines[] = 'DTSTART:' . $daylight['dtstart'];
                    $lines[] = 'TZOFFSETFROM:' . $daylight['offsetfrom'];
                    $lines[] = 'TZOFFSETTO:' . $daylight['offsetto'];
                    $lines[] = 'TZNAME:' . $daylight['tzname'];
                    $lines[] = 'END:DAYLIGHT';
                }

                $lines[] = 'END:VTIMEZONE';
            }
        } catch (\Exception $e) {
            // If timezone generation fails, return empty array
            // The calendar will fall back to UTC
            return [];
        }

        return $lines;
    }

    /**
     * Format timezone offset for iCalendar TZOFFSET* properties
     *
     * Converts seconds offset to ±HHMM format
     *
     * @param int $offsetSeconds Offset in seconds from UTC
     * @return string Formatted offset (e.g., '+0500', '-0600')
     */
    protected function formatTimezoneOffset(int $offsetSeconds): string
    {
        $hours = intdiv($offsetSeconds, 3600);
        $minutes = abs(intdiv($offsetSeconds % 3600, 60));

        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $absHours = abs($hours);

        return sprintf('%s%02d%02d', $sign, $absHours, $minutes);
    }
}
