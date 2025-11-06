<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add Timezone Field to Members Table
 *
 * Adds a timezone field to the members table to store each user's preferred timezone.
 * This enables personalized date/time display throughout the application while
 * maintaining UTC storage in the database.
 *
 * ## Migration Details
 * - **Table**: members
 * - **Field**: timezone
 * - **Type**: VARCHAR(50)
 * - **Nullable**: YES (defaults to application setting or UTC)
 * - **Default**: NULL
 *
 * ## Timezone Priority
 * 1. User's timezone (stored in this field)
 * 2. Application default timezone (from AppSettings)
 * 3. UTC (final fallback)
 *
 * ## Valid Timezone Values
 * Must be valid PHP timezone identifiers from the IANA Time Zone Database:
 * - America/Chicago (US Central)
 * - America/New_York (US Eastern)
 * - America/Los_Angeles (US Pacific)
 * - Europe/London
 * - UTC
 * 
 * @see https://www.php.net/manual/en/timezones.php
 * @see \App\KMP\TimezoneHelper For timezone handling utilities
 */
class AddTimezoneToMembers extends AbstractMigration
{
    /**
     * Add timezone column to members table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('members');

        $table->addColumn('timezone', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
            'comment' => 'User preferred timezone (IANA identifier, e.g., America/Chicago)',
            'after' => 'email_address', // Place after email for logical grouping
        ]);

        $table->update();
    }
}
