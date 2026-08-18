<?php
declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\AppController as BaseController;

/**
 * Awards Plugin AppController - Base controller for award management system.
 *
 * Extends main KMP AppController with Awards-specific component configuration.
 * Establishes security baseline for all award management controllers.
 *
 * @package Awards\Controller
 * @see \App\Controller\AppController Parent controller
 */
class AppController extends BaseController
{
    /**
     * Initialize Awards Plugin Base Controller.
     *
     * Loads Authentication, Authorization, and Flash components.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        // $this->loadComponent('FormProtection');
    }

    /**
     * Build a bounded, plain-text count of operational result reasons.
     *
     * @param array<array-key, mixed> $entries Result entries containing a reason.
     * @param string $label User-facing summary label.
     * @return string Empty when no usable reasons were supplied.
     */
    protected function operationReasonSummary(array $entries, string $label): string
    {
        $reasonCounts = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_scalar($entry['reason'] ?? null)) {
                continue;
            }
            $reason = strip_tags((string)$entry['reason']);
            $reason = preg_replace('/[\p{C}\s]+/u', ' ', $reason);
            $reason = trim($reason ?? '');
            if ($reason === '') {
                continue;
            }
            if (mb_strlen($reason) > 160) {
                $reason = rtrim(mb_substr($reason, 0, 159)) . '…';
            }
            $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
        }
        if ($reasonCounts === []) {
            return '';
        }

        arsort($reasonCounts, SORT_NUMERIC);
        $shownReasons = array_slice($reasonCounts, 0, 5, true);
        $parts = [];
        foreach ($shownReasons as $reason => $count) {
            $parts[] = __('{0} ({1})', rtrim($reason, '.'), $count);
        }
        $hiddenCount = count($reasonCounts) - count($shownReasons);
        if ($hiddenCount > 0) {
            $parts[] = __('{0} more reason(s)', $hiddenCount);
        }

        return ' ' . __('{0}: {1}.', $label, implode('; ', $parts));
    }

    /**
     * Build a plain-text summary from trusted category labels and their counts.
     *
     * @param array<string, int> $categoryCounts Counts keyed by static, user-safe labels.
     * @param string $label User-facing summary label.
     * @return string Empty when every count is zero.
     */
    protected function operationCategorySummary(array $categoryCounts, string $label): string
    {
        $parts = [];
        foreach ($categoryCounts as $category => $count) {
            if ($count > 0) {
                $parts[] = __('{0} ({1})', $category, $count);
            }
        }
        if ($parts === []) {
            return '';
        }

        return ' ' . __('{0}: {1}.', $label, implode('; ', $parts));
    }
}
