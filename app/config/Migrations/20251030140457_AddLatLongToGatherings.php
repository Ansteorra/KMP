<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddLatLongToGatherings extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('gatherings');

        // Add latitude column (range: -90 to 90, precision to ~1.1 meters)
        $table->addColumn('latitude', 'decimal', [
            'default' => null,
            'null' => true,
            'precision' => 10,
            'scale' => 8,
            'comment' => 'Latitude coordinate from Google Maps geocoding'
        ]);

        // Add longitude column (range: -180 to 180, precision to ~1.1 meters)
        $table->addColumn('longitude', 'decimal', [
            'default' => null,
            'null' => true,
            'precision' => 11,
            'scale' => 8,
            'comment' => 'Longitude coordinate from Google Maps geocoding'
        ]);

        $table->update();
    }
}
