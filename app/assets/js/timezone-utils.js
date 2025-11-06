/**
 * KMP Timezone Utilities
 *
 * Client-side timezone handling for the KMP application. Provides utilities for
 * detecting user timezone, formatting dates/times, and converting between timezones
 * for datetime inputs and displays.
 *
 * ## Features
 * - Automatic timezone detection
 * - UTC to local timezone conversion for display
 * - Local to UTC conversion for form submission
 * - Datetime formatting with timezone awareness
 * - Integration with HTML5 datetime-local inputs
 *
 * ## Usage Examples
 *
 * ### Basic Timezone Detection
 * ```javascript
 * // Detect user's browser timezone
 * const userTz = KMP_Timezone.detectTimezone();
 * console.log(userTz); // "America/Chicago"
 *
 * // Get timezone from data attribute or detect
 * const tz = KMP_Timezone.getTimezone(element);
 * ```
 *
 * ### Formatting Dates for Display
 * ```javascript
 * // Format UTC datetime for display in user's timezone
 * const utcString = "2025-03-15T14:30:00Z";
 * const displayed = KMP_Timezone.formatDateTime(utcString, "America/Chicago");
 * // "3/15/2025, 9:30:00 AM"
 *
 * // Custom format
 * const formatted = KMP_Timezone.formatDateTime(utcString, "America/Chicago", {
 *     dateStyle: 'full',
 *     timeStyle: 'short'
 * });
 * // "Saturday, March 15, 2025 at 9:30 AM"
 * ```
 *
 * ### Form Input Handling
 * ```javascript
 * // Convert UTC to local time for datetime-local input
 * const utcDate = "2025-03-15T14:30:00Z";
 * const inputValue = KMP_Timezone.toLocalInput(utcDate, "America/Chicago");
 * // "2025-03-15T09:30"
 *
 * // Convert local datetime-local input to UTC for submission
 * const localInput = "2025-03-15T09:30";
 * const utcValue = KMP_Timezone.toUTC(localInput, "America/Chicago");
 * // "2025-03-15T14:30:00.000Z"
 * ```
 *
 * ### Auto-Converting Datetime Inputs
 * ```html
 * <!-- Add data attributes to auto-convert inputs -->
 * <input type="datetime-local" 
 *        name="start_date"
 *        data-timezone="America/Chicago"
 *        data-utc-value="2025-03-15T14:30:00Z"
 *        data-controller="timezone-input">
 * ```
 *
 * ## Integration with Server
 *
 * Server always stores in UTC, client converts for display/input:
 * 1. Server sends UTC datetime: "2025-03-15T14:30:00Z"
 * 2. Client converts to local for input: "2025-03-15T09:30" (Chicago time)
 * 3. User edits: "2025-03-15T10:00"
 * 4. Client converts back to UTC: "2025-03-15T15:00:00Z"
 * 5. Server stores UTC value
 *
 * @namespace KMP_Timezone
 */
