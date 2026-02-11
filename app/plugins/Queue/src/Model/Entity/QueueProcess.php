<?php

declare(strict_types=1);

namespace Queue\Model\Entity;

use Cake\ORM\Entity;
use App\Model\Entity\BaseEntity;

/**
 * QueueProcess Entity
 *
 * @property int $id
 * @property string $pid
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property bool $terminate
 * @property string|null $server
 * @property string $workerkey
 * @property \Queue\Model\Entity\QueuedJob|null $active_job
 */
class QueueProcess extends BaseEntity
{

	/**
	 * @var array<string, bool>
	 */
	protected array $_accessible = [
		'*' => true,
		'id' => false,
	];

	/**
	 * Queue processes are not branch-scoped.
	 *
	 * @return int|null
	 */
	public function getBranchId(): ?int
	{
		return null;
	}
}
