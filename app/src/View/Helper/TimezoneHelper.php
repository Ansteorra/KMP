<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\KMP\TimezoneHelper as TzHelper;
use App\Model\Entity\Gathering;
use App\Model\Entity\Member;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use Cake\I18n\I18n;
use Cake\View\Helper;
use IntlDateFormatter;
use Locale;

/**
 * Timezone View Helper
 *
 * Provides template-friendly wrappers for timezone conversion and formatting.
 * This helper integrates with the TimezoneHelper service class to provide
 * easy-to-use methods for displaying dates and times in the user's timezone.
 *
 * ## Usage in Templates
 *
 * ### Basic Display
 * ```php
 * <!-- Display datetime in user's timezone -->
 * <?= $this->Timezone->format($gathering->start_date) ?>
* // Output: "March 15, 2025 9:00 AM"
*
*
<!-- With timezone abbreviation -->
* <?= $this->Timezone->format($gathering->start_date, null, true) ?>
* // Output: "March 15, 2025 9:00 AM CDT"
* ```
*
* ### Custom Formats
* ```php
*
<!-- Custom format string -->
* <?= $this->Timezone->format($gathering->start_date, 'l, F j, Y') ?>
* // Output: "Saturday, March 15, 2025"
*
*
<!-- Date only -->
* <?= $this->Timezone->date($gathering->start_date) ?>
* // Output: "March 15, 2025"
*
*
<!-- Time only -->
* <?= $this->Timezone->time($gathering->start_date) ?>
* // Output: "9:00 AM"
* ```
*
* ### Form Inputs
* ```php
*
<!-- Convert UTC to user timezone for datetime input -->
* <?= $this->Form->control('start_date', [
 *     'type' => 'datetime-local',
 *     'value' => $this->Timezone->forInput($entity->start_date)
 * ]) ?>
*
*
<!-- With custom format for input -->
* <?= $this->Form->control('start_date', [
 *     'type' => 'text',
 *     'value' => $this->Timezone->forInput($entity->start_date, 'Y-m-d\TH:i')
 * ]) ?>
* ```
*
* ### Displaying Ranges
* ```php
*
<!-- Date range -->
* <?= $this->Timezone->range($gathering->start_date, $gathering->end_date) ?>
* // Output: "March 15, 2025 9:00 AM - March 17, 2025 5:00 PM"
*
*
<!-- Smart range (same day shows time range only) -->
* <?= $this->Timezone->smartRange($activity->start_datetime, $activity->end_datetime) ?>
* // Output: "March 15, 2025 9:00 AM - 5:00 PM" (same day)
* // Output: "March 15, 2025 - March 16, 2025" (different days, date only)
* ```
*
* ### Timezone Information
* ```php
*
<!-- Show user's current timezone -->
* <p>Times shown in: <?= $this->Timezone->getUserTimezone() ?></p>
* // Output: "Times shown in: America/Chicago"
*
*
<!-- Show timezone abbreviation -->
* <p>Times shown in <?= $this->Timezone->getAbbreviation() ?></p>
* // Output: "Times shown in CDT"
* ```
*
* ## Integration with Current User
*
* The helper automatically uses the current authenticated user's timezone
* preference from the session/identity. You can override this:
*
* ```php
*
<!-- Use specific member's timezone -->
* <?= $this->Timezone->format($gathering->start_date, null, false, $otherMember) ?>
* ```
*
* ## Form Helper Integration
*
* When users submit forms with datetime values, convert back to UTC:
* ```php
* // In controller, before save:
* use App\KMP\TimezoneHelper;
*
* if ($this->request->is(['post', 'put'])) {
* $data = $this->request->getData();
*
* // Convert user's timezone input to UTC for storage
* $data['start_date'] = TimezoneHelper::toUtc(
* $data['start_date'],
* TimezoneHelper::getUserTimezone($this->Authentication->getIdentity())
* );
*
* $gathering = $this->Gatherings->patchEntity($gathering, $data);
* }
* ```
*
* @property \Cake\View\Helper\FormHelper $Form
* @see \App\KMP\TimezoneHelper Core timezone conversion logic
*/
class TimezoneHelper extends Helper
{
/**
* Helpers used by this helper
*
* @var array<string>
    */
    protected array $helpers = [];

    /**
    * Current user/member for timezone resolution
    *
    * @var \App\Model\Entity\Member|array|null
    */
    protected $currentUser = null;

