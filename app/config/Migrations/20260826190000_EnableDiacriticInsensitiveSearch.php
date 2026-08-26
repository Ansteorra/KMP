<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class EnableDiacriticInsensitiveSearch extends BaseMigration
{
    /**
     * Enable PostgreSQL's general-purpose diacritic folding dictionary.
     */
    public function up(): void
    {
        if ($this->getAdapter()->getAdapterType() !== 'pgsql') {
            return;
        }

        $this->execute('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    /**
     * Keep the shared extension available when one tenant rolls back.
     */
    public function down(): void
    {
    }
}
