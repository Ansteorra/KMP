<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add hamlet-mode fields to Branches table.
 *
 * - can_have_officers: mirrors can_have_members pattern; controls whether
 *   the branch participates in the officer system.
 * - contact_id: nullable FK to members.id; single point of contact for
 *   branches operating in "hamlet" mode (no officers).
 */
class AddHamletFieldsToBranches extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('branches');

        if (!$table->hasColumn('can_have_officers')) {
            $table->addColumn('can_have_officers', 'boolean', [
                'default' => true,
                'limit' => null,
                'null' => false,
                'after' => 'can_have_members',
                'comment' => 'Whether this branch can have officers assigned',
            ]);
        }

        if (!$table->hasColumn('contact_id')) {
            $table->addColumn('contact_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => true,
                'signed' => true,
                'after' => 'can_have_officers',
                'comment' => 'Point of contact member for hamlet-mode branches',
            ]);
        }

        $table->update();

        // Add foreign key separately after column exists
        if ($table->hasColumn('contact_id')) {
            $table->addForeignKey('contact_id', 'members', 'id', [
                'update' => 'NO_ACTION',
                'delete' => 'SET_NULL',
                'constraint' => 'fk_branches_contact_member',
            ]);
            $table->update();
        }
    }

    public function down(): void
    {
        $table = $this->table('branches');

        if ($table->hasForeignKey('contact_id')) {
            $table->dropForeignKey('contact_id');
            $table->update();
        }

        if ($table->hasColumn('contact_id')) {
            $table->removeColumn('contact_id');
        }

        if ($table->hasColumn('can_have_officers')) {
            $table->removeColumn('can_have_officers');
        }

        $table->update();
    }
}
