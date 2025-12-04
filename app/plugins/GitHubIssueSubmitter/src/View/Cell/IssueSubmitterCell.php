<?php

declare(strict_types=1);

namespace GitHubIssueSubmitter\View\Cell;

use Cake\View\Cell;
use App\KMP\StaticHelpers;

/**
 * Issue Submitter View Cell
 *
 * Provides conditional rendering for the feedback submission interface
 * based on plugin activation status.
 *
 * @package GitHubIssueSubmitter\View\Cell
 * @see /docs/5.4-github-issue-submitter-plugin.md
 */
class IssueSubmitterCell extends Cell
{
    /**
     * Display method - checks plugin activation and sets template variables
     *
     * Sets `activeFeature` to true/false based on plugin enabled status.
     * Template uses this to conditionally render the feedback interface.
     *
     * @return void
     */
    public function display()
    {
        $activeFeature =
            StaticHelpers::pluginEnabled("GitHubIssueSubmitter");
        if ($activeFeature == "yes") {
            $this->set('activeFeature', true);
        } else {
            $this->set('activeFeature', false);
        }
    }
}
