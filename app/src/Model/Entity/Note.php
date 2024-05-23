<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Note Entity
 *
 * @property int $id
 * @property int $author_id
 * @property \Cake\I18n\DateTime $created_on
 * @property string|null $topic_model
 * @property int $topic_id
 * @property string|null $subject
 * @property string|null $body
 * @property bool $private
 */
class Note extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'author_id' => true,
        'created_on' => true,
        'topic_model' => true,
        'topic_id' => true,
        'subject' => true,
        'body' => true,
        'private' => true,
    ];
}
