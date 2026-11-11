<?php
declare(strict_types=1);

use Migrations\BaseMigration;

require_once dirname(__DIR__) . '/Seeds/InitLocalCalendarDeputySeed.php';

/** Add the non-warrant local deputy without changing Seneschal or publication grants. */
class AddLocalCalendarDeputy extends BaseMigration
{
    /** @return void */
    public function up(): void
    {
        (new InitLocalCalendarDeputySeed())->run();
    }

    /** @return void */
    public function down(): void
    {
        throw new RuntimeException(
            'Local Calendar Deputy setup is irreversible because offices and roles may be assigned.',
        );
    }
}