const KMP_Timezone = {
    /**
     * Detect user's timezone from browser
     *
     * Uses Intl.DateTimeFormat to get IANA timezone identifier
     *
     * @returns {string} IANA timezone identifier (e.g., "America/Chicago")
     */
    detectTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch (e) {
            console.warn('Could not detect timezone, defaulting to UTC', e);
            return 'UTC';
        }
    },

    /**
     * Get timezone from element data attribute or detect from browser
     *
     * @param {HTMLElement} element - Element with optional data-timezone attribute
     * @returns {string} Timezone identifier
     */
    getTimezone(element) {
        if (element && element.dataset && element.dataset.timezone) {
            return element.dataset.timezone;
        }
        return this.detectTimezone();
    },

    /**
     * Convert UTC datetime to user's timezone for display
     *
     * @param {string|Date} utcDateTime - UTC datetime string or Date object
     * @param {string} timezone - Target timezone (default: detected timezone)
     * @param {object} options - Intl.DateTimeFormat options
     * @returns {string} Formatted datetime string in local timezone
     */
    formatDateTime(utcDateTime, timezone = null, options = null) {
        if (!utcDateTime) return '';

        timezone = timezone || this.detectTimezone();
        
        // Parse the datetime
        const date = typeof utcDateTime === 'string' ? new Date(utcDateTime) : utcDateTime;
        
        if (isNaN(date.getTime())) {
            console.error('Invalid datetime:', utcDateTime);
            return '';
        }

        // Default options
        const defaultOptions = {
            timeZone: timezone,
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };

        const formatOptions = options ? { ...defaultOptions, ...options } : defaultOptions;

        try {
            return new Intl.DateTimeFormat('en-US', formatOptions).format(date);
        } catch (e) {
            console.error('Error formatting datetime:', e);
            return date.toLocaleString();
        }
    },

    /**
     * Format date only (no time)
     *
     * @param {string|Date} utcDateTime - UTC datetime string or Date object
     * @param {string} timezone - Target timezone (default: detected timezone)
     * @param {object} options - Intl.DateTimeFormat options
     * @returns {string} Formatted date string
     */
    formatDate(utcDateTime, timezone = null, options = null) {
        if (!utcDateTime) return '';

        timezone = timezone || this.detectTimezone();
        const date = typeof utcDateTime === 'string' ? new Date(utcDateTime) : utcDateTime;

        const defaultOptions = {
            timeZone: timezone,
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };

        const formatOptions = options ? { ...defaultOptions, ...options } : defaultOptions;

        try {
            return new Intl.DateTimeFormat('en-US', formatOptions).format(date);
        } catch (e) {
            console.error('Error formatting date:', e);
            return date.toLocaleDateString();
        }
    },

    /**
     * Format time only (no date)
     *
     * @param {string|Date} utcDateTime - UTC datetime string or Date object
     * @param {string} timezone - Target timezone (default: detected timezone)
     * @param {object} options - Intl.DateTimeFormat options
     * @returns {string} Formatted time string
     */
    formatTime(utcDateTime, timezone = null, options = null) {
        if (!utcDateTime) return '';

        timezone = timezone || this.detectTimezone();
        const date = typeof utcDateTime === 'string' ? new Date(utcDateTime) : utcDateTime;

        const defaultOptions = {
            timeZone: timezone,
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        };

        const formatOptions = options ? { ...defaultOptions, ...options } : defaultOptions;

        try {
            return new Intl.DateTimeFormat('en-US', formatOptions).format(date);
        } catch (e) {
            console.error('Error formatting time:', e);
            return date.toLocaleTimeString();
        }
    },

    /**
     * Convert UTC datetime to HTML5 datetime-local format in user's timezone
     *
     * For use with datetime-local inputs
     *
     * @param {string|Date} utcDateTime - UTC datetime
     * @param {string} timezone - Target timezone (default: detected timezone)
     * @returns {string} Datetime in YYYY-MM-DDTHH:mm format (local time)
     */
    toLocalInput(utcDateTime, timezone = null) {
        if (!utcDateTime) return '';

        timezone = timezone || this.detectTimezone();
        const date = typeof utcDateTime === 'string' ? new Date(utcDateTime) : utcDateTime;

        if (isNaN(date.getTime())) {
            console.error('Invalid datetime for input:', utcDateTime);
            return '';
        }

        try {
            // Get date parts in the target timezone
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });

            const parts = formatter.formatToParts(date);
            const dateParts = {};
            
            parts.forEach(part => {
                if (part.type !== 'literal') {
                    dateParts[part.type] = part.value;
                }
            });

            // Format as YYYY-MM-DDTHH:mm
            return `${dateParts.year}-${dateParts.month}-${dateParts.day}T${dateParts.hour}:${dateParts.minute}`;
        } catch (e) {
            console.error('Error converting to local input:', e);
            return '';
        }
    },

    /**
     * Convert datetime-local input value (local time) to UTC
     *
     * For form submission - converts user's local input to UTC for storage
     *
     * @param {string} localDateTime - Datetime in YYYY-MM-DDTHH:mm format (local time)
     * @param {string} timezone - Source timezone (default: detected timezone)
     * @returns {string} ISO 8601 UTC datetime string
     */
    toUTC(localDateTime, timezone = null) {
        if (!localDateTime) return '';

        timezone = timezone || this.detectTimezone();

        try {
            // Parse the local datetime string
            // Format: YYYY-MM-DDTHH:mm or YYYY-MM-DD HH:mm:ss
            const dateStr = localDateTime.replace(' ', 'T');
            
            // Create a date string with timezone offset
            // We'll use a hack: create date in target timezone by building ISO string
            const parts = dateStr.match(/(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?/);
            
            if (!parts) {
                console.error('Invalid datetime format:', localDateTime);
                return '';
            }

            const [, year, month, day, hour, minute, second = '00'] = parts;
            
            // Create a formatter to get timezone offset
            const tempDate = new Date(`${year}-${month}-${day}T${hour}:${minute}:${second}`);
            
            // Format in target timezone to get the actual date/time
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
                timeZoneName: 'short'
            });

            // Create date assuming it's in the target timezone
            // This is tricky - we need to find the UTC time that produces this local time
            const localString = `${year}-${month}-${day}T${hour}:${minute}:${second}`;
            
            // Use a more reliable method: temporarily set to target timezone
            const utcDate = new Date(localString + 'Z'); // Treat as UTC first
            const offset = this.getTimezoneOffset(timezone, utcDate);
            
            // Adjust by the offset to get the correct UTC time
            const adjustedDate = new Date(utcDate.getTime() - (offset * 60000));
            
            return adjustedDate.toISOString();
        } catch (e) {
            console.error('Error converting to UTC:', e);
            return '';
        }
    },

    /**
     * Get timezone offset in minutes for a specific timezone and date
     *
     * @param {string} timezone - IANA timezone identifier
     * @param {Date} date - Date to calculate offset for (handles DST)
     * @returns {number} Offset in minutes
     */
    getTimezoneOffset(timezone, date = new Date()) {
        try {
            // Get UTC time
            const utcDate = new Date(date.toLocaleString('en-US', { timeZone: 'UTC' }));
            
            // Get time in target timezone
            const tzDate = new Date(date.toLocaleString('en-US', { timeZone: timezone }));
            
            // Calculate difference in minutes
            return (tzDate.getTime() - utcDate.getTime()) / 60000;
        } catch (e) {
            console.error('Error getting timezone offset:', e);
            return 0;
        }
    },

    /**
     * Get timezone abbreviation (e.g., CDT, EST, PST)
     *
     * @param {string} timezone - IANA timezone identifier
     * @param {Date} date - Date for DST calculation (default: now)
     * @returns {string} Timezone abbreviation
     */
    getAbbreviation(timezone = null, date = new Date()) {
        timezone = timezone || this.detectTimezone();

        try {
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                timeZoneName: 'short'
            });

            const parts = formatter.formatToParts(date);
            const abbr = parts.find(part => part.type === 'timeZoneName');
            
            return abbr ? abbr.value : '';
        } catch (e) {
            console.error('Error getting timezone abbreviation:', e);
            return '';
        }
    },

    /**
     * Initialize timezone conversion for all datetime inputs on page
     *
     * Finds all inputs with data-utc-value and converts them to local time
     * Call this on page load or after dynamically adding inputs
     *
     * @param {HTMLElement} container - Container to search in (default: document)
     */
    initializeDatetimeInputs(container = document) {
        const inputs = container.querySelectorAll('input[type="datetime-local"][data-utc-value]');
        
        inputs.forEach(input => {
            const utcValue = input.dataset.utcValue;
            const timezone = this.getTimezone(input);
            
            if (utcValue) {
                input.value = this.toLocalInput(utcValue, timezone);
            }
        });
    },

    /**
     * Convert all datetime-local inputs to UTC before form submission
     *
     * Attach this to form submit event to automatically convert local times to UTC
     *
     * @param {HTMLFormElement} form - Form element
     * @param {string} timezone - Timezone to use for conversion (default: detected)
     */
    convertFormDatetimesToUTC(form, timezone = null) {
        timezone = timezone || this.detectTimezone();
        
        const inputs = form.querySelectorAll('input[type="datetime-local"]');
        
        inputs.forEach(input => {
            if (input.value) {
                // Store original value in case needed
                input.dataset.originalValue = input.value;
                
                // Convert to UTC
                const utcValue = this.toUTC(input.value, timezone);
                
                // Only proceed if conversion was successful
                if (utcValue) {
                    // Create hidden input with UTC value
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = input.name;
                    hiddenInput.value = utcValue;
                    
                    // Disable original input so it doesn't submit
                    input.disabled = true;
                    
                    // Add hidden input to form
                    form.appendChild(hiddenInput);
                } else {
                    // Conversion failed - preserve original value and flag error
                    console.error('Failed to convert datetime to UTC:', input.value);
                    input.dataset.conversionError = 'true';
                    // Original input remains enabled with user's value
                }
            }
        });
    }
};

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = KMP_Timezone;
}

// Make available globally
if (typeof window !== 'undefined') {
    window.KMP_Timezone = KMP_Timezone;
}

// Auto-initialize on DOM ready
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            KMP_Timezone.initializeDatetimeInputs();
        });
    } else {
        // DOM already loaded
        KMP_Timezone.initializeDatetimeInputs();
    }
}