    /**
    * Initialize helper
    *
    * @param array $config Configuration options
    * @return void
    */
    public function initialize(array $config): void
    {
    parent::initialize($config);

    // Try to get current user from view
    $this->currentUser = $this->getView()->get('currentUser') ??
    $this->getView()->get('identity');
    }

    /**
    * Format a datetime in the viewer's timezone.
    *
    * This method accepts a flexible argument list so older call signatures are still supported.
    * You may pass arguments in any order and by type they will be assigned as follows:
    *
    * - `string` → Custom PHP date format string (e.g., `'Y-m-d H:i'`)
    * - `bool` → Whether to append timezone abbreviation
    * - `\App\Model\Entity\Member` → Member context for timezone resolution
    * - `\App\Model\Entity\Gathering` → Gathering context for timezone resolution
    * - `int` (IntlDateFormatter constant) → Date/time style for localized formatting
    * - `array` → Options array with any of the keys: `format`, `includeTimezone`, `member`,
    * `gathering`, `intlDateFormat`, `intlTimeFormat`
    *
    * Examples:
    * ```php
    * $this->Timezone->format($datetime); // default display format
    * $this->Timezone->format($datetime, true); // include timezone abbreviation
    * $this->Timezone->format($datetime, $member, 'Y-m-d'); // member context + format string
    * $this->Timezone->format($datetime, $gathering, 'M j, Y'); // gathering context + format
    * $this->Timezone->format($datetime, null, null, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
    * ```
    *
    * @param \Cake\I18n\DateTime|\Cake\I18n\FrozenTime|string|null $datetime UTC datetime/value to format
    * @param mixed ...$args Additional context/formatting options
    * @return string
    */
    public function format($datetime, ...$args): string
    {
    if ($datetime === null) {
    return '';
    }

    $format = null;
    $includeTimezone = false;
    $member = null;
    $gathering = null;
    $intlDateFormat = null;
    $intlTimeFormat = null;

    foreach ($args as $arg) {
    if ($arg === null) {
    continue;
    }

    if (is_bool($arg)) {
    $includeTimezone = $arg;
    continue;
    }

    if (is_int($arg)) {
    if ($intlDateFormat === null) {
    $intlDateFormat = $arg;
    continue;
    }

    if ($intlTimeFormat === null) {
    $intlTimeFormat = $arg;
    continue;
    }
    }

    if ($this->isGatheringContext($arg)) {
    $gathering = $arg;
    continue;
    }

    if ($this->isMemberContext($arg)) {
    $member = $arg;
    continue;
    }

    if (is_array($arg)) {
    if (isset($arg['format'])) {
    $format = $arg['format'];
    }

    if (isset($arg['includeTimezone'])) {
    $includeTimezone = (bool)$arg['includeTimezone'];
    }

    if (isset($arg['member'])) {
    $member = $arg['member'];
    }

    if (isset($arg['gathering'])) {
    $gathering = $arg['gathering'];
    }

    if (isset($arg['intlDateFormat'])) {
    $intlDateFormat = $arg['intlDateFormat'];
    }

    if (isset($arg['intlTimeFormat'])) {
    $intlTimeFormat = $arg['intlTimeFormat'];
    }

    continue;
    }

    if (is_string($arg)) {
    $format = $arg;
    }
    }

    $member = $member ?? $this->currentUser;

    $converted = TzHelper::toUserTimezone($datetime, $member, null, $gathering);

    if ($intlDateFormat !== null || $intlTimeFormat !== null) {
    $locale = $this->getView()->getRequest()?->getAttribute('locale') ?? I18n::getLocale() ?? Locale::getDefault();
    $formatter = new IntlDateFormatter(
    $locale,
    $intlDateFormat ?? IntlDateFormatter::FULL,
    $intlTimeFormat ?? IntlDateFormatter::NONE,
    $converted->getTimezone()->getName()
    );
    $formatted = $formatter->format($converted);
    } else {
    $format = $format ?? TzHelper::DISPLAY_DATETIME_FORMAT;
    $formatted = $converted->format($format);
    }

    if ($includeTimezone) {
    $timezone = $gathering !== null
    ? TzHelper::getGatheringTimezone($gathering, $member)
    : TzHelper::getUserTimezone($member);
    $abbr = TzHelper::getTimezoneAbbreviation($converted, $timezone);
    if ($abbr !== '') {
    $formatted .= ' ' . $abbr;
    }
    }

    return $formatted;
    }

