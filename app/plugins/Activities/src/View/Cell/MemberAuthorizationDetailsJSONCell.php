<?php

declare(strict_types=1);

namespace Activities\View\Cell;

use App\KMP\PermissionsLoader;
use App\View\Cell\BasePluginCell;
use Cake\View\Cell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

use function PHPUnit\Framework\isEmpty;

/**
 * MemberAuthorizationDetailsJSON cell
 */
class MemberAuthorizationDetailsJSONCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Members', 'action' => 'viewCardJson', 'plugin' => null],
        ['controller' => 'Members', 'action' => 'viewMobileCardJson', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => BasePluginCell::PLUGIN_TYPE_JSON, // 'tab' or 'detail' or 'modal'
        'id' => 'memberAuthorizations',
        'order' => 1,
        'cell' => 'Activities.MemberAuthorizationDetailsJSON'
    ];
    public static function getViewConfigForRoute($route)
    {
        return parent::getRouteEventResponse($route, self::$pluginData, self::$validRoutes);
    }
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
    public function initialize(): void
    {
    }

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        $authTable = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $currentAuths = $authTable->find('current')
            ->select(['id', 'activity_id', 'member_id', 'ActivityGroups.name', 'Activities.name', 'expires_on'])
            ->contain(['Activities' => function (SelectQuery $q) {
                return $q
                    ->select(['Activities.id', 'Activities.name'])
                    ->contain(['ActivityGroups' => function (SelectQuery $q) {
                        return $q->select(['ActivityGroups.id', 'ActivityGroups.name']);
                    }]);
            }])
            ->where(['member_id' => $id])->OrderBy(['ActivityGroups.name', 'Activities.name'])->toArray();
        $organizedAuths = [];
        foreach ($currentAuths as $auth) {
            $activityGroup = $auth->activity->activity_group->name;
            $activityName = $auth->activity->name;
            $organizedAuths[$activityGroup][] = $activityName . " : " . $auth->expires_on->toDateString();
        }
        $memberPermissions = PermissionsLoader::getPermissions($id);
        $permissionIds = [];
        foreach ($memberPermissions as $permission) {
            $permissionIds[] = $permission->id;
        }
        $currentApproverFor = [];
        if (!isEmpty($permissionIds)) {
            $activitiesTbl = TableRegistry::getTableLocator()->get('Activities.Activities');
            $activities = $activitiesTbl->find()
                ->where(['Activities.permission_id IN' => $permissionIds])
                ->contain(['ActivityGroups' => function (SelectQuery $q) {
                    return $q->select(['ActivityGroups.id', 'ActivityGroups.name']);
                }])
                ->distinct()
                ->toArray();

            $organizedAuthorisor = [];
            foreach ($activities as $activity) {
                $activityGroup = $activity->activity_group->name;
                $activityName = $activity->name;
                $organizedAuthorisor[$activityGroup][] = $activityName;
            }
        } else {
            $organizedAuthorisor = [];
        }
        $responseData = ["Can Authorize" => $organizedAuthorisor, "Authorizations" => $organizedAuths,];
        $this->set(compact('responseData'));
    }
}