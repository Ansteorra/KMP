<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class RemoveAwardsViewConfigSettings extends BaseMigration
{
    public function up(): void
    {
        $this->execute(
            "DELETE FROM app_settings WHERE name LIKE 'Awards.ViewConfig.%'"
        );
    }

    public function down(): void
    {
        // ViewConfig settings were unused — no restoration needed.
    }
}
