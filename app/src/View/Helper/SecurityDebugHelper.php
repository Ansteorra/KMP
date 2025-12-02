<?php

declare(strict_types=1);

namespace App\View\Helper;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\Permission;
use App\Services\AuthorizationService;
use Cake\View\Helper;
use Cake\Core\Configure;

/**
 * Security Debug Helper
 * 
 * Provides debugging information about user permissions, policies, and authorization checks.
 * Only active when debug mode is enabled. Displays policies with branch scope info
 * and authorization check log from the current request.
 * 
 * @property \Cake\View\Helper\HtmlHelper $Html
 */
class SecurityDebugHelper extends Helper
{
    protected array $helpers = ['Html'];

    /**
     * Display complete security debug information
     * 
     * @param \App\KMP\KmpIdentityInterface|null $user The current user
     * @return string HTML output of security information
     */
    public function displaySecurityInfo(?KmpIdentityInterface $user): string
    {
        if (!Configure::read('debug')) {
            return '';
        }

        if (!$user) {
            return $this->formatNoUserInfo();
        }

        $output = '<div class="security-debug-info mt-3 p-3 border rounded bg-light">';
        $output .= '<h4 class="mb-3">Security Debug Information</h4>';
        $output .= $this->displayUserPolicies($user);
        $output .= $this->displayAuthorizationChecks();
        $output .= '</div>';

        return $output;
    }

    /**
     * Display user policies with branch scope information
     * 
     * @param \App\KMP\KmpIdentityInterface $user The current user
     * @return string HTML output of user policies
     */
    public function displayUserPolicies(KmpIdentityInterface $user): string
    {
        $policies = $user->getPolicies();
        $isSuperUser = $user->isSuperUser();

        $output = '<div class="mb-4">';
        $output .= '<h5 class="mb-3">User Policies';

        if ($isSuperUser) {
            $output .= ' <span class="badge bg-danger ms-2">SUPER USER</span>';
        }

        $output .= '</h5>';

        if ($isSuperUser) {
            $output .= '<div class="alert alert-warning mb-3">';
            $output .= '<strong>⚠️ Super User Detected:</strong> This user has super user privileges and bypasses all policy checks. ';
            $output .= 'The policies shown below are explicitly assigned, but this user has access to ALL actions regardless of policy assignments.';
            $output .= '</div>';
        }

        if (empty($policies)) {
            $msg = $isSuperUser ?
                'No explicit policies assigned (super user has full access anyway).' :
                'User has no policies assigned.';
            return $output . '<div class="alert alert-info">' . $msg . '</div></div>';
        }

        // Count total policies
        $totalMethods = 0;
        foreach ($policies as $methods) {
            $totalMethods += count($methods);
        }

        $output .= '<p class="text-muted mb-2">Showing ' . count($policies) . ' policy classes with ' . $totalMethods . ' total methods</p>';

        $output .= '<div class="table-responsive">';
        $output .= '<table class="table table-sm table-bordered table-hover">';
        $output .= '<thead class="table-dark">';
        $output .= '<tr>';
        $output .= '<th>Policy Class</th>';
        $output .= '<th>Method</th>';
        $output .= '<th>Scope</th>';
        $output .= '<th>Branches</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';

        foreach ($policies as $policyClass => $methods) {
            $shortClassName = $this->getShortClassName($policyClass);
            $firstMethod = true;

            foreach ($methods as $methodName => $policyData) {
                $output .= '<tr>';

                if ($firstMethod) {
                    $rowspan = count($methods);
                    $output .= '<td rowspan="' . $rowspan . '" class="fw-bold">' . h($shortClassName) . '</td>';
                    $firstMethod = false;
                }

                $output .= '<td><code>' . h($methodName) . '</code></td>';
                $output .= '<td>' . $this->formatScopingRule($policyData->scoping_rule) . '</td>';
                $output .= '<td>' . $this->formatBranchIds($policyData) . '</td>';
                $output .= '</tr>';
            }
        }

        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Display authorization checks that occurred during this request
     * 
     * @return string HTML output of authorization checks
     */
    public function displayAuthorizationChecks(): string
    {
        $checks = AuthorizationService::getAuthCheckLog();

        if (empty($checks)) {
            return '<div class="alert alert-info">No authorization checks performed yet.</div>';
        }

        $output = '<div class="mb-4">';
        $output .= '<h5 class="mb-3">Authorization Checks (' . count($checks) . ' total)</h5>';
        $output .= '<div class="table-responsive">';
        $output .= '<table class="table table-sm table-bordered table-hover">';
        $output .= '<thead class="table-dark">';
        $output .= '<tr>';
        $output .= '<th>#</th>';
        $output .= '<th>Action</th>';
        $output .= '<th>Resource</th>';
        $output .= '<th>Result</th>';
        $output .= '<th>Args</th>';
        $output .= '</tr>';
        $output .= '</thead>';
        $output .= '<tbody>';

        foreach ($checks as $index => $check) {
            $resultClass = $check['result'] ? 'table-success' : 'table-danger';
            $resultText = $check['result'] ? '✓ Granted' : '✗ Denied';

            $output .= '<tr class="' . $resultClass . '">';
            $output .= '<td>' . ($index + 1) . '</td>';
            $output .= '<td><code>' . h($check['action']) . '</code></td>';
            $output .= '<td>' . h($check['resource']) . '</td>';
            $output .= '<td class="fw-bold">' . $resultText . '</td>';
            $output .= '<td>' . ($check['additional_args'] > 0 ? $check['additional_args'] : '-') . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Format message for no user
     * 
     * @return string
     */
    protected function formatNoUserInfo(): string
    {
        return '<div class="alert alert-warning">No authenticated user found.</div>';
    }

    /**
     * Get short class name from fully qualified class name
     * 
     * @param string $className Full class name
     * @return string Short class name
     */
    protected function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Format scoping rule for display
     * 
     * @param string $scopingRule The scoping rule constant
     * @return string Formatted scoping rule
     */
    protected function formatScopingRule(string $scopingRule): string
    {
        $badges = [
            Permission::SCOPE_GLOBAL => '<span class="badge bg-primary">Global</span>',
            Permission::SCOPE_BRANCH_ONLY => '<span class="badge bg-info">Branch Only</span>',
            Permission::SCOPE_BRANCH_AND_CHILDREN => '<span class="badge bg-success">Branch + Children</span>',
        ];

        return $badges[$scopingRule] ?? '<span class="badge bg-secondary">' . h($scopingRule) . '</span>';
    }

    /**
     * Format branch IDs for display
     * 
     * @param object $policyData Policy data object
     * @return string Formatted branch information
     */
    protected function formatBranchIds($policyData): string
    {
        if ($policyData->scoping_rule === Permission::SCOPE_GLOBAL) {
            return '<em class="text-muted">All branches</em>';
        }

        if (empty($policyData->branch_ids)) {
            return '<em class="text-muted">None</em>';
        }

        $branchIds = $policyData->branch_ids;

        if (count($branchIds) > 5) {
            $shown = array_slice($branchIds, 0, 5);
            $remaining = count($branchIds) - 5;
            return implode(', ', $shown) . ' <small class="text-muted">(+' . $remaining . ' more)</small>';
        }

        return implode(', ', $branchIds);
    }
}
