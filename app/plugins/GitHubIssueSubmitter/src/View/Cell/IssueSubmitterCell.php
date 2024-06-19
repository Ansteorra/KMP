<?php

namespace GitHubIssueSubmitter\View\Cell;

use Cake\View\Cell;
use App\KMP\StaticHelpers;

class IssueSubmitterCell extends Cell
{
    public function display()
    {
        $activeFeature =
            StaticHelpers::getAppSetting("KMP.GitHub.AllowIssueSubmission", "yes");
        if ($activeFeature == "yes") {
            $this->set('activeFeature', true);
        } else {
            $this->set('activeFeature', false);
        }
    }
}