    /**
    * Determine if the provided context should be treated as a member reference.
    *
    * @param mixed $context Potential member context
    * @return bool
    */
    protected function isMemberContext(mixed $context): bool
    {
    if ($context instanceof Member) {
    return true;
    }

    if ($context instanceof EntityInterface && $context->getSource() === 'Members') {
    return true;
    }

    return false;
    }

    /**
    * Determine if the provided context should be treated as a gathering reference.
    *
    * @param mixed $context Potential gathering context
    * @return bool
    */
    protected function isGatheringContext(mixed $context): bool
    {
    if ($context instanceof Gathering) {
    return true;
    }

    if ($context instanceof EntityInterface && $context->getSource() === 'Gatherings') {
    return true;
    }

    return false;
    }

    /**
    * Format date only (no time)
    *
    * @param \Cake\I18n\DateTime|string|null $datetime UTC datetime to format
    * @param string|null $format PHP date format (null = default)
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @param \App\Model\Entity\Gathering|array|null $gathering Gathering for event timezone priority
    * @return string Formatted date string
    */
    public function date($datetime, ?string $format = null, $member = null, $gathering = null): string
    {
    if ($datetime === null) {
    return '';
    }

    $member = $member ?? $this->currentUser;
    $format = $format ?? TzHelper::DISPLAY_DATE_FORMAT;

    $converted = TzHelper::toUserTimezone($datetime, $member, null, $gathering);
    return TzHelper::formatDate($converted, $format);
    }

    /**
    * Format time only (no date)
    *
    * @param \Cake\I18n\DateTime|string|null $datetime UTC datetime to format
    * @param string|null $format PHP time format (null = default)
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @param \App\Model\Entity\Gathering|array|null $gathering Gathering for event timezone priority
    * @return string Formatted time string
    */
    public function time($datetime, ?string $format = null, $member = null, $gathering = null): string
    {
    if ($datetime === null) {
    return '';
    }

    $member = $member ?? $this->currentUser;
    $format = $format ?? TzHelper::DISPLAY_TIME_FORMAT;

    $converted = TzHelper::toUserTimezone($datetime, $member, null, $gathering);
    return TzHelper::formatTime($converted, $format);
    }

    /**
    * Format datetime for form input (datetime-local format)
    *
    * Converts UTC to user's timezone (or gathering's timezone) in HTML5 datetime-local format (Y-m-d\TH:i)
    *
    * @param \Cake\I18n\DateTime|string|null $datetime UTC datetime
    * @param string|null $format Format string (default: Y-m-d\TH:i for datetime-local)
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @param \App\Model\Entity\Gathering|array|null $gathering Gathering for event timezone priority
    * @return string Formatted datetime for input field
    */
    public function forInput($datetime, ?string $format = null, $member = null, $gathering = null): string
    {
    if ($datetime === null) {
    return '';
    }

    $member = $member ?? $this->currentUser;
    $format = $format ?? 'Y-m-d\TH:i'; // HTML5 datetime-local format

    $converted = TzHelper::toUserTimezone($datetime, $member, null, $gathering);
    return $converted->format($format);
    }

    /**
    * Format a date range
    *
    * @param \Cake\I18n\DateTime|string|null $start Start datetime
    * @param \Cake\I18n\DateTime|string|null $end End datetime
    * @param string $separator Separator between dates (default: " - ")
    * @param string|null $format Format string for each date
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @return string Formatted date range
    */
    public function range(
    $start,
    $end,
    string $separator = ' - ',
    ?string $format = null,
    $member = null
    ): string {
    if ($start === null && $end === null) {
    return '';
    }

    $member = $member ?? $this->currentUser;
    $format = $format ?? TzHelper::DISPLAY_DATETIME_FORMAT;

    $startFormatted = $start ? TzHelper::formatForDisplay($start, $member, $format) : '';
    $endFormatted = $end ? TzHelper::formatForDisplay($end, $member, $format) : '';

    if ($startFormatted && $endFormatted) {
    return $startFormatted . $separator . $endFormatted;
    }

    return $startFormatted ?: $endFormatted;
    }

