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
 * Implements RFC 5545 (iCalendar) format
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

        // Begin event
        $lines[] = 'BEGIN:VEVENT';

        // Unique identifier for this event
        $uid = 'gathering-' . $gathering->id . '@' . ($_SERVER['HTTP_HOST'] ?? 'kmp.local');
        $lines[] = 'UID:' . $this->escapeText($uid);

        // Timestamps
        $now = DateTime::now();
        $lines[] = 'DTSTAMP:' . $this->formatDateTime($now);

        // Event dates
        // For multi-day events, use DATE format (all-day event)
        // For single-day events, use DATETIME format
        if ($gathering->is_multi_day) {
            // All-day event format (DATE value type)
            // Note: End date is exclusive in iCalendar, so we add 1 day
            $endDate = $gathering->end_date->modify('+1 day');
            $lines[] = 'DTSTART;VALUE=DATE:' . $gathering->start_date->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $endDate->format('Ymd');
        } else {
            // Single day event - use full date-time format
            // Set to 9 AM start, 9 PM end as defaults
            // Create DateTime instances from the date values
            $startDateTime = new DateTime($gathering->start_date->format('Y-m-d') . ' 09:00:00');
            $endDateTime = new DateTime($gathering->end_date->format('Y-m-d') . ' 21:00:00');
            $lines[] = 'DTSTART:' . $this->formatDateTime($startDateTime);
            $lines[] = 'DTEND:' . $this->formatDateTime($endDateTime);
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
}