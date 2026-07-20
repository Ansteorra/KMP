<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Entity\Permission;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\DateTime;

/**
 * Find inert role grants: active member-role assignments with no branch scope
 * on roles that carry branch-scoped permissions.
 *
 * PermissionsLoader resolves branch-scoped permissions through the assignment's
 * branch, so a branchless grant of such a role silently confers nothing — the
 * member looks assigned everywhere in the UI but every permission check fails.
 *
 * Usage:
 *   bin/cake roles_audit_branch_scope             # report inert grants
 *   bin/cake roles_audit_branch_scope --fix-branch <id>  # repair them to a branch
 */
class RolesAuditBranchScopeCommand extends Command
{
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'roles_audit_branch_scope';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return parent::buildOptionParser($parser)
            ->setDescription(
                'List active branchless member-role assignments on roles with branch-scoped permissions.',
            )
            ->addOption('fix-branch', [
                'help' => 'Branch ID to assign to every reported grant (repairs them in place).',
                'default' => null,
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $memberRoles = $this->fetchTable('MemberRoles');
        $now = DateTime::now();

        $inert = $memberRoles->find()
            ->contain(['Members' => ['fields' => ['id', 'sca_name', 'email_address']]])
            ->innerJoinWith('Roles.Permissions')
            ->where([
                'MemberRoles.branch_id IS' => null,
                'Permissions.scoping_rule !=' => Permission::SCOPE_GLOBAL,
                'OR' => [
                    'MemberRoles.expires_on IS' => null,
                    'MemberRoles.expires_on >=' => $now,
                ],
            ])
            ->contain(['Roles' => ['fields' => ['id', 'name']]])
            ->select($memberRoles)
            ->distinct(['MemberRoles.id'])
            ->orderBy(['MemberRoles.id' => 'ASC'])
            ->all()
            ->toList();

        if ($inert === []) {
            $io->success('No inert branchless role grants found.');

            return static::CODE_SUCCESS;
        }

        $io->warning(sprintf('%d inert branchless role grant(s):', count($inert)));
        foreach ($inert as $grant) {
            $io->out(sprintf(
                '  member_role #%d — %s (%s) → role "%s", start %s, expires %s',
                (int)$grant->id,
                (string)($grant->member->sca_name ?? 'unknown'),
                (string)($grant->member->email_address ?? '?'),
                (string)($grant->role->name ?? '?'),
                (string)$grant->start_on,
                $grant->expires_on !== null ? (string)$grant->expires_on : 'never',
            ));
        }

        $fixBranch = $args->getOption('fix-branch');
        if ($fixBranch === null) {
            $io->info('Re-run with --fix-branch <branch id> to repair these grants in place.');

            return static::CODE_ERROR;
        }

        $branchId = (int)$fixBranch;
        $this->fetchTable('Branches')->get($branchId);
        foreach ($inert as $grant) {
            $grant->branch_id = $branchId;
            $memberRoles->saveOrFail($grant);
        }
        $io->success(sprintf('Repaired %d grant(s) to branch %d.', count($inert), $branchId));

        return static::CODE_SUCCESS;
    }
}
