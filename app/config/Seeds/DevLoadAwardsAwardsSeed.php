<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * AwardsAwards seed.
 */
class DevLoadAwardsAwardsSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'id' => 1,
                'name' => 'Award of the Sable Falcon',
                'abbreviation' => 'Falcon',
                'description' => 'Given to those who have striven greatly to further their skill level and capabilities in heavy weapons combat. Often given for a single notable deed.',
                'insignia' => 'A cord braided sable and Or tied to a metal ring worn on the belt.',
                'badge' => 'None',
                'charter' => '',
                'domain_id' => 1,
                'level_id' => 1,
                'branch_id' => 1,
                'modified' => '2024-06-25 22:21:14',
                'created' => '2024-06-25 22:21:14',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 2,
                'name' => 'Award of the Sable Talon of Ansteorra for Chivalric',
                'abbreviation' => 'Talon',
                'description' => 'Confers an Award of Arms. Given to those who have striven greatly to further their skill levels and capabilities in any recognized marshallate activity, who have positively influenced the skills and capabilities of others in these fields, and who lead by example when on and off the field of endeavor. May be given repeatedly  for different martial activities. ',
                'insignia' => 'The badge worn as a medallion or pin',
                'badge' => '(Fieldless) An eagle’s leg erased à la quise sable.',
                'charter' => '',
                'domain_id' => 1,
                'level_id' => 2,
                'branch_id' => 1,
                'modified' => '2024-06-25 22:22:51',
                'created' => '2024-06-25 22:22:00',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 3,
                'name' => 'Order of the Centurions of the Sable Star of Ansteorra',
                'abbreviation' => 'Centurion',
                'description' => 'Polling order. Confers a Grant of Arms. Given to those who have demonstrated exceptional leadership, skill and honor in chivalric combat.',
                'insignia' => 'A ribbon Or edged gules charged with an Ansteorran star (a mullet of five greater
and five lesser points) sable worn as a garter, and/or the badge of the order prominently
displayed on a red cloak.',
                'badge' => 'On an eagle displayed wings inverted Or a mullet of five greater and five lesser points
sable.',
                'charter' => '',
                'domain_id' => 1,
                'level_id' => 3,
                'branch_id' => 1,
                'modified' => '2024-06-25 22:22:42',
                'created' => '2024-06-25 22:22:42',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
            [
                'id' => 4,
                'name' => 'Order of Knighthood or Order of Mastery of Arms',
                'abbreviation' => 'Order of Chivalry',
                'description' => 'Polling order. The highest award for chivalric combat.',
                'insignia' => 'Knighthood : White belt and unadorned gold chain
Master at Arms: White baldric',
                'badge' => 'Knighthood : (Fieldless) A white belt
Master at Arms: (Fieldless) A white baldric',
                'charter' => '',
                'domain_id' => 1,
                'level_id' => 4,
                'branch_id' => 1,
                'modified' => '2024-06-25 22:24:06',
                'created' => '2024-06-25 22:24:06',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => NULL,
            ],
        ];

        $table = $this->table('awards_awards');
        $table->insert($data)->save();
    }
}
