<?php
declare(strict_types=1);

use Migrations\BaseMigration;

require_once dirname(__DIR__) . '/Seeds/InitCalendarDeputySeed.php';

/** Provision the Calendar Deputy without changing existing local seneschal permissions. */
class AddCalendarDeputy extends BaseMigration
{
    /** @return void */
    public function up(): void
    {
        (new InitCalendarDeputySeed())->run();
    }

    /** @return void */
    public function down(): void
    {
        throw new RuntimeException('Calendar Deputy setup is irreversible because offices and roles may be assigned.');
    }
}
