<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;

/**
 * MemberOfficers cell
 */
class MemberOfficersCell extends Cell
{
    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */
    protected array $_validCellOptions = [];

    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void {}

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        $offiersTable = TableRegistry::getTableLocator()->get("Officers.Officers");
        $currentOfficers = $offiersTable->addDisplayConditionsAndFields($offiersTable->find('current')->where(['Officers.member_id' => $id]), "current")->toArray();
        $upcomingOfficers = $offiersTable->addDisplayConditionsAndFields($offiersTable->find('upcoming')->where(['Officers.member_id' => $id]), "upcoming")->toArray();
        $previousOfficers = $offiersTable->addDisplayConditionsAndFields($offiersTable->find('previous')->where(['Officers.member_id' => $id]), "previous")->toArray();
        $this->set(compact('currentOfficers', 'upcomingOfficers', 'previousOfficers', 'id'));
    }
}