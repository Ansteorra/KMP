<?php
declare(strict_types=1);

use App\Model\Entity\Permission;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

class ScopeBestowalTodoPermissions extends BaseMigration
{
    private const SUFFIXES = [
        'Scroll Management',
        'Regalia Management',
        'Award Schedule Management',
        'Court Management',
        'Court Reporter',
    ];

    private const TIER_SCOPE_RULES = [
        'Crown' => Permission::SCOPE_BRANCH_ONLY,
        'Principality' => Permission::SCOPE_BRANCH_ONLY,
        'Baronial' => Permission::SCOPE_BRANCH_AND_CHILDREN,
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->updateScopeRules(self::TIER_SCOPE_RULES);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $this->updateScopeRules(array_fill_keys(array_keys(self::TIER_SCOPE_RULES), Permission::SCOPE_GLOBAL));
    }

    /**
     * @param array<string, string> $scopeRules Scope rule by permission prefix.
     * @return void
     */
    private function updateScopeRules(array $scopeRules): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');

        foreach ($scopeRules as $prefix => $scopeRule) {
            foreach (self::SUFFIXES as $suffix) {
                $permissions->updateAll(
                    ['scoping_rule' => $scopeRule],
                    ['name' => $prefix . ' ' . $suffix],
                );
            }
        }
    }
}