    /**
    * Smart date range formatting
    *
    * If same day: "March 15, 2025 9:00 AM - 5:00 PM"
    * If different days: "March 15, 2025 - March 17, 2025"
    *
    * @param \Cake\I18n\DateTime|string|null $start Start datetime
    * @param \Cake\I18n\DateTime|string|null $end End datetime
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @return string Smart formatted date range
    */
    public function smartRange($start, $end, $member = null): string
    {
    if ($start === null || $end === null) {
    return $this->format($start ?? $end, null, false, $member);
    }

    $member = $member ?? $this->currentUser;

    // Convert both to user timezone
    $startConverted = TzHelper::toUserTimezone($start, $member);
    $endConverted = TzHelper::toUserTimezone($end, $member);

    // Check if same day
    if ($startConverted->format('Y-m-d') === $endConverted->format('Y-m-d')) {
    // Same day: show full date with time range
    return sprintf(
    '%s %s - %s',
    $startConverted->format(TzHelper::DISPLAY_DATE_FORMAT),
    $startConverted->format(TzHelper::DISPLAY_TIME_FORMAT),
    $endConverted->format(TzHelper::DISPLAY_TIME_FORMAT)
    );
    } else {
    // Different days: show date range only
    return sprintf(
    '%s - %s',
    $startConverted->format(TzHelper::DISPLAY_DATE_FORMAT),
    $endConverted->format(TzHelper::DISPLAY_DATE_FORMAT)
    );
    }
    }

    /**
    * Get the current user's timezone
    *
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @return string Timezone identifier (e.g., "America/Chicago")
    */
    public function getUserTimezone($member = null): string
    {
    $member = $member ?? $this->currentUser;
    return TzHelper::getUserTimezone($member);
    }

    /**
    * Get timezone abbreviation for current user or specific timezone
    *
    * @param \Cake\I18n\DateTime|\Cake\I18n\Date|string|null $datetime Datetime for DST calculation (null = now)
    * @param string|null $timezone Specific timezone identifier (null = use member/user timezone)
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @return string Timezone abbreviation (e.g., "CDT", "EST")
    */
    public function getAbbreviation($datetime = null, ?string $timezone = null, $member = null): string
    {
    // Convert Date to DateTime if needed
    if ($datetime instanceof \Cake\I18n\Date) {
    $datetime = new DateTime($datetime->format('Y-m-d') . ' 12:00:00');
    } elseif (is_string($datetime)) {
    $datetime = new DateTime($datetime);
    } elseif ($datetime === null) {
    $datetime = DateTime::now();
    }

    // If timezone is provided directly, use it
    if ($timezone !== null) {
    return TzHelper::getTimezoneAbbreviation($datetime, $timezone);
    }

    // Otherwise, use member's timezone
    $member = $member ?? $this->currentUser;
    $timezone = TzHelper::getUserTimezone($member);

    return TzHelper::getTimezoneAbbreviation($datetime, $timezone);
    }

    /**
    * Get timezone offset in hours
    *
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @param \Cake\I18n\DateTime|null $datetime Datetime for DST calculation (null = now)
    * @return float Offset in hours (e.g., -6.0 for CST, -5.0 for CDT)
    */
    public function getOffset($member = null, ?DateTime $datetime = null): float
    {
    $member = $member ?? $this->currentUser;
    $timezone = TzHelper::getUserTimezone($member);
    $datetime = $datetime ?? DateTime::now();

    return TzHelper::getTimezoneOffset($timezone, $datetime);
    }

    /**
    * Display a timezone notice for the user
    *
    * Outputs a small notice showing the user which timezone is being used
    *
    * @param string $class CSS class for the notice
    * @param \App\Model\Entity\Member|array|null $member Specific member (null = current user)
    * @return string HTML for timezone notice
    */
    public function notice(string $class = 'text-muted small', $member = null): string
    {
    $member = $member ?? $this->currentUser;
    $timezone = TzHelper::getUserTimezone($member);
    $abbr = $this->getAbbreviation(null, $member);

    return sprintf(
    '<div class="%s"><i class="bi bi-clock"></i> Times shown in %s (%s)</div>',
    h($class),
    h($timezone),
    h($abbr)
    );
    }

    /**
    * Create a timezone selector dropdown for forms
    *
    * @param bool $commonOnly Show only common timezones (default: true)
    * @return array Options array for Form->control()
    */
    public function getTimezoneOptions(bool $commonOnly = true): array
    {
    if ($commonOnly) {
    return TzHelper::getCommonTimezones();
    }

    return TzHelper::getTimezoneList();
    }
    